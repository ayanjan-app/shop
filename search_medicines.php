<?php
require_once 'db.php';

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
$total_count = $result->num_rows;
?>

<div class="table-header">
    <h3>
        <i class="fas fa-list"></i>
        Medicines List
    </h3>
    <div class="records-count">
        <i class="fas fa-database"></i>
        <?php echo $total_count; ?> Records Found
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
                $expired = strtotime($row['expire_date']) < time();
                $low_stock = $row['amount'] < 10;
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