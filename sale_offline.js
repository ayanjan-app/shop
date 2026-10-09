// ============================================================
// sale_offline.js — Vanilla JS version of sale_scripts.js
// No jQuery. No Select2. Works 100% offline.
// NOW WITH EDITABLE PRICE
// ============================================================

let rowCounter = 0;
let currentPage = 1;
let isLoading = false;
let currentSaleId = null;
const salesPerPage = 15;

// ------------------------------------------------------------
// INJECT CSS for editable price + yellow override state
// ------------------------------------------------------------
(function injectPriceStyles() {
    const s = document.createElement('style');
    s.textContent = `
        .price-out {
            background: #fff !important;
            cursor: text !important;
            transition: all 0.15s ease !important;
            font-weight: 600 !important;
        }
        .price-out:focus {
            border-color: #1a2980 !important;
            box-shadow: 0 0 0 2px rgba(26, 41, 128, 0.15) !important;
            outline: none !important;
        }
        .price-out.price-overridden {
            background: #fff8e1 !important;
            border-color: #f39c12 !important;
            font-weight: 700 !important;
            color: #d35400 !important;
        }
    `;
    document.head.appendChild(s);
})();

// ------------------------------------------------------------
// PAGE INIT
// ------------------------------------------------------------
window.addEventListener('DOMContentLoaded', function () {
    buildMedicineDatalist();
    addRow(true);
    const custEl = document.getElementById('customer_name');
    if (custEl) custEl.focus();
    loadSalesHistory(1);
    setupKeyboardShortcuts();
    setupEventListeners();
});

// ------------------------------------------------------------
// BUILD DATALIST (replaces Select2 data source)
// ------------------------------------------------------------
function buildMedicineDatalist() {
    if (document.getElementById('medicineOptionsList')) return;
    const datalist = document.createElement('datalist');
    datalist.id = 'medicineOptionsList';

    (window.medicineOptions || []).forEach(m => {
        const opt = document.createElement('option');
        opt.value = `${m.name} - ${m.gram} - ${m.company} (Stock: ${m.stock})`;
        opt.dataset.id = m.id;
        opt.dataset.name = m.name;
        opt.dataset.gram = m.gram;
        opt.dataset.company = m.company;
        opt.dataset.type = m.type || '';
        opt.dataset.price = m.price;
        opt.dataset.stock = m.stock;
        datalist.appendChild(opt);
    });
    document.body.appendChild(datalist);
}

// ------------------------------------------------------------
// KEYBOARD SHORTCUTS
// ------------------------------------------------------------
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            addRow(true);
            showNotification('New row added', 'success');
        }
        if (e.ctrlKey && e.key === 'm') {
            e.preventDefault();
            removeLastRow();
        }
        if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            const f = document.getElementById('saleForm');
            if (f) f.requestSubmit();
        }
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const s = document.getElementById('searchSales');
            if (s) s.focus();
        }
    });
}

// ------------------------------------------------------------
// NOTIFICATION
// ------------------------------------------------------------
function showNotification(message, type = 'info') {
    const n = document.createElement('div');
    n.className = `notification notification-${type}`;
    n.textContent = message;
    n.style.cssText = `
        position: fixed; top: 20px; right: 20px;
        background: ${type === 'success' ? '#4CAF50' : '#2196F3'};
        color: white; padding: 10px 20px; border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 9999; font-family: sans-serif; font-size: 14px;
    `;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 2000);
}

// ------------------------------------------------------------
// ADD / REMOVE ROWS
// ------------------------------------------------------------
function addRow(shouldFocus = true) {
    const tbody = document.getElementById('medicinesTableBody');
    if (!tbody) return;
    rowCounter++;

    const tr = document.createElement('tr');
    tr.id = 'row_' + rowCounter;

    tr.innerHTML = `
        <td>
            <div class="medicine-select-wrapper">
                <input type="text"
                       list="medicineOptionsList"
                       class="form-select medicine-select"
                       id="medicine_select_${rowCounter}"
                       placeholder="🔍 Search medicine..."
                       autocomplete="off"
                       oninput="handleMedicineSelection(this)"
                       onchange="handleMedicineSelection(this)"
                       style="width:100%; padding:6px;">
                <div class="stock-warning" id="stock_warning_${rowCounter}" style="display:none; color:#e74c3c; font-size:12px; margin-top:2px;">
                    ⚠ Exceeds stock!
                </div>
            </div>
        </td>
        <td>
            <input type="number" class="price-out" step="0.01" min="0"
                   oninput="onPriceChange(this)"
                   onfocus="this.select()"
                   style="text-align:center; width:100%;">
        </td>
        <td>
            <input type="number" class="bonus" value="0" step="0.01" min="0"
                   oninput="updateRow(this)" style="text-align:center; width:100%;">
        </td>
        <td>
            <input type="number" class="qty" value="1" min="0.01" step="0.01"
                   oninput="updateRow(this)" style="text-align:center; width:100%;">
        </td>
        <td>
            <span class="stock-amount">-</span>
        </td>
        <td class="subtotal">0.00</td>
        <td>
            <button type="button" class="btn-remove" onclick="removeRow(this)"
                    style="background:#e74c3c;color:#fff;border:none;padding:5px 8px;border-radius:5px;cursor:pointer;">
                ✕
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    if (shouldFocus) {
        setTimeout(() => {
            const input = document.getElementById('medicine_select_' + rowCounter);
            if (input) input.focus();
        }, 100);
    }
}

function removeLastRow() {
    const tbody = document.getElementById('medicinesTableBody');
    const rows = tbody.querySelectorAll('tr');
    if (rows.length > 1) {
        const lastRow = rows[rows.length - 1];
        const input = lastRow.querySelector('.medicine-select');
        if (input && input.value) {
            clearRow(lastRow);
        } else {
            lastRow.remove();
        }
        updateTotal();
        showNotification('Last item removed', 'success');
    } else if (rows.length === 1) {
        clearRow(rows[0]);
        showNotification('Row cleared', 'info');
    }
}

function removeRow(btn) {
    const tbody = document.getElementById('medicinesTableBody');
    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 1) {
        clearRow(btn.closest('tr'));
    } else {
        btn.closest('tr').remove();
    }
    updateTotal();
    showNotification('Item removed', 'success');
}

function clearRow(row) {
    const input = row.querySelector('.medicine-select');
    if (input) {
        input.value = '';
        delete input.dataset.qtySet;
    }

    const priceInput = row.querySelector('.price-out');
    priceInput.value = '';
    priceInput.classList.remove('price-overridden');
    priceInput.dataset.defaultPrice = '';
    priceInput.title = '';

    row.querySelector('.stock-amount').textContent = '-';
    row.querySelector('.subtotal').textContent = '0.00';
    row.querySelector('.qty').value = '1';
    row.querySelector('.bonus').value = '0';

    const warning = row.querySelector('.stock-warning');
    if (warning) warning.style.display = 'none';

    delete row.dataset.medicineId;
}

function clearAllRows() {
    const tbody = document.getElementById('medicinesTableBody');
    if (!tbody || tbody.children.length === 0) return;
    if (confirm('Clear all items?')) {
        tbody.innerHTML = '';
        addRow(false);
        updateTotal();
        showNotification('All items cleared', 'info');
    }
}

// ------------------------------------------------------------
// MEDICINE SELECTION
// ------------------------------------------------------------
function handleMedicineSelection(el) {
    const tr = el.closest('tr');
    const value = el.value;

    const opt = Array.from(document.querySelectorAll('#medicineOptionsList option'))
        .find(o => o.value === value);

    if (opt) {
        tr.dataset.medicineId = opt.dataset.id;
    } else {
        delete tr.dataset.medicineId;
    }

    updateRow(el);

    if (opt) {
        const tbody = document.getElementById('medicinesTableBody');
        const rows = tbody.querySelectorAll('tr');
        const isLastRow = tr === rows[rows.length - 1];
        if (isLastRow) addRow(false);
    }
}

// ------------------------------------------------------------
// NEW: Handle manual price override
// ------------------------------------------------------------
function onPriceChange(input) {
    const tr = input.closest('tr');
    if (!tr) return;

    const medInput = tr.querySelector('.medicine-select');
    const value = medInput ? medInput.value : '';

    if (!value) {
        updateTotal();
        return;
    }

    const opt = Array.from(document.querySelectorAll('#medicineOptionsList option'))
        .find(o => o.value === value);

    if (!opt) {
        updateTotal();
        return;
    }

    const defaultPrice = parseFloat(opt.dataset.price) || 0;
    const currentPrice = parseFloat(input.value) || 0;

    if (Math.abs(currentPrice - defaultPrice) > 0.001) {
        input.classList.add('price-overridden');
        input.title = 'Custom price (default: $' + defaultPrice.toFixed(2) + ')';
    } else {
        input.classList.remove('price-overridden');
        input.title = '';
    }

    // Recalculate subtotal for this row
    const qty = parseFloat(tr.querySelector('.qty').value) || 0;
    const bonus = parseFloat(tr.querySelector('.bonus').value) || 0;
    const subtotal = qty * (currentPrice - bonus);
    tr.querySelector('.subtotal').textContent = subtotal.toFixed(2);

    updateTotal();
}

// ------------------------------------------------------------
// UPDATE ROW
// ------------------------------------------------------------
function updateRow(el) {
    const tr = el.closest('tr');
    const input = tr.querySelector('.medicine-select');
    const value = input.value;

    if (value) {
        const opt = Array.from(document.querySelectorAll('#medicineOptionsList option'))
            .find(o => o.value === value);

        if (opt) {
            const priceOut = parseFloat(opt.dataset.price) || 0;
            const stock = parseInt(opt.dataset.stock) || 0;

            const qtyInput = tr.querySelector('.qty');
            const bonusInput = tr.querySelector('.bonus');
            const priceInput = tr.querySelector('.price-out');
            const stockSpan = tr.querySelector('.stock-amount');
            const warning = tr.querySelector('.stock-warning');

            // === EDITABLE PRICE LOGIC ===
            // Only set price from DB if user hasn't overridden it
            const isOverridden = priceInput.classList.contains('price-overridden');
            if (!isOverridden || !priceInput.value) {
                priceInput.value = priceOut.toFixed(2);
                priceInput.dataset.defaultPrice = priceOut;
            }

            stockSpan.textContent = stock;
            qtyInput.max = stock;

            if (el.classList.contains('medicine-select') && !el.dataset.qtySet) {
                if (stock <= 0) {
                    qtyInput.value = 0;
                    warning.style.display = 'block';
                    warning.innerHTML = '⚠ Out of stock!';
                    qtyInput.style.borderColor = '#e74c3c';
                } else {
                    qtyInput.value = Math.min(1, stock);
                    warning.style.display = 'none';
                    qtyInput.style.borderColor = '';
                }
                el.dataset.qtySet = '1';
            }

            const qty = parseFloat(qtyInput.value) || 0;
            const bonus = parseFloat(bonusInput.value) || 0;

            if (qty > stock) {
                warning.style.display = 'block';
                warning.innerHTML = `⚠ Exceeds stock! Available: ${stock}`;
                qtyInput.style.borderColor = '#e74c3c';
            } else if (qty > 0) {
                warning.style.display = 'none';
                qtyInput.style.borderColor = '';
            }

            // === USE CURRENT PRICE (may be edited) ===
            const currentPrice = parseFloat(priceInput.value) || 0;
            const subtotal = qty * (currentPrice - bonus);
            tr.querySelector('.subtotal').textContent = subtotal.toFixed(2);
        }
    } else {
        // Clear fields when input emptied
        const priceInput = tr.querySelector('.price-out');
        priceInput.value = '';
        priceInput.classList.remove('price-overridden');
        priceInput.dataset.defaultPrice = '';
        priceInput.title = '';

        tr.querySelector('.stock-amount').textContent = '-';
        tr.querySelector('.subtotal').textContent = '0.00';
        tr.querySelector('.qty').value = '1';
        tr.querySelector('.bonus').value = '0';
        tr.querySelector('.qty').style.borderColor = '';
        delete input.dataset.qtySet;
        const warning = tr.querySelector('.stock-warning');
        if (warning) warning.style.display = 'none';
    }

    updateTotal();
}

// ------------------------------------------------------------
// TOTALS
// ------------------------------------------------------------
function updateTotal() {
    let medicinesTotal = 0;
    document.querySelectorAll('.subtotal').forEach(s => {
        medicinesTotal += parseFloat(s.textContent) || 0;
    });

    const doctorFee = parseFloat(document.getElementById('doctor_fee').value) || 0;
    const operationFee = parseFloat(document.getElementById('operation_fee').value) || 0;
    const grandTotal = medicinesTotal + doctorFee + operationFee;

    document.getElementById('totalSale').textContent = grandTotal.toFixed(2);
    document.getElementById('totalAmountInput').value = grandTotal.toFixed(2);
    calculateBalance();
}

function calculateBalance() {
    const total = parseFloat(document.getElementById('totalSale').textContent) || 0;
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const balance = total - paid;
    document.getElementById('balance').textContent = balance.toFixed(2);
    document.getElementById('isUnpaidInput').value = balance > 0 ? '1' : '0';
}

// ------------------------------------------------------------
// FORM SUBMIT
// ------------------------------------------------------------
function setupEventListeners() {
    const form = document.getElementById('saleForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        const customerName = (document.getElementById('customer_name').value || '').trim();
        if (!customerName) {
            alert('Please enter customer name');
            document.getElementById('customer_name').focus();
            e.preventDefault();
            return false;
        }

        const items = [];
        let allValid = true;

        document.querySelectorAll('#medicinesTableBody tr').forEach(tr => {
            const medId = tr.dataset.medicineId;
            const medInput = tr.querySelector('.medicine-select');
            if (!medInput || !medInput.value || !medId) return;

            const qty = parseFloat(tr.querySelector('.qty').value) || 0;
            const price = parseFloat(tr.querySelector('.price-out').value) || 0;  // ← edited price
            const bonus = parseFloat(tr.querySelector('.bonus').value) || 0;
            const stock = parseInt(tr.querySelector('.stock-amount').textContent) || 0;

            if (qty > 0) {
                if (qty > stock) {
                    alert('Quantity exceeds stock for one or more items');
                    allValid = false;
                    return;
                }
                items.push({
                    medicine_id: medId,
                    quantity: qty,
                    price_out: price,
                    bonus_or_discount: bonus
                });
            }
        });

        const doctorFee = parseFloat(document.getElementById('doctor_fee').value) || 0;
        const operationFee = parseFloat(document.getElementById('operation_fee').value) || 0;

        if (items.length === 0 && doctorFee === 0 && operationFee === 0) {
            alert('Please add at least one medicine or fee');
            e.preventDefault();
            return false;
        }

        if (!allValid) {
            e.preventDefault();
            return false;
        }

        document.getElementById('itemsInput').value = JSON.stringify(items);
        showNotification('Saving sale...', 'info');
    });

    ['doctor_fee', 'operation_fee'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateTotal);
    });

    const paidEl = document.getElementById('paid_amount');
    if (paidEl) paidEl.addEventListener('input', calculateBalance);

    const searchEl = document.getElementById('searchSales');
    if (searchEl) {
        let t = null;
        searchEl.addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(() => loadSalesHistory(1), 300);
        });
    }

    const filterEl = document.getElementById('filterStatus');
    if (filterEl) {
        filterEl.addEventListener('change', () => loadSalesHistory(1));
    }

    const refreshBtn = document.getElementById('refreshSales');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => loadSalesHistory(currentPage));
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closePaymentModal();
    });

    setInterval(() => {
        if (!isLoading) loadSalesHistory(currentPage);
    }, 30000);
}

// ------------------------------------------------------------
// SALES HISTORY
// ------------------------------------------------------------
function loadSalesHistory(page = 1) {
    if (isLoading) return;
    isLoading = true;
    currentPage = page;

    const search = document.getElementById('searchSales')?.value || '';
    const status = document.getElementById('filterStatus')?.value || 'all';

    const tbody = document.getElementById('salesHistoryBody');
    const loadingDiv = document.getElementById('salesLoading');
    const emptyDiv = document.getElementById('salesEmpty');

    if (loadingDiv) loadingDiv.style.display = 'flex';
    if (emptyDiv) emptyDiv.style.display = 'none';
    if (tbody) tbody.innerHTML = '';

    const formData = new FormData();
    formData.append('page', page);
    formData.append('per_page', salesPerPage);
    formData.append('search', search);
    formData.append('status', status);
    formData.append('period', 'all');

    fetch('fetch_sales.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            isLoading = false;
            if (loadingDiv) loadingDiv.style.display = 'none';

            if (data.success && data.sales) {
                displaySales(data.sales);
                updateStats(data.sales);
                setupPagination(data.totalPages, page);
                if (data.sales.length === 0 && emptyDiv) {
                    emptyDiv.style.display = 'block';
                }
            } else if (emptyDiv) {
                emptyDiv.style.display = 'block';
            }
        })
        .catch(err => {
            isLoading = false;
            if (loadingDiv) loadingDiv.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            console.error('Error:', err);
        });
}

function displaySales(sales) {
    const tbody = document.getElementById('salesHistoryBody');
    if (!tbody || !sales || sales.length === 0) return;

    let html = '';
    sales.forEach(sale => {
        const saleDate = new Date(sale.sale_date);
        const formattedDate = saleDate.toLocaleDateString() + ' ' +
            saleDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const isUnpaid = parseFloat(sale.balance) > 0;
        const statusClass = isUnpaid ? 'status-unpaid' : 'status-completed';
        const statusText = isUnpaid ? 'UNPAID' : 'DONE';

        html += `
            <tr>
                <td>${formattedDate}</td>
                <td>${escapeHtml(sale.customer_name || 'N/A')}</td>
                <td>${sale.items_count || 0}</td>
                <td>$${parseFloat(sale.total_amount || 0).toFixed(2)}</td>
                <td>$${parseFloat(sale.paid_amount || 0).toFixed(2)}</td>
                <td>$${parseFloat(sale.balance || 0).toFixed(2)}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>
                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                        <button class="btn-sm" onclick="viewSaleDetails(${sale.id})" title="View">👁</button>
                        ${isUnpaid ? `<button class="btn-sm btn-success" onclick="openPaymentModal(${sale.id})" title="Pay">💵</button>` : ''}
                        <button class="btn-sm btn-warning" onclick="updateSale(${sale.id})" title="Update">✏️</button>
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function updateStats(sales) {
    let totalAmount = 0, completedCount = 0, unpaidCount = 0;
    sales.forEach(s => {
        totalAmount += parseFloat(s.total_amount || 0);
        if (parseFloat(s.balance) > 0) unpaidCount++;
        else completedCount++;
    });
    const setTxt = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    setTxt('statTotal', sales.length);
    setTxt('statCompleted', completedCount);
    setTxt('statUnpaid', unpaidCount);
    setTxt('statAmount', totalAmount.toFixed(2));
}

function setupPagination(totalPages, curPage) {
    const pag = document.getElementById('salesPagination');
    if (!pag) return;
    if (!totalPages || totalPages <= 1) {
        pag.style.display = 'none';
        return;
    }
    pag.style.display = 'flex';
    let html = '';
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= curPage - 2 && i <= curPage + 2)) {
            html += `<button onclick="loadSalesHistory(${i})" ${i === curPage ? 'class="active"' : ''}>${i}</button>`;
        } else if (i === curPage - 3 || i === curPage + 3) {
            html += `<button disabled>...</button>`;
        }
    }
    pag.innerHTML = html;
}

function viewSaleDetails(saleId) {
    if (saleId) window.open(`view_sale.php?id=${saleId}`, '_blank');
}

function updateSale(saleId) {
    if (saleId) window.open(`update_sale.php?id=${saleId}`, '_blank');
}

// ------------------------------------------------------------
// PAYMENT MODAL
// ------------------------------------------------------------
function openPaymentModal(saleId) {
    currentSaleId = saleId;
    document.getElementById('paymentSaleId').textContent = saleId;
    document.getElementById('paymentModal').style.display = 'flex';
    document.getElementById('paymentAmount').focus();
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    currentSaleId = null;
}

function submitPayment() {
    const amount = document.getElementById('paymentAmount').value;
    const method = document.getElementById('paymentMethod').value;

    if (!amount || parseFloat(amount) <= 0) {
        alert('Please enter a valid amount');
        return;
    }

    const formData = new FormData();
    formData.append('sale_id', currentSaleId);
    formData.append('payment_amount', amount);
    formData.append('payment_method', method);

    fetch('add_payment.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Payment recorded successfully');
                closePaymentModal();
                loadSalesHistory(currentPage);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error recording payment');
        });
}

// ------------------------------------------------------------
// HELPERS
// ------------------------------------------------------------
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}