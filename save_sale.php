<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =========================
       SAFE TYPE CASTING
    ========================== */
    $user_id       = (int)($_POST['user_id'] ?? 0);
    $customer_name = $_POST['customer_name'] ?? '';

    $doctor_fee    = (float)($_POST['doctor_fee'] ?? 0);
    $operation_fee = (float)($_POST['operation_fee'] ?? 0);
    $paid_amount   = (float)($_POST['paid_amount'] ?? 0);

    $items         = json_decode($_POST['items'], true) ?? [];

    $payment_method = $_POST['payment_method'] ?? '';
    $note           = $_POST['note'] ?? '';
    $is_unpaid      = (int)($_POST['is_unpaid'] ?? 0);


    /* =========================
       CALCULATE TOTAL AMOUNT SERVER-SIDE
    ========================== */
    $total_amount = 0;

    foreach ($items as $item) {
        $price    = (float)($item['price_out'] ?? 0);
        $discount = (float)($item['bonus_or_discount'] ?? 0);
$qty = (float)($item['quantity'] ?? 0);


        $subtotal = ($price - $discount) * $qty;
        $total_amount += $subtotal;
    }

    // Add doctor and operation fees
    $total_amount += $doctor_fee + $operation_fee;


    /* =========================
       START TRANSACTION
    ========================== */
    $conn->begin_transaction();

    try {
        /* =========================
           INSERT SALE
        ========================== */
        $stmt = $conn->prepare("
            INSERT INTO sales 
            (user_id, customer_name, doctor_fee, operation_fee, total_amount, sale_date) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("isddd", $user_id, $customer_name, $doctor_fee, $operation_fee, $total_amount);
        $stmt->execute();

        $sale_id = $stmt->insert_id;


        /* =========================
           INSERT ITEMS + UPDATE STOCK
        ========================== */
        foreach ($items as $item) {
            $medicine_id = (int)($item['medicine_id'] ?? 0);
    $qty = (float)($item['quantity'] ?? 0);

            $price       = (float)($item['price_out'] ?? 0);
            $discount    = (float)($item['bonus_or_discount'] ?? 0);

            $subtotal = ($price - $discount) * $qty;

            // Insert item
            $stmt = $conn->prepare("
                INSERT INTO sale_items 
                (sale_id, medicine_id, quantity, price_out, bonus_or_discount, subtotal) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
          $stmt->bind_param("iidddd", $sale_id, $medicine_id, $qty, $price, $discount, $subtotal);

            $stmt->execute();

            // Update stock
            $stmt = $conn->prepare("UPDATE medicines SET amount = amount - ? WHERE id = ?");
      $stmt->bind_param("di", $qty, $medicine_id);

            $stmt->execute();
        }


        /* =========================
           PAYMENT
        ========================== */
        if ($paid_amount > 0) {
            $stmt = $conn->prepare("
                INSERT INTO payments 
                (sale_id, user_id, payment_amount, payment_date, payment_method, note) 
                VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->bind_param("iidss", $sale_id, $user_id, $paid_amount, $payment_method, $note);
            $stmt->execute();
        }


        /* =========================
           UNPAID BALANCE
        ========================== */
        $balance = $total_amount - $paid_amount;

        if ($balance > 0) {
            $stmt = $conn->prepare("
                INSERT INTO customer_unpaid 
                (sale_id, customer_name, unpaid_amount, sale_date, note) 
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $stmt->bind_param("isds", $sale_id, $customer_name, $balance, $note);
            $stmt->execute();
        }


        /* =========================
           COMMIT TRANSACTION
        ========================== */
        $conn->commit();

        $_SESSION['success'] = "Sale completed successfully! Sale ID: #{$sale_id}";
        header("Location: sale_success.php?id=" . $sale_id);
        exit();

    } catch (Exception $e) {

        $conn->rollback();

        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: new_sale.php");
        exit();
    }
}
?>
