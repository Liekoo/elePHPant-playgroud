<?php
session_start();
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin': header('Location: admin/dashboard.php'); exit;
        case 'staff': header('Location: staff/dashboard.php'); exit;
    }
}
// Everyone else (guests and users) goes to shop
header('Location: user/shop.php');
exit;