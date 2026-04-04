<?php
session_start();
require '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Not logged in']); exit; }

$uid    = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $pid     = (int)$_POST['product_id'];
    $sid     = !empty($_POST['size_id']) ? (int)$_POST['size_id'] : null;
    $sid_val = $sid ? $sid : 'NULL';
    $sid_where = $sid ? "Size_ID = $sid" : "Size_ID IS NULL";

    $exists = $conn->query("SELECT Cart_ID FROM cart WHERE User_ID=$uid AND Product_ID=$pid AND $sid_where")->fetch_assoc();
    if ($exists) {
        $conn->query("UPDATE cart SET Quantity = Quantity + 1 WHERE Cart_ID={$exists['Cart_ID']}");
    } else {
        $conn->query("INSERT INTO cart (User_ID, Product_ID, Size_ID, Quantity) VALUES ($uid, $pid, $sid_val, 1)");
    }
} elseif ($action === 'remove') {
    $cid = (int)$_POST['cart_id'];
    $conn->query("DELETE FROM cart WHERE Cart_ID=$cid AND User_ID=$uid");
} elseif ($action === 'update') {
    $cid = (int)$_POST['cart_id'];
    $qty = (int)$_POST['quantity'];
    if ($qty < 1) $conn->query("DELETE FROM cart WHERE Cart_ID=$cid AND User_ID=$uid");
    else $conn->query("UPDATE cart SET Quantity=$qty WHERE Cart_ID=$cid AND User_ID=$uid");
} elseif ($action === 'clear') {
    $conn->query("DELETE FROM cart WHERE User_ID=$uid");
}

$cart_count = $conn->query("SELECT SUM(Quantity) AS c FROM cart WHERE User_ID=$uid")->fetch_assoc()['c'] ?? 0;
echo json_encode(['cart_count' => (int)$cart_count]);