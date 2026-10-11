<?php
require_once "db.php";

$message = "";

function uploadPaymentPicture($field) {
    if (!isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Picture upload failed.");
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        throw new Exception("Each picture must be smaller than 5 MB.");
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

    $types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($types[$mime]) || !getimagesize($tmp)) {
        throw new Exception("Upload a JPG, PNG, or WEBP image.");
    }

    $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    $destination = __DIR__ . '/uploads/payments/' . $name;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new Exception("Could not save the picture.");
    }

    return 'uploads/payments/' . $name;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $receiptPicture = null;
    $notebookPicture = null;

    try {
        $clientId = (int)($_POST["client_id"] ?? 0);
        $saleId = ($_POST["sale_id"] ?? "") === ""
            ? null : (int)$_POST["sale_id"];
        $currency = $_POST["currency"] ?? "";
        $amount = $_POST["amount"] ?? "";
        $paymentDate = $_POST["payment_date"] ?? "";
        $note = trim($_POST["note"] ?? "");

        if ($clientId < 1) {
            throw new Exception("Please select a client.");
        }

        if (!in_array($currency, ["USD", "AFN"], true)) {
            throw new Exception("Select USD or AFN.");
        }

        if (!is_numeric($amount) || !is_finite((float)$amount)
            || (float)$amount <= 0 || (float)$amount > 9999999999999) {
            throw new Exception("Enter a valid payment amount.");
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            throw new Exception("Enter a valid payment date.");
        }

        $date = DateTime::createFromFormat('!Y-m-d', $paymentDate);
        if (!$date || $date->format('Y-m-d') !== $paymentDate) {
            throw new Exception("Invalid payment date.");
        }

        // Check that the client exists.
        $check = $conn->prepare("SELECT id FROM clients WHERE id = ?");
        $check->bind_param("i", $clientId);
        $check->execute();

        if (!$check->get_result()->fetch_assoc()) {
            throw new Exception("Selected client does not exist.");
        }

        // If an invoice is selected, it must belong to this
        // client and use the same currency as the payment.
        if ($saleId !== null) {
            if ($saleId < 1) {
                throw new Exception("Invalid invoice.");
            }

            $checkSale = $conn->prepare(
                "SELECT id FROM sales
                 WHERE id = ? AND client_id = ? AND currency = ?"
            );

            $checkSale->bind_param(
                "iis", $saleId, $clientId, $currency
            );
            $checkSale->execute();

            if (!$checkSale->get_result()->fetch_assoc()) {
                throw new Exception(
                    "The invoice must belong to the selected client "
                    . "and use the same currency."
                );
            }
        }

        if (!is_dir(__DIR__ . "/uploads/payments")) {
            throw new Exception("Create the uploads/payments folder first.");
        }

        $receiptPicture = uploadPaymentPicture("receipt_picture");
        $notebookPicture = uploadPaymentPicture("notebook_picture");

        $amountValue = (float)$amount;

        $stmt = $conn->prepare(
            "INSERT INTO client_payments
             (client_id, sale_id, currency, amount, payment_date,
              receipt_picture, notebook_picture, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "iisdssss",
            $clientId,
            $saleId,
            $currency,
            $amountValue,
            $paymentDate,
            $receiptPicture,
            $notebookPicture,
            $note
        );

        $stmt->execute();

        $message = "Payment saved successfully.";

    } catch (Throwable $e) {
        foreach ([$receiptPicture, $notebookPicture] as $picture) {
            if ($picture && is_file(__DIR__ . '/' . $picture)) {
                unlink(__DIR__ . '/' . $picture);
            }
        }

        $message = $e instanceof mysqli_sql_exception
            ? "Database error. Please check your database settings."
            : $e->getMessage();
    }
}

// ========================================
// AJAX: GET CLIENT BALANCE
// ========================================
if (isset($_GET['ajax_balance'])) {
    header('Content-Type: application/json');

    $client_id = (int)($_GET['client_id'] ?? 0);
    $currency  = trim($_GET['currency'] ?? '');

    if ($client_id < 1 || !in_array($currency, ['USD', 'AFN'], true)) {
        echo json_encode(['success' => false]);
        exit();
    }

    // Total invoiced for this client + currency
    $invStmt = $conn->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) AS total
         FROM sales WHERE client_id = ? AND currency = ?"
    );
    $invStmt->bind_param("is", $client_id, $currency);
    $invStmt->execute();
    $invoice_total = (float)($invStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $invStmt->close();

    // Total paid for this client + currency
    $payStmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM client_payments WHERE client_id = ? AND currency = ?"
    );
    $payStmt->bind_param("is", $client_id, $currency);
    $payStmt->execute();
    $paid_total = (float)($payStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $payStmt->close();

    $unpaid = $invoice_total - $paid_total;

    // Client name
    $nameStmt = $conn->prepare("SELECT full_name FROM clients WHERE id = ?");
    $nameStmt->bind_param("i", $client_id);
    $nameStmt->execute();
    $client_name = $nameStmt->get_result()->fetch_assoc()['full_name'] ?? '';
    $nameStmt->close();

    echo json_encode([
        'success'       => true,
        'client_name'   => $client_name,
        'currency'      => $currency,
        'invoice_total' => round($invoice_total, 2),
        'paid_total'    => round($paid_total, 2),
        'unpaid_total'  => round($unpaid, 2),
    ]);
    exit();
}

$clients = $conn->query(
    "SELECT id, full_name FROM clients ORDER BY full_name"
);

$invoices = $conn->query(
    "SELECT id, client_id, invoice_number, currency, total_amount
     FROM sales ORDER BY id DESC"
);

// Separate payment histories for each currency.
$usdPayments = $conn->query(
    "SELECT p.*, c.full_name, s.invoice_number
     FROM client_payments p
     JOIN clients c ON c.id = p.client_id
     LEFT JOIN sales s ON s.id = p.sale_id
     WHERE p.currency = 'USD'
     ORDER BY p.payment_date DESC, p.id DESC"
);

$afnPayments = $conn->query(
    "SELECT p.*, c.full_name, s.invoice_number
     FROM client_payments p
     JOIN clients c ON c.id = p.client_id
     LEFT JOIN sales s ON s.id = p.sale_id
     WHERE p.currency = 'AFN'
     ORDER BY p.payment_date DESC, p.id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Payments</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 20px;
}
.container {
    max-width: 1200px;
    margin: auto;
}
form, .table-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
}
.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}
label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
}
input, select, textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
button {
    padding: 11px 20px;
    background: #1769aa;
    color: white;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
}
table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
}
th, td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}
th {
    background: #e9eef5;
}
.table-scroll {
    overflow-x: auto;
}
.message {
    padding: 12px;
    background: #e5f5e8;
    margin-bottom: 15px;
}
img {
    max-width: 90px;
    max-height: 90px;
}
.usd-title { color: #1769aa; }
.afn-title { color: #258044; }

/* ========================================
   BALANCE PANEL
   ======================================== */
.balance-panel {
    display: none;
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    padding: 18px;
    margin: 20px 0;
    font-family: Arial, sans-serif;
}
.balance-panel.active {
    display: block;
    animation: fadeIn 0.25s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.balance-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    font-weight: bold;
    font-size: 1.05rem;
    color: #1769aa;
}
.balance-header i {
    color: #258044;
}
.balance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
}
.balance-item {
    background: white;
    padding: 12px 14px;
    border-radius: 8px;
    border-left: 4px solid #cbd5e1;
}
.balance-item .label {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    font-weight: bold;
}
.balance-item .value {
    display: block;
    font-size: 1.15rem;
    font-weight: bold;
    color: #1e3c72;
}
.balance-item.invoiced { border-left-color: #1769aa; }
.balance-item.invoiced .value { color: #1769aa; }
.balance-item.paid { border-left-color: #258044; }
.balance-item.paid .value { color: #258044; }
.balance-item.unpaid { border-left-color: #d97706; background: #fffbeb; }
.balance-item.unpaid .value { color: #d97706; }
.balance-item.unpaid.zero { border-left-color: #258044; background: #f0fdf4; }
.balance-item.unpaid.zero .value { color: #258044; }
.balance-item.remaining { border-left-color: #258044; background: #f0fdf4; }
.balance-item.remaining .value { color: #258044; }
.balance-item.overpaid { border-left-color: #dc2626; background: #fef2f2; }
.balance-item.overpaid .value { color: #dc2626; }

.balance-loading {
    display: none;
    padding: 15px;
    text-align: center;
    color: #64748b;
    font-size: 0.9rem;
    font-style: italic;
}
.balance-loading.active {
    display: block;
}

.balance-warning {
    margin-top: 12px;
    padding: 10px 14px;
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    border-radius: 6px;
    color: #92400e;
    font-size: 0.88rem;
    font-weight: 600;
    display: none;
}
.balance-warning.active { display: block; }

.balance-success {
    margin-top: 12px;
    padding: 10px 14px;
    background: #dcfce7;
    border-left: 4px solid #16a34a;
    border-radius: 6px;
    color: #166534;
    font-size: 0.88rem;
    font-weight: 600;
    display: none;
}
.balance-success.active { display: block; }

@media(max-width: 600px) {
    .grid { grid-template-columns: 1fr; }
    body { padding: 10px; }
    .balance-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>

<body>
<div class="container">

<h1>Client Payments</h1>
<p><a href="sale.php">Go to Sales Invoices</a></p>

<?php if ($message !== ""): ?>
<div class="message">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<h2>Record New Payment</h2>

<div class="grid">

<div>
<label>Client</label>
<select name="client_id" id="client_id" required>
<option value="">Select client</option>
<?php while ($c = $clients->fetch_assoc()): ?>
<option value="<?= $c['id'] ?>">
<?= htmlspecialchars($c['full_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<div>
<label>Payment Currency</label>
<select name="currency" id="currency" required>
<option value="USD">US Dollar (USD)</option>
<option value="AFN">Afghani (AFN)</option>
</select>
</div>

<div>
<label>Invoice (Optional)</label>
<select name="sale_id" id="sale_id">
<option value="">Not linked to an invoice</option>
<?php while ($i = $invoices->fetch_assoc()): ?>
<option
    value="<?= $i['id'] ?>"
    data-client="<?= $i['client_id'] ?>"
    data-currency="<?= htmlspecialchars($i['currency']) ?>">
    <?= htmlspecialchars($i['invoice_number']) ?>
    - <?= htmlspecialchars($i['currency']) ?>
    - <?= number_format((float)$i['total_amount'], 2) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<div>
<label>Payment Amount</label>
<input type="number" name="amount" id="amount"
       min="0.01" step="0.01" required>
</div>

<div>
<label>Payment Date</label>
<input type="date" name="payment_date"
       value="<?= date('Y-m-d') ?>" required>
</div>

<div>
<label>Receipt Picture (Optional)</label>
<input type="file" name="receipt_picture"
       accept="image/jpeg,image/png,image/webp">
</div>

<div>
<label>Notebook Picture (Optional)</label>
<input type="file" name="notebook_picture"
       accept="image/jpeg,image/png,image/webp">
</div>

</div>

<!-- ===== LIVE BALANCE PANEL ===== -->
<div class="balance-loading" id="balanceLoading">
    <i>Calculating balance...</i>
</div>

<div class="balance-panel" id="balancePanel">
    <div class="balance-header">
        <i>&#128176;</i>
        <span id="balanceClientName">Client Balance</span>
    </div>

    <div class="balance-grid" id="balanceGrid">
        <!-- filled by JS -->
    </div>

    <div class="balance-warning" id="balanceWarning">
        &#9888; You are paying more than the unpaid balance.
    </div>
    <div class="balance-success" id="balanceSuccess">
        &#10004; This payment will fully settle the balance.
    </div>
</div>

<p>
<label>Note</label>
<textarea name="note" rows="3"></textarea>
</p>

<button type="submit">Save Payment</button>
</form>

<?php
function showPaymentTable($result, $currency) {
    $total = 0;
?>
<div class="table-box">
<h2 class="<?= $currency === 'USD' ? 'usd-title' : 'afn-title' ?>">
    <?= $currency === 'USD'
        ? 'US Dollar Payments (USD)'
        : 'Afghani Payments (AFN)' ?>
</h2>

<div class="table-scroll">
<table>
<thead>
<tr>
<th>ID</th>
<th>Client</th>
<th>Invoice</th>
<th>Date</th>
<th>Amount</th>
<th>Receipt</th>
<th>Notebook Picture</th>
<th>Note</th>
</tr>
</thead>
<tbody>

<?php while ($p = $result->fetch_assoc()):
    $total += (float)$p['amount'];
?>
<tr>
<td><?= $p['id'] ?></td>
<td><?= htmlspecialchars($p['full_name']) ?></td>
<td><?= htmlspecialchars($p['invoice_number'] ?? 'Not linked') ?></td>
<td><?= htmlspecialchars($p['payment_date']) ?></td>
<td><?= number_format((float)$p['amount'], 2) ?></td>

<td>
<?php if ($p['receipt_picture']): ?>
<a href="<?= htmlspecialchars($p['receipt_picture']) ?>"
   target="_blank" rel="noopener">View receipt</a>
<?php else: ?>—<?php endif; ?>
</td>

<td>
<?php if ($p['notebook_picture']): ?>
<a href="<?= htmlspecialchars($p['notebook_picture']) ?>"
   target="_blank" rel="noopener">View notebook</a>
<?php else: ?>—<?php endif; ?>
</td>

<td><?= htmlspecialchars($p['note'] ?? '') ?></td>
</tr>
<?php endwhile; ?>

<tr>
<th colspan="4">Total Received (<?= $currency ?>)</th>
<th><?= number_format($total, 2) ?></th>
<th colspan="3"></th>
</tr>

</tbody>
</table>
</div>
</div>
<?php
}
?>

<?php showPaymentTable($usdPayments, 'USD'); ?>
<?php showPaymentTable($afnPayments, 'AFN'); ?>

</div>

<script>
const clientSelect   = document.getElementById('client_id');
const invoiceSelect  = document.getElementById('sale_id');
const currencySelect = document.getElementById('currency');
const amountInput    = document.getElementById('amount');

const balancePanel      = document.getElementById('balancePanel');
const balanceLoading    = document.getElementById('balanceLoading');
const balanceClientName = document.getElementById('balanceClientName');
const balanceGrid       = document.getElementById('balanceGrid');
const balanceWarning    = document.getElementById('balanceWarning');
const balanceSuccess    = document.getElementById('balanceSuccess');

let currentBalance = null;

/* ---------- Filter invoices by client + currency ---------- */
function filterInvoices() {
    const clientId = clientSelect.value;
    const currency = currencySelect.value;

    for (const option of invoiceSelect.options) {
        if (!option.value) {
            option.hidden = false;
            continue;
        }
        option.hidden =
            option.dataset.client !== clientId ||
            option.dataset.currency !== currency;
    }

    if (invoiceSelect.selectedOptions[0]?.hidden) {
        invoiceSelect.value = "";
    }
}

/* ---------- Fetch balance via AJAX ---------- */
function fetchBalance() {
    const clientId = clientSelect.value;
    const currency = currencySelect.value;

    if (!clientId || !currency) {
        balancePanel.classList.remove('active');
        currentBalance = null;
        return;
    }

    // Show loading
    balanceLoading.classList.add('active');
    balancePanel.classList.remove('active');
    balanceWarning.classList.remove('active');
    balanceSuccess.classList.remove('active');

    fetch('client_payments.php?ajax_balance=1&client_id=' +
          encodeURIComponent(clientId) +
          '&currency=' + encodeURIComponent(currency))
        .then(r => r.json())
        .then(data => {
            balanceLoading.classList.remove('active');

            if (!data.success) {
                balancePanel.classList.remove('active');
                currentBalance = null;
                return;
            }

            currentBalance = data;
            balanceClientName.textContent = data.client_name + ' — ' + data.currency;
            renderBalance(data, 0);
            balancePanel.classList.add('active');
        })
        .catch(err => {
            balanceLoading.classList.remove('active');
            balancePanel.classList.remove('active');
            console.error(err);
        });
}

/* ---------- Render the balance grid ---------- */
function renderBalance(data, payAmount) {
    const symbol = data.currency === 'USD' ? '$' : 'AFN';
    const invoice  = data.invoice_total;
    const paid     = data.paid_total;
    const unpaid   = data.unpaid_total;
    const remaining = unpaid - payAmount;
    const isOverpaid = remaining < -0.001;

    let html = `
        <div class="balance-item invoiced">
            <span class="label">Total Invoiced</span>
            <span class="value">${invoice.toLocaleString('en-US', {minimumFractionDigits:2})} ${symbol}</span>
        </div>
        <div class="balance-item paid">
            <span class="label">Total Paid</span>
            <span class="value">${paid.toLocaleString('en-US', {minimumFractionDigits:2})} ${symbol}</span>
        </div>
        <div class="balance-item unpaid ${unpaid <= 0.001 ? 'zero' : ''}">
            <span class="label">Unpaid Balance</span>
            <span class="value">${unpaid.toLocaleString('en-US', {minimumFractionDigits:2})} ${symbol}</span>
        </div>
    `;

    if (payAmount > 0) {
        if (isOverpaid) {
            html += `
                <div class="balance-item overpaid">
                    <span class="label">&#9888; Overpaid By</span>
                    <span class="value">${Math.abs(remaining).toLocaleString('en-US', {minimumFractionDigits:2})} ${symbol}</span>
                </div>
            `;
        } else {
            html += `
                <div class="balance-item remaining">
                    <span class="label">&#10004; Remaining After Payment</span>
                    <span class="value">${remaining.toLocaleString('en-US', {minimumFractionDigits:2})} ${symbol}</span>
                </div>
            `;
        }
    }

    balanceGrid.innerHTML = html;

    /* Show warning / success */
    balanceWarning.classList.remove('active');
    balanceSuccess.classList.remove('active');

    if (payAmount > 0) {
        if (isOverpaid) {
            balanceWarning.classList.add('active');
        } else if (Math.abs(remaining) < 0.01 && unpaid > 0.001) {
            balanceSuccess.classList.add('active');
        }
    }
}

/* ---------- Events ---------- */
clientSelect.addEventListener('change', function() {
    filterInvoices();
    fetchBalance();
});

currencySelect.addEventListener('change', function() {
    filterInvoices();
    fetchBalance();
});

amountInput.addEventListener('input', function() {
    if (currentBalance) {
        const val = parseFloat(this.value) || 0;
        renderBalance(currentBalance, val);
    }
});

/* ---------- Auto-select currency from invoice ---------- */
invoiceSelect.addEventListener('change', function() {
    if (!this.value) return;
    const opt = this.options[this.selectedIndex];
    if (opt.dataset.currency) {
        currencySelect.value = opt.dataset.currency;
        fetchBalance();
    }
});

/* ---------- Init ---------- */
filterInvoices();
</script>

</body>
</html>