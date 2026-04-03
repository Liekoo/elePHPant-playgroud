<?php
session_start();
require '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Not logged in']); exit; }

$uid    = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $pid = (int)$_POST['product_id'];
    $conn->query("INSERT INTO cart (User_ID, Product_ID, Quantity) VALUES ($uid, $pid, 1)
                  ON DUPLICATE KEY UPDATE Quantity = Quantity + 1");
} elseif ($action === 'remove') {
    $pid = (int)$_POST['product_id'];
    $conn->query("DELETE FROM cart WHERE User_ID=$uid AND Product_ID=$pid");
} elseif ($action === 'update') {
    $pid = (int)$_POST['product_id'];
    $qty = (int)$_POST['quantity'];
    if ($qty < 1) $conn->query("DELETE FROM cart WHERE User_ID=$uid AND Product_ID=$pid");
    else $conn->query("UPDATE cart SET Quantity=$qty WHERE User_ID=$uid AND Product_ID=$pid");
} elseif ($action === 'clear') {
    $conn->query("DELETE FROM cart WHERE User_ID=$uid");
}

$cart_count = $conn->query("SELECT SUM(Quantity) AS c FROM cart WHERE User_ID=$uid")->fetch_assoc()['c'] ?? 0;
echo json_encode(['cart_count' => (int)$cart_count]);
