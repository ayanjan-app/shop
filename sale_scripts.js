// sale_scripts.js - Optimized for Quick Sales (with Editable Price)

// Initialize on page load
window.onload = function() {
    addRow(true); // Add first row
    document.getElementById('customer_name').focus();
    loadSalesHistory(1);
    setupKeyboardShortcuts();
};

// Setup keyboard shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+B: Add Medicine
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            addRow(true);
            showNotification('New row added', 'success');
        }
        
        // Ctrl+N: Remove Last Medicine
        if (e.ctrlKey && e.key === 'm') {
            e.preventDefault();
            removeLastRow();
        }
        
        // Ctrl+S: Save Sale
        if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            document.getElementById('saleForm').requestSubmit();
        }
        
        // Ctrl+F: Focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchSales').focus();
        }
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#4CAF50' : '#2196F3'};
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 2000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }

    /* ===== Editable Price Field ===== */
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
document.head.appendChild(style);

// Remove last row
function removeLastRow() {
    const tbody = document.getElementById('medicinesTableBody');
    const rows = tbody.querySelectorAll('tr');
    
    if (rows.length > 1) {
        const lastRow = rows[rows.length - 1];
        const select = lastRow.querySelector('.medicine-select');
        
        if (select && select.value) {
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

// Clear a specific row
function clearRow(row) {
    const select = row.querySelector('.medicine-select');
    if (select) {
        $(select).val('').trigger('change');
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
}

// Add medicine row
function addRow(shouldFocus = true) {
    const tbody = document.getElementById('medicinesTableBody');
    const tr = document.createElement('tr');
    rowCounter++;
    tr.id = 'row_' + rowCounter;
    
    tr.innerHTML = `
        <td>
            <div class="medicine-select-wrapper">
                <select class="form-select medicine-select" id="medicine_select_${rowCounter}" 
                        onchange="handleMedicineSelection(this)" style="width: 100%;">
                    <option value="">Search medicine...</option>
                </select>
                <div class="stock-warning" id="stock_warning_${rowCounter}" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> Exceeds stock!
                </div>
            </div>
        </td>
        <td>
            <input type="number" class="price-out" step="0.01" min="0"
                   oninput="onPriceChange(this)"
                   onfocus="this.select()"
                   style="text-align: center; width: 90px;">
        </td>
        <td>
            <input type="number" class="bonus" value="0" step="0.01" min="0" 
                   oninput="updateRow(this)" style="text-align: center;">
        </td>
        <td>
            <input type="number" class="qty" value="1" min="0.01" step="0.01"
                   oninput="updateRow(this)" style="text-align: center;">
        </td>
        <td>
            <span class="stock-amount">-</span>
        </td>
        <td class="subtotal">0.00</td>
        <td>
            <button type="button" class="btn-remove" onclick="removeRow(this)">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(tr);
    initializeSelect2ForRow(rowCounter);
    
    if (shouldFocus) {
        setTimeout(() => {
            $(`#medicine_select_${rowCounter}`).select2('open');
        }, 100);
    }
}

// Initialize Select2
function initializeSelect2ForRow(rowId) {
    const selectElement = $(`#medicine_select_${rowId}`);
    
    selectElement.select2({
        data: medicineOptions,
        placeholder: '🔍 Search medicine...',
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true,
        matcher: function(params, data) {
            if ($.trim(params.term) === '') return data;
            
            const searchTerm = params.term.toLowerCase();
            const medicineData = medicineOptions.find(m => m.id == data.id);
            
            if (medicineData) {
                if (medicineData.name.toLowerCase().includes(searchTerm) ||
                    medicineData.company.toLowerCase().includes(searchTerm) ||
                    medicineData.gram.toLowerCase().includes(searchTerm)) {
                    return data;
                }
            }
            return null;
        },
        templateResult: formatMedicineResult,
        templateSelection: formatMedicineSelection
    });
    
    selectElement.on('change', function() {
        updateRow(this);
    });
}

// Format medicine result
function formatMedicineResult(medicine) {
    if (!medicine.id) return medicine.text;
    
    const medData = medicineOptions.find(m => m.id == medicine.id);
    if (!medData) return medicine.text;
    
    return $(`
        <div style="display: flex; justify-content: space-between; padding: 3px;">
            <div>
                <strong>${medData.name}</strong>
                <div style="font-size: 0.75em; color: #666;">
                    ${medData.gram} | ${medData.company}
                </div>
            </div>
            <div style="text-align: right;">
                <span style="color: #27ae60;">$${medData.price.toFixed(2)}</span>
                <div style="font-size: 0.7em; color: ${medData.stock > 0 ? '#3498db' : '#e74c3c'};">
                    Stock: ${medData.stock}
                </div>
            </div>
        </div>
    `);
}

// Format medicine selection
function formatMedicineSelection(medicine) {
    if (!medicine.id) return medicine.text;
    
    const medData = medicineOptions.find(m => m.id == medicine.id);
    if (!medData) return medicine.text;
    
    return `${medData.name} - ${medData.gram} (${medData.stock})`;
}

// Handle medicine selection
function handleMedicineSelection(selectElement) {
    updateRow(selectElement);
    
    const selectedValue = selectElement.value;
    if (selectedValue && selectedValue !== '') {
        const tbody = document.getElementById('medicinesTableBody');
        const rows = tbody.querySelectorAll('tr');
        const currentRow = selectElement.closest('tr');
        const isLastRow = currentRow === rows[rows.length - 1];
        
        if (isLastRow) {
            addRow(false);
        }
    }
}

// ============================================================
// NEW: Handle manual price override by user
// ============================================================
function onPriceChange(input) {
    const tr = input.closest('tr');
    if (!tr) return;

    const select = tr.querySelector('.medicine-select');
    const selectedValue = select ? select.value : '';

    if (!selectedValue) {
        updateTotal();
        return;
    }

    const medicineData = medicineOptions.find(m => m.id == selectedValue);
    if (!medicineData) {
        updateTotal();
        return;
    }

    const defaultPrice = parseFloat(medicineData.price) || 0;
    const currentPrice = parseFloat(input.value) || 0;

    // Visual hint: yellow = overridden
    if (Math.abs(currentPrice - defaultPrice) > 0.001) {
        input.classList.add('price-overridden');
        input.title = 'Custom price (default: $' + defaultPrice.toFixed(2) + ')';
    } else {
        input.classList.remove('price-overridden');
        input.title = '';
    }

    // Recalculate this row's subtotal
    const qty = parseFloat(tr.querySelector('.qty').value) || 0;
    const bonus = parseFloat(tr.querySelector('.bonus').value) || 0;
    const subtotal = qty * (currentPrice - bonus);
    tr.querySelector('.subtotal').textContent = subtotal.toFixed(2);

    updateTotal();
}

// Update row calculations
function updateRow(el) {
    const tr = el.closest('tr');
    const select = tr.querySelector('.medicine-select');
    const selectedValue = select.value;
    
    if (selectedValue && selectedValue !== '') {
        const medicineData = medicineOptions.find(m => m.id == selectedValue);
        
        if (medicineData) {
            const priceOut = medicineData.price || 0;
            const stock = medicineData.stock || 0;
            
            const qtyInput = tr.querySelector('.qty');
            const bonusInput = tr.querySelector('.bonus');
            const priceInput = tr.querySelector('.price-out');
            const stockSpan = tr.querySelector('.stock-amount');
            const warning = tr.querySelector('.stock-warning');
            
            // Only set price from DB if user hasn't overridden it
            const isOverridden = priceInput.classList.contains('price-overridden');
            if (!isOverridden || !priceInput.value) {
                priceInput.value = priceOut.toFixed(2);
                priceInput.dataset.defaultPrice = priceOut;
            }
            
            stockSpan.textContent = stock;
            qtyInput.max = stock;
            
            if (el.classList.contains('medicine-select')) {
                qtyInput.value = Math.min(1, stock);
            }
            
            const qty = parseFloat(qtyInput.value) || 0;
            const bonus = parseFloat(bonusInput.value) || 0;
            
            // Check stock
            if (qty > stock) {
                warning.style.display = 'block';
                qtyInput.style.borderColor = 'var(--danger-color)';
            } else {
                warning.style.display = 'none';
                qtyInput.style.borderColor = '';
            }
            
            // Use CURRENT price input (may be edited)
            const currentPrice = parseFloat(priceInput.value) || 0;
            const subtotal = qty * (currentPrice - bonus);
            tr.querySelector('.subtotal').textContent = subtotal.toFixed(2);
        }
    } else {
        // Clear row
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
        
        const warning = tr.querySelector('.stock-warning');
        if (warning) warning.style.display = 'none';
    }
    
    updateTotal();
}

// Remove row
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

// Clear all rows
function clearAllRows() {
    const tbody = document.getElementById('medicinesTableBody');
    if (tbody.children.length === 0) return;
    
    if (confirm('Clear all items?')) {
        tbody.innerHTML = '';
        addRow(false);
        updateTotal();
        showNotification('All items cleared', 'info');
    }
}

// Update total calculations
function updateTotal() {
    const subtotals = document.querySelectorAll('.subtotal');
    let medicinesTotal = 0;
    subtotals.forEach(s => medicinesTotal += parseFloat(s.textContent) || 0);
    
    const doctorFee = parseFloat(document.getElementById('doctor_fee').value) || 0;
    const operationFee = parseFloat(document.getElementById('operation_fee').value) || 0;
    const grandTotal = medicinesTotal + doctorFee + operationFee;

    document.getElementById('totalSale').textContent = grandTotal.toFixed(2);
    document.getElementById('totalAmountInput').value = grandTotal.toFixed(2);
    
    calculateBalance();
}

// Calculate balance
function calculateBalance() {
    const total = parseFloat(document.getElementById('totalSale').textContent) || 0;
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const balance = total - paid;
    
    document.getElementById('balance').textContent = balance.toFixed(2);
    document.getElementById('balance').value = balance.toFixed(2);
    document.getElementById('isUnpaidInput').value = balance > 0 ? '1' : '0';
}

// Form validation
function validateForm() {
    const customerName = document.getElementById('customer_name').value.trim();
    if (!customerName) {
        alert('Please enter customer name');
        document.getElementById('customer_name').focus();
        return false;
    }

    const rows = document.querySelectorAll('#medicinesTableBody tr');
    let hasValidMedicine = false;
    
    rows.forEach(row => {
        const select = row.querySelector('.medicine-select');
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        
        if (select.value && qty > 0) {
            hasValidMedicine = true;
        }
    });
    
    const doctorFee = parseFloat(document.getElementById('doctor_fee').value) || 0;
    const operationFee = parseFloat(document.getElementById('operation_fee').value) || 0;
    
    if (!hasValidMedicine && doctorFee === 0 && operationFee === 0) {
        alert('Please add at least one medicine or fee');
        return false;
    }
    
    return true;
}

// Form submission
document.getElementById('saleForm').addEventListener('submit', function(e) {
    if (!validateForm()) {
        e.preventDefault();
        return false;
    }
    
    const items = [];
    let allValid = true;
    
    document.querySelectorAll('#medicinesTableBody tr').forEach(tr => {
        const medId = tr.querySelector('.medicine-select').value;
        const qty = parseFloat(tr.querySelector('.qty').value) || 0;
        const price = parseFloat(tr.querySelector('.price-out').value) || 0;
        const bonus = parseFloat(tr.querySelector('.bonus').value) || 0;
        const stock = parseInt(tr.querySelector('.stock-amount').textContent) || 0;
        
        if (medId && qty > 0) {
            if (qty > stock) {
                alert('Quantity exceeds stock for one or more items');
                allValid = false;
                return;
            }
            
            items.push({
                medicine_id: medId,
                quantity: qty,
                price_out: price,          // ← edited price is sent
                bonus_or_discount: bonus
            });
        }
    });
    
    if (!allValid) {
        e.preventDefault();
        return false;
    }
    
    document.getElementById('itemsInput').value = JSON.stringify(items);
    
    showNotification('Saving sale...', 'info');
});

// Load sales history
function loadSalesHistory(page = 1) {
    if (isLoading) return;
    
    isLoading = true;
    currentPage = page;
    
    const search = document.getElementById('searchSales')?.value || '';
    const status = document.getElementById('filterStatus')?.value || 'all';
    
    const tbody = document.getElementById('salesHistoryBody');
    const loadingDiv = document.getElementById('salesLoading');
    const emptyDiv = document.getElementById('salesEmpty');
    
    loadingDiv.style.display = 'flex';
    emptyDiv.style.display = 'none';
    tbody.innerHTML = '';
    
    const formData = new FormData();
    formData.append('page', page);
    formData.append('per_page', salesPerPage);
    formData.append('search', search);
    formData.append('status', status);
    formData.append('period', 'all');
    
    fetch('fetch_sales.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        isLoading = false;
        loadingDiv.style.display = 'none';
        
        if (data.success && data.sales) {
            displaySales(data.sales);
            updateStats(data.sales);
            setupPagination(data.totalPages, page);
            
            if (data.sales.length === 0) {
                emptyDiv.style.display = 'block';
            }
        }
    })
    .catch(error => {
        isLoading = false;
        loadingDiv.style.display = 'none';
        emptyDiv.style.display = 'block';
        console.error('Error:', error);
    });
}

// Display sales
function displaySales(sales) {
    const tbody = document.getElementById('salesHistoryBody');
    if (!tbody || sales.length === 0) return;
    
    let html = '';
    sales.forEach(sale => {
        const saleDate = new Date(sale.sale_date);
        const formattedDate = saleDate.toLocaleDateString() + ' ' + 
                             saleDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
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
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                        <button class="btn-sm" onclick="viewSaleDetails(${sale.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${isUnpaid ? `
                        <button class="btn-sm btn-success" onclick="openPaymentModal(${sale.id})" title="Add Payment">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                        ` : ''}
                        <button class="btn-sm btn-warning" onclick="updateSale(${sale.id})" title="Update/Return Items" style="color: #ff9800;">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Update stats
function updateStats(sales) {
    let totalAmount = 0;
    let completedCount = 0;
    let unpaidCount = 0;
    
    sales.forEach(sale => {
        totalAmount += parseFloat(sale.total_amount || 0);
        if (parseFloat(sale.balance) > 0) {
            unpaidCount++;
        } else {
            completedCount++;
        }
    });
    
    document.getElementById('statTotal').textContent = sales.length;
    document.getElementById('statCompleted').textContent = completedCount;
    document.getElementById('statUnpaid').textContent = unpaidCount;
    document.getElementById('statAmount').textContent = totalAmount.toFixed(2);
}

// Setup pagination
function setupPagination(totalPages, currentPage) {
    const paginationDiv = document.getElementById('salesPagination');
    
    if (!paginationDiv || totalPages <= 1) {
        paginationDiv.style.display = 'none';
        return;
    }
    
    paginationDiv.style.display = 'flex';
    let html = '';
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button onclick="loadSalesHistory(${i})" ${i === currentPage ? 'class="active"' : ''}>${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += `<button disabled>...</button>`;
        }
    }
    
    paginationDiv.innerHTML = html;
}

// View sale details
function viewSaleDetails(saleId) {
    if (saleId) {
        window.open(`view_sale.php?id=${saleId}`, '_blank');
    }
}

// Payment modal functions
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
    
    if(!amount || parseFloat(amount) <= 0) {
        alert('Please enter a valid amount');
        return;
    }
    
    const formData = new FormData();
    formData.append('sale_id', currentSaleId);
    formData.append('payment_amount', amount);
    formData.append('payment_method', method);
    
    fetch('add_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('Payment recorded successfully');
            closePaymentModal();
            loadSalesHistory(currentPage);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error recording payment');
    });
}

// Helper functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    let searchTimer;
    document.getElementById('searchSales').addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadSalesHistory(1), 300);
    });
    
    document.getElementById('filterStatus').addEventListener('change', function() {
        loadSalesHistory(1);
    });
    
    document.getElementById('refreshSales').addEventListener('click', function() {
        loadSalesHistory(currentPage);
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePaymentModal();
        }
    });
    
    setInterval(() => {
        if (!isLoading) {
            loadSalesHistory(currentPage);
        }
    }, 30000);
});

// Add this function for update sale
function updateSale(saleId) {
    if (saleId && saleId !== 'null' && saleId !== null) {
        window.open(`update_sale.php?id=${saleId}`, '_blank');
    } else {
        alert('Invalid sale ID. Please try again.');
    }
}

// Enhanced medicine search function with multiple fields
function initializeSelect2ForRow(rowId) {
    const selectElement = $(`#medicine_select_${rowId}`);
    
    selectElement.select2({
        data: medicineOptions,
        placeholder: '🔍 Search by name, company, gram, or type...',
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true,
        matcher: function(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }
            
            const searchTerm = params.term.toLowerCase().trim();
            const medicineData = medicineOptions.find(m => m.id == data.id);
            
            if (medicineData) {
                const nameMatch = medicineData.name.toLowerCase().includes(searchTerm);
                const companyMatch = medicineData.company.toLowerCase().includes(searchTerm);
                const gramMatch = medicineData.gram.toLowerCase().includes(searchTerm);
                const typeMatch = medicineData.type ? medicineData.type.toLowerCase().includes(searchTerm) : false;
                
                const combinedText = `${medicineData.name} ${medicineData.gram} ${medicineData.company} ${medicineData.type || ''}`.toLowerCase();
                const combinedMatch = combinedText.includes(searchTerm);
                
                if (nameMatch || companyMatch || gramMatch || typeMatch || combinedMatch) {
                    return data;
                }
            }
            return null;
        },
        templateResult: formatMedicineResultEnhanced,
        templateSelection: formatMedicineSelectionEnhanced
    });
    
    selectElement.on('change', function() {
        updateRow(this);
    });
}

// Enhanced format for medicine results in dropdown
function formatMedicineResultEnhanced(medicine) {
    if (!medicine.id) return medicine.text;
    
    const medData = medicineOptions.find(m => m.id == medicine.id);
    if (!medData) return medicine.text;
    
    const $container = $(`
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 5px;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <strong style="font-size: 0.9rem; color: #2c3e50;">${escapeHtml(medData.name)}</strong>
                    <span style="background: #e3f2fd; padding: 2px 6px; border-radius: 12px; font-size: 0.7rem; color: #1976d2;">
                        ${escapeHtml(medData.gram)}
                    </span>
                    <span style="background: #f3e5f5; padding: 2px 6px; border-radius: 12px; font-size: 0.7rem; color: #7b1fa2;">
                        ${escapeHtml(medData.type)}
                    </span>
                </div>
                <div style="font-size: 0.75rem; color: #666; margin-top: 3px;">
                    <i class="fas fa-building"></i> ${escapeHtml(medData.company)}
                </div>
            </div>
            <div style="text-align: right; min-width: 80px;">
                <div style="color: #27ae60; font-weight: bold; font-size: 0.85rem;">
                    $${medData.price.toFixed(2)}
                </div>
                <div style="font-size: 0.7rem; color: ${medData.stock > 0 ? '#3498db' : '#e74c3c'};">
                    <i class="fas fa-boxes"></i> Stock: ${medData.stock}
                </div>
            </div>
        </div>
    `);
    
    return $container;
}

// Enhanced format for selected medicine display
function formatMedicineSelectionEnhanced(medicine) {
    if (!medicine.id) return medicine.text;
    
    const medData = medicineOptions.find(m => m.id == medicine.id);
    if (!medData) return medicine.text;
    
    return $(`
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <span>
                <strong>${escapeHtml(medData.name)}</strong>
                <span style="font-size: 0.7rem; color: #666; margin-left: 5px;">
                    (${escapeHtml(medData.gram)} | ${escapeHtml(medData.company)})
                </span>
            </span>
            <span style="font-size: 0.7rem; color: ${medData.stock > 0 ? '#27ae60' : '#e74c3c'};">
                Stock: ${medData.stock}
            </span>
        </div>
    `);
}

// Helper function to clear row fields
function clearRowFields(tr) {
    const priceInput = tr.querySelector('.price-out');
    priceInput.value = '';
    priceInput.classList.remove('price-overridden');
    priceInput.dataset.defaultPrice = '';
    priceInput.title = '';

    tr.querySelector('.stock-amount').textContent = '-';
    tr.querySelector('.subtotal').textContent = '0.00';
    
    const qtyInput = tr.querySelector('.qty');
    qtyInput.value = '1';
    qtyInput.max = '';
    qtyInput.style.borderColor = '';
    
    const bonusInput = tr.querySelector('.bonus');
    bonusInput.value = '0';
    
    const warning = tr.querySelector('.stock-warning');
    if (warning) {
        warning.style.display = 'none';
    }
    
    const select = tr.querySelector('.medicine-select');
    if (select) {
        select.style.borderColor = '';
    }
}

// Add this function to update medicine data globally (if needed)
function refreshMedicineData() {
    fetch('get_medicines.php')
        .then(response => response.json())
        .then(data => {
            medicineOptions.length = 0;
            data.forEach(m => {
                medicineOptions.push({
                    id: m.id,
                    text: `${m.name} - ${m.gram} - ${m.company_name} (Stock: ${m.amount})`,
                    name: m.name,
                    gram: m.gram,
                    type: m.type,
                    company: m.company_name,
                    price: parseFloat(m.price_out),
                    stock: parseInt(m.amount)
                });
            });
            
            document.querySelectorAll('.medicine-select').forEach(select => {
                const rowId = select.id.split('_')[2];
                if (rowId) {
                    $(select).select2('destroy');
                    initializeSelect2ForRow(rowId);
                }
            });
            
            showNotification('Medicine list updated', 'success');
        })
        .catch(error => console.error('Error refreshing medicines:', error));
}

// Add search hint feature
function addSearchHint() {
    const styleEl = document.createElement('style');
    styleEl.textContent = `
        .select2-container--default .select2-results__option {
            padding: 0;
        }
        .select2-container--default .select2-search__field {
            font-size: 0.85rem;
            padding: 8px !important;
        }
        .select2-container--default .select2-search__field::placeholder {
            color: #999;
            font-style: italic;
        }
        .medicine-search-tip {
            padding: 8px 12px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e7ff;
            font-size: 0.75rem;
            color: #666;
            text-align: center;
        }
        .medicine-search-tip i {
            margin-right: 5px;
            color: var(--secondary-color);
        }
    `;
    document.head.appendChild(styleEl);
}

// Initialize search hint on page load
document.addEventListener('DOMContentLoaded', function() {
    addSearchHint();
    
    $(document).on('select2:open', () => {
        setTimeout(() => {
            const searchField = document.querySelector('.select2-search__field');
            if (searchField && !document.querySelector('.medicine-search-tip')) {
                const tip = document.createElement('div');
                tip.className = 'medicine-search-tip';
                tip.innerHTML = '<i class="fas fa-search"></i> Search by: Name, Company, Gram, or Type';
                const dropdown = document.querySelector('.select2-dropdown');
                if (dropdown) {
                    dropdown.insertBefore(tip, dropdown.firstChild);
                }
            }
        }, 100);
    });
});