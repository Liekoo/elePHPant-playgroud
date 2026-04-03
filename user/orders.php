<?php
require '../config.php';
require_once '../includes/auth_check.php';
require_login();
$uid = $_SESSION['user_id'];

$orders = $conn->query("
    SELECT o.*, p.Product_Name, ct.Customer_Type_Description, pt.Payment_Type_Description
    FROM orders o
    JOIN products p       ON o.Product_ID       = p.Product_ID
    JOIN customer_type ct ON o.Customer_Type_ID  = ct.Customer_Type_ID
    JOIN payments_type pt ON o.Payment_Type_ID   = pt.Payment_Type_ID
    WHERE o.User_ID = $uid
    ORDER BY o.Order_Date_Time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#0e0f11;--surface:#16181c;--card:#1c1f25;--border:#2a2d35;--accent:#4ade80;--accent2:#22d3ee;--danger:#f87171;--warn:#fbbf24;--text:#e8eaf0;--muted:#6b7280;--radius:10px;--mono:'DM Mono',monospace;--sans:'Syne',sans-serif}
    body{background:var(--bg);color:var(--text);font-family:var(--sans);min-height:100vh}
    .topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
    .topbar-logo{font-size:18px;font-weight:700;color:var(--accent)}.topbar-logo span{color:var(--text)}
    .topbar-right{display:flex;align-items:center;gap:16px}
    .back-link{font-size:13px;color:var(--accent);text-decoration:none;font-weight:600}
    .logout-link{font-size:12px;font-family:var(--mono);color:var(--danger);text-decoration:none}
    .content{padding:32px}
    h1{font-size:22px;font-weight:700;margin-bottom:24px}.accent{color:var(--accent)}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    thead th{text-align:left;padding:10px 14px;font-family:var(--mono);font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
    tbody tr{border-bottom:1px solid rgba(255,255,255,0.04)}
    tbody td{padding:12px 14px;vertical-align:middle}
    .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:500}
    .badge-green{background:rgba(74,222,128,0.12);color:var(--accent)}
    .badge-yellow{background:rgba(251,191,36,0.12);color:var(--warn)}
    .badge-red{background:rgba(248,113,113,0.12);color:var(--danger)}
    .badge-blue{background:rgba(34,211,238,0.12);color:var(--accent2)}
    .mono{font-family:var(--mono)}
    .alert-success{background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.3);color:var(--accent);padding:12px 16px;border-radius:var(--radius);margin-bottom:20px;font-family:var(--mono);font-size:13px}
    .empty{text-align:center;padding:48px;color:var(--muted);font-size:15px}
    .empty a{color:var(--accent);text-decoration:none;font-weight:600}
  </style>
</head>
<body>
<div class="topbar">
  <div class="topbar-logo">my<span>orders</span></div>
  <div class="topbar-right">
    <a href="shop.php" class="back-link">← Back to Shop</a>
    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </div>
</div>
<div class="content">
  <h1>My <span class="accent">Orders</span></h1>
  <?php if (isset($_GET['success'])): ?>
    <div class="alert-success">✓ Order placed successfully!</div>
  <?php endif; ?>
  <div class="card">
    <?php if ($orders->num_rows === 0): ?>
      <div class="empty">No orders yet. <a href="shop.php">Start shopping</a></div>
    <?php else: ?>
    <table>
      <thead><tr><th>#ID</th><th>Product</th><th>Payment</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php while ($row = $orders->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['Order_ID'] ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td><?= htmlspecialchars($row['Payment_Type_Description']) ?></td>
          <td><?= $row['Order_Quantity'] ?></td>
          <td class="mono">₱<?= number_format($row['Product_Price'],2) ?></td>
          <td class="mono">₱<?= number_format($row['Order_Total'],2) ?></td>
          <td><?php $b=match($row['Order_Status']){'Completed'=>'badge-green','Pending'=>'badge-yellow','Cancelled'=>'badge-red',default=>'badge-blue'}; ?><span class="badge <?= $b ?>"><?= $row['Order_Status'] ?></span></td>
          <td class="mono"><?= $row['Order_Date_Time'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>