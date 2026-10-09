<?php
require_once 'db.php';
require_once 'auth.php';

$id = 0;
$name = $company_name = $type = $gram = "";
$manufacture_date = $expire_date = "";
$price_in = $price_out = 0;
$amount = 0;
$action = "Add";
$error_message = "";

// INSERT or UPDATE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_form'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $company_name = trim($_POST['company_name']);
    $type = $_POST['type'];
    $gram = $_POST['gram'];
    $manufacture_date = $_POST['manufacture_date'];
    $expire_date = $_POST['expire_date'];
    $price_in = floatval($_POST['price_in']);
    $price_out = floatval($_POST['price_out']);
  $amount = floatval($_POST['amount']);


    // Check for duplicate: name + company + type + gram
    if ($id > 0) {
        $stmt_check = $conn->prepare("SELECT id FROM medicines WHERE name=? AND company_name=? AND type=? AND gram=? AND id != ?");
        $stmt_check->bind_param("ssssi", $name, $company_name, $type, $gram, $id);
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM medicines WHERE name=? AND company_name=? AND type=? AND gram=?");
        $stmt_check->bind_param("ssss", $name, $company_name, $type, $gram);
    }
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $error_message = "This medicine with the same name, company, type, and gram already exists!";
    } else {
        if ($id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE medicines SET name=?, company_name=?, type=?, gram=?, manufacture_date=?, expire_date=?, price_in=?, price_out=?, amount=? WHERE id=?");
            $stmt->bind_param("ssssssdddi", $name, $company_name, $type, $gram, $manufacture_date, $expire_date, $price_in, $price_out, $amount, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO medicines (name, company_name, type, gram, manufacture_date, expire_date, price_in, price_out, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
           $stmt->bind_param("sssssssdd", $name, $company_name, $type, $gram, $manufacture_date, $expire_date, $price_in, $price_out, $amount);

            $stmt->execute();
            $stmt->close();
        }
        header("Location: medicines.php");
        exit;
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM medicines WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: medicines.php");
    exit;
}

// EDIT
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM medicines WHERE id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
        $name = $row['name'];
        $company_name = $row['company_name'];
        $type = $row['type'];
        $gram = $row['gram'];
        $manufacture_date = $row['manufacture_date'];
        $expire_date = $row['expire_date'];
        $price_in = $row['price_in'];
        $price_out = $row['price_out'];
        $amount = $row['amount'];
        $action = "Update";
    }
    $stmt->close();
}

// FETCH ALL with search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (!empty($search)) {
    $query = "SELECT * FROM medicines WHERE name LIKE ? OR company_name LIKE ? ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
} else {
    $query = "SELECT * FROM medicines ORDER BY id DESC";
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();

// Get total count for display
$total_count = $result->num_rows;

// Calculate total stock amount from all displayed medicines
$total_stock = 0;
$result->data_seek(0);
while ($stock_row = $result->fetch_assoc()) {
    $total_stock += floatval($stock_row['amount']);
}
$result->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines Management - PharmaCare RX</title>
    <!-- Include Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Include CSS -->
    <link rel="stylesheet" href="s.css">
    <style>
        .today {
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: auto;
        }

        /* ===== Table Header Stats ===== */
        .table-header-stats {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }
        .total-stock {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .total-stock strong {
            font-size: 1rem;
        }

        /* ===== Combo Box Styles (Gram & Type) ===== */
        .combo-group { position: relative; }
        .combo-wrap { position: relative; display: flex; align-items: center; }
        .combo-wrap .combo-input {
            width: 100%;
            padding-right: 38px;
        }
        .combo-toggle {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 6px 8px;
            line-height: 1;
        }
        .combo-toggle:hover { color: #333; }
        .combo-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
            max-height: 220px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            margin-top: 4px;
        }
        .combo-dropdown.open { display: block; }
        .combo-option {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: background 0.15s;
        }
        .combo-option:hover,
        .combo-option.active {
            background: #e8f5e9;
            color: #1b5e20;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-pills"></i>
                Medicines Management
            </h1>
            <div class="today">
                <i class="far fa-calendar"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo $error_message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <i class="fas fa-plus-circle"></i>
                <h2><?php echo $action; ?> Medicine</h2>
            </div>

            <form method="POST" action="" id="medicineForm">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="submit_form" value="1">

                <div class="form-grid">
                    <!-- Name -->
                    <div class="form-group">
                        <label class="form-label">Medicine Name</label>
                        <div class="form-input-group">
                            <i class="fas fa-capsules"></i>
                            <input type="text" name="name" class="form-input" 
                                   value="<?php echo htmlspecialchars($name); ?>" 
                                   placeholder="Enter medicine name" required>
                        </div>
                    </div>

                    <!-- Company -->
                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <div class="form-input-group">
                            <i class="fas fa-building"></i>
                            <input type="text" name="company_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($company_name); ?>" 
                                   placeholder="Enter company name" required>
                        </div>
                    </div>

                    <!-- Type (Combo: input + dropdown) -->
                    <div class="form-group combo-group">
                        <label class="form-label">Type</label>
                        <div class="combo-wrap">
                            <input type="text" name="type" id="typeInput" class="form-input combo-input"
                                   value="<?php echo htmlspecialchars($type); ?>"
                                   placeholder="Type or select type" autocomplete="off" required>
                            <button type="button" class="combo-toggle" id="typeToggle" tabindex="-1">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="combo-dropdown" id="typeDropdown">
                                <?php
                                $types_list = ["Inj","Capsol","Tab","Syp","Sch","Cream","Lotion","Drop","Geel","Powder",
                                              "Eye ointment","Skin ointment","Slotion"];
                                foreach ($types_list as $t):
                                ?>
                                <div class="combo-option" data-value="<?php echo $t; ?>"><?php echo $t; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Gram (Combo: input + dropdown) -->
                    <div class="form-group combo-group">
                        <label class="form-label">Gram</label>
                        <div class="combo-wrap">
                            <input type="text" name="gram" id="gramInput" class="form-input combo-input"
                                   value="<?php echo htmlspecialchars($gram); ?>"
                                   placeholder="Type or select gram" autocomplete="off" required>
                            <button type="button" class="combo-toggle" id="gramToggle" tabindex="-1">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="combo-dropdown" id="gramDropdown">
                                <?php
                                $grams_list = ["0.5mg","1mg","2mg","3mg","10mg","12.5mg","20mg","40mg","100mg","125mg","156mg","200mg","250mg","312mg","375mg","400mg","500mg","625mg","750mg","1000mg","2000mg","4000mg"];
                                foreach ($grams_list as $g):
                                ?>
                                <div class="combo-option" data-value="<?php echo $g; ?>"><?php echo $g; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Manufacture Date -->
                    <div class="form-group">
                        <label class="form-label">Manufacture Date</label>
                        <div class="form-input-group">
                            <i class="far fa-calendar-alt"></i>
                            <input type="date" name="manufacture_date" class="form-input" 
                                   value="<?php echo $manufacture_date; ?>" required>
                        </div>
                    </div>

                    <!-- Expire Date -->
                    <div class="form-group">
                        <label class="form-label">Expire Date</label>
                        <div class="form-input-group">
                            <i class="far fa-calendar-times"></i>
                            <input type="date" name="expire_date" class="form-input" 
                                   value="<?php echo $expire_date; ?>" required>
                        </div>
                    </div>

                    <!-- Price In -->
                    <div class="form-group">
                        <label class="form-label">Price In</label>
                        <div class="form-input-group">
                            <i class="fas fa-dollar-sign"></i>
                            <input type="number" step="0.01" name="price_in" class="form-input" 
                                   value="<?php echo $price_in; ?>" placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- Price Out -->
                    <div class="form-group">
                        <label class="form-label">Price Out</label>
                        <div class="form-input-group">
                            <i class="fas fa-tag"></i>
                            <input type="number" step="0.01" name="price_out" class="form-input" 
                                   value="<?php echo $price_out; ?>" placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="form-group">
    <label class="form-label">Amount in Stock</label>
    <div class="form-input-group">
        <i class="fas fa-boxes"></i>
        <input type="number" name="amount" class="form-input"
               value="<?php echo $amount; ?>" placeholder="0"
               min="0" step="any" required>
    </div>
</div>

                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i>
                    <?php echo $action; ?> Medicine
                </button>
            </form>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" action="" id="searchForm" class="search-form">
                <div class="form-input-group search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" id="searchInput" class="search-input" 
                           placeholder="Search by medicine name or company..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <?php if (!empty($search)): ?>
                <a href="medicines.php" class="clear-btn">
                    <i class="fas fa-times"></i> Clear Search
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Medicines List
                </h3>
                <div class="table-header-stats">
                    <div class="records-count">
                        <i class="fas fa-database"></i>
                        <?php echo $total_count; ?> Records Found
                    </div>
                    <div class="total-stock">
                        <i class="fas fa-boxes"></i>
                        Total Stock: <strong><?php echo number_format($total_stock, 0); ?></strong>
                    </div>
                </div>
            </div>

            <?php if ($total_count > 0): ?>
            <div class="table-responsive">
                <table class="medicines-table">
                    <thead>
                        <tr>
            
                            <th>Name</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Gram</th>
                            <th>Mfg Date</th>
                            <th>Exp Date</th>
                            <th>Price In</th>
                            <th>Price Out</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): 
                            // Determine stock status
                            $expired = strtotime($row['expire_date']) < time();
                            $low_stock = $row['amount'] <=2;
                            $status_class = $expired ? 'expired' : ($low_stock ? 'low-stock' : 'in-stock');
                            $status_text = $expired ? 'Expired' : ($low_stock ? 'Low Stock' : 'In Stock');
                        ?>
                        <tr>
                          
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                            <td><?php echo $row['type']; ?></td>
                            <td><?php echo $row['gram']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['manufacture_date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['expire_date'])); ?></td>
                            <td class="price-in"><?php echo number_format($row['price_in'], 2); ?></td>
                            <td class="price-out"><?php echo number_format($row['price_out'], 2); ?></td>
                            <td><?php echo $row['amount']; ?></td>
                            <td>
                                <span class="<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="#" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-box-open"></i>
                <h3>No Medicines Found</h3>
                <p><?php echo !empty($search) ? 'Try a different search term' : 'Add your first medicine using the form above'; ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this medicine? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-cancel" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-confirm">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // AJAX Search without page refresh
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = this.value.trim();
                updateTable(searchTerm);
            }, 500);
        });
        
        function updateTable(searchTerm = '') {
            // Show loading
            const tableContainer = document.querySelector('.table-container');
            const originalContent = tableContainer.innerHTML;
            tableContainer.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            // Make AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `search_medicines.php?search=${encodeURIComponent(searchTerm)}`, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    tableContainer.innerHTML = xhr.responseText;
                    // Re-attach event listeners
                    attachEventListeners();
                } else {
                    tableContainer.innerHTML = originalContent;
                    console.error('Error loading search results');
                }
            };
            
            xhr.onerror = function() {
                tableContainer.innerHTML = originalContent;
                console.error('Network error');
            };
            
            xhr.send();
        }
        
        // Form validation
        const form = document.getElementById('medicineForm');
        form.addEventListener('submit', function(e) {
            const priceIn = parseFloat(document.querySelector('input[name="price_in"]').value);
            const priceOut = parseFloat(document.querySelector('input[name="price_out"]').value);
            const amount = parseInt(document.querySelector('input[name="amount"]').value);
            const mfgDate = document.querySelector('input[name="manufacture_date"]').value;
            const expDate = document.querySelector('input[name="expire_date"]').value;
            
            if (priceIn <= 0 || priceOut <= 0) {
                alert('Prices must be greater than zero');
                e.preventDefault();
                return;
            }
            
            if (amount < 0) {
                alert('Amount cannot be negative');
                e.preventDefault();
                return;
            }
            
            if (new Date(expDate) <= new Date(mfgDate)) {
                alert('Expiry date must be after manufacture date');
                e.preventDefault();
                return;
            }
        });
        
        // Auto-focus first input
        if (<?php echo $id == 0 ? 'true' : 'false'; ?>) {
            document.querySelector('input[name="name"]').focus();
        }
        
        // Real-time validation for prices
        const priceInInput = document.querySelector('input[name="price_in"]');
        const priceOutInput = document.querySelector('input[name="price_out"]');
        
        priceInInput.addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
            if (this.value && priceOutInput.value && parseFloat(this.value) >= parseFloat(priceOutInput.value)) {
                this.style.borderColor = '#f44336';
                priceOutInput.style.borderColor = '#f44336';
            } else {
                this.style.borderColor = '';
                priceOutInput.style.borderColor = '';
            }
        });
        
        priceOutInput.addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
            if (this.value && priceInInput.value && parseFloat(this.value) <= parseFloat(priceInInput.value)) {
                this.style.borderColor = '#f44336';
                priceInInput.style.borderColor = '#f44336';
            } else {
                this.style.borderColor = '';
                priceInInput.style.borderColor = '';
            }
        });
        
        // Date validation
        const mfgDateInput = document.querySelector('input[name="manufacture_date"]');
        const expDateInput = document.querySelector('input[name="expire_date"]');
        
        // Set min dates
        const today = new Date().toISOString().split('T')[0];
        mfgDateInput.max = today;
        expDateInput.min = today;
        
        mfgDateInput.addEventListener('change', function() {
            expDateInput.min = this.value;
        });
        
        expDateInput.addEventListener('change', function() {
            if (this.value && mfgDateInput.value && new Date(this.value) <= new Date(mfgDateInput.value)) {
                alert('Expiry date must be after manufacture date');
                this.value = '';
            }
        });

        // ===== Combo Box Logic (Type & Gram) =====
        function initCombo(inputId, toggleId, dropdownId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(toggleId);
            const dropdown = document.getElementById(dropdownId);
            const options = dropdown.querySelectorAll('.combo-option');

            function openDropdown() {
                dropdown.classList.add('open');
                filterOptions(input.value.trim().toLowerCase());
            }
            function closeDropdown() {
                dropdown.classList.remove('open');
            }
            function filterOptions(term) {
                options.forEach(opt => {
                    const val = opt.dataset.value.toLowerCase();
                    opt.style.display = (!term || val.includes(term)) ? 'block' : 'none';
                });
            }

            input.addEventListener('focus', openDropdown);
            input.addEventListener('click', openDropdown);
            input.addEventListener('input', function() {
                openDropdown();
                filterOptions(this.value.trim().toLowerCase());
            });

            toggle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                if (dropdown.classList.contains('open')) {
                    closeDropdown();
                } else {
                    input.focus();
                    openDropdown();
                }
            });

            options.forEach(opt => {
                opt.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    input.value = this.dataset.value;
                    closeDropdown();
                    input.dispatchEvent(new Event('change'));
                });
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.combo-group')) closeDropdown();
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeDropdown();
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const visible = Array.from(options).filter(o => o.style.display !== 'none');
                    const current = dropdown.querySelector('.combo-option.active');
                    let idx = visible.indexOf(current);
                    idx = (idx + 1) % visible.length;
                    visible.forEach(o => o.classList.remove('active'));
                    if (visible[idx]) {
                        visible[idx].classList.add('active');
                        visible[idx].scrollIntoView({ block: 'nearest' });
                    }
                }
                if (e.key === 'Enter') {
                    const active = dropdown.querySelector('.combo-option.active');
                    if (active && dropdown.classList.contains('open')) {
                        e.preventDefault();
                        input.value = active.dataset.value;
                        closeDropdown();
                    }
                }
            });
        }

        initCombo('typeInput', 'typeToggle', 'typeDropdown');
        initCombo('gramInput', 'gramToggle', 'gramDropdown');
    });
    
    function attachEventListeners() {
        // Re-attach delete confirmation listeners
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('onclick').match(/\d+/)[0];
                confirmDelete(id);
            });
        });
    }
    
    // Modal functions
    function confirmDelete(id) {
        const modal = document.getElementById('deleteModal');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.href = '?delete=' + id;
        modal.style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    
    // Close modal on overlay click
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    
    // Prevent form submission on Enter key in search
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    }
    </script>
</body>
</html>