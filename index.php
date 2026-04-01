<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php'); exit;
}
switch ($_SESSION['role']) {
    case 'admin': header('Location: admin/dashboard.php'); break;
    case 'staff': header('Location: staff/dashboard.php'); break;
    case 'user':  header('Location: user/shop.php');       break;
    default:      header('Location: auth/login.php');
}
exit;
