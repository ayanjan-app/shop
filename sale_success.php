```php
<?php
require_once "db.php";

$message = "";

function uploadPicture($field, $folder) {
    if (!isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Picture upload failed.");
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        throw new Exception("Picture must be smaller than 5 MB.");
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
    $destination = __DIR__ . '/' . $folder . '/' . $name;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new Exception("Could not save the picture.");
    }

    return $folder . '/' . $name;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $invoicePicture = null;

    try {
        $clientId = (int)($_POST["client_id"] ?? 0);
        $invoiceNumber = trim($_POST["invoice_number"] ?? "");
        $invoiceDate = $_POST["invoice_date"] ?? "";
        $currency = $_POST["currency"] ?? "";
        $amount = $_POST["total_amount"] ?? "";
        $note = trim($_POST["note"] ?? "");

        if ($clientId < 1 || $invoiceNumber === "") {
            throw new Exception("Select a client and enter invoice number.");
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
            throw new Exception("Enter a valid invoice date.");
        }

        $date = DateTime::createFromFormat('!Y-m-d', $invoiceDate);
        if (!$date || $date->format('Y-m-d') !== $invoiceDate) {
            throw new Exception("Invalid invoice date.");
        }

        if (!in_array($currency, ["USD", "AFN"], true)) {
            throw new Exception("Select USD or AFN.");
        }

        if (!is_numeric($amount) || !is_finite((float)$amount)
            || (float)$amount <= 0 || (float)$amount > 9999999999999) {
            throw new Exception("Enter a valid invoice amount.");
        }

        $check = $conn->prepare("SELECT id FROM clients WHERE id = ?");
        $check->bind_param("i", $clientId);
        $check->execute();

        if (!$check->get_result()->fetch_assoc()) {
            throw new Exception("Selected client does not exist.");
        }

        if (!is_dir(__DIR__ . "/uploads/invoices")) {
            throw new Exception("Create the uploads/invoices folder first.");
        }

        $invoicePicture = uploadPicture(
            "invoice_picture",
            "uploads/invoices"
        );

        $stmt = $conn->prepare(
            "INSERT INTO sales
            (client_id, invoice_number, invoice_date, currency,
             total_amount, invoice_picture, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $total = (float)$amount;
        $stmt->bind_param(
            "isssdss",
            $clientId,
            $invoiceNumber,
            $invoiceDate,
            $currency,
            $total,
            $invoicePicture,
            $note
        );

        $stmt->execute();
        $message = "Invoice saved successfully.";

    } catch (Throwable $e) {
        if ($invoicePicture) {
            $path = __DIR__ . '/' . $invoicePicture;
            if (is_file($path)) {
                unlink($path);
            }
        }

        $message = $e instanceof mysqli_sql_exception
            ? ($e->getCode() == 1062
                ? "This invoice number already exists."
                : "Database error. Please check your data and database.")
            : $e->getMessage();
    }
}

$clients = $conn->query(
    "SELECT id, full_name FROM clients ORDER BY full_name"
);

$sales = $conn->query(
    "SELECT s.*, c.full_name
     FROM sales s
     JOIN clients c ON c.id = s.client_id
     ORDER BY s.id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Sales Invoices</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:20px}
.container{max-width:1100px;margin:auto}
form,.table-box{background:white;padding:20px;border-radius:8px;margin-bottom:20px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
label{display:block;font-weight:bold;margin-bottom:6px}
input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:4px}
button{padding:11px 20px;background:#1769aa;color:white;border:0;border-radius:4px;cursor:pointer}
table{width:100%;border-collapse:collapse;min-width:750px}
th,td{padding:10px;border:1px solid #ddd;text-align:left}
th{background:#e9eef5}
.table-scroll{overflow-x:auto}
.message{padding:12px;background:#e5f5e8;margin-bottom:15px}
img{max-width:90px;max-height:90px}
@media(max-width:600px){.grid{grid-template-columns:1fr}body{padding:10px}}
</style>
</head>
<body>
<div class="container">
<h1>Client Sales Invoices</h1>
<p><a href="payment.php">Go to Client Payments</a></p>

<?php if ($message !== ""): ?>
<div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<h2>Add New Invoice</h2>

<div class="grid">
<div>
<label>Client</label>
<select name="client_id" required>
<option value="">Select client</option>
<?php while ($c = $clients->fetch_assoc()): ?>
<option value="<?= $c['id'] ?>">
<?= htmlspecialchars($c['full_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<div>
<label>Invoice Number</label>
<input type="text" name="invoice_number" required maxlength="100">
</div>

<div>
<label>Invoice Date</label>
<input type="date" name="invoice_date" required
value="<?= date('Y-m-d') ?>">
</div>

<div>
<label>Currency</label>
<select name="currency" required>
<option value="USD">US Dollar (USD)</option>
<option value="AFN">Afghani (AFN)</option>
</select>
</div>

<div>
<label>Total Amount</label>
<input type="number" name="total_amount" min="0.01"
step="0.01" required>
</div>

<div>
<label>Invoice Picture</label>
<input type="file" name="invoice_picture"
accept="image/jpeg,image/png,image/webp">
</div>
</div>

<p>
<label>Note</label>
<textarea name="note" rows="3"></textarea>
</p>

<button type="submit">Save Invoice</button>
</form>

<div class="table-box">
<h2>All Invoices</h2>
<div class="table-scroll">
<table>
<thead>
<tr>
<th>ID</th><th>Client</th><th>Invoice</th>
<th>Date</th><th>Currency</th><th>Amount</th>
<th>Picture</th><th>Note</th>
</tr>
</thead>
<tbody>
<?php while ($s = $sales->fetch_assoc()): ?>
<tr>
<td><?= $s['id'] ?></td>
<td><?= htmlspecialchars($s['full_name']) ?></td>
<td><?= htmlspecialchars($s['invoice_number']) ?></td>
<td><?= htmlspecialchars($s['invoice_date']) ?></td>
<td><?= htmlspecialchars($s['currency']) ?></td>
<td><?= number_format((float)$s['total_amount'], 2) ?></td>
<td>
<?php if ($s['invoice_picture']): ?>
<a href="<?= htmlspecialchars($s['invoice_picture']) ?>"
target="_blank" rel="noopener">View picture</a>
<?php else: ?>—<?php endif; ?>
</td>
<td><?= htmlspecialchars($s['note'] ?? '') ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</body>
</html>
```