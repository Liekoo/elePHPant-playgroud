<?php
require '../config.php';
$pageTitle = 'Staff Dashboard';
$totalProducts = $conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$totalOrders   = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
$pendingOrders = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE Order_Status='Pending'")->fetch_assoc()['c'];
$recentOrders  = $conn->query("
    SELECT o.Order_ID, u.Full_Name, p.Product_Name, o.Order_Quantity, o.Order_Total, o.Order_Status, o.Order_Date_Time
    FROM orders o
    JOIN products p ON o.Product_ID = p.Product_ID
    JOIN users u    ON o.User_ID    = u.User_ID
    ORDER BY o.Order_Date_Time DESC LIMIT 8
");
require '../includes/staff_header.php';
?>
<div class="page-header"><h1 class="page-title">Staff <span>Dashboard</span></h1></div>
<div class="stats-row">
  <div class="stat-card"><div class="stat-label">Products</div><div class="stat-value"><?= $totalProducts ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Orders</div><div class="stat-value"><?= $totalOrders ?></div></div>
  <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value"><?= $pendingOrders ?></div></div>
</div>
<div class="card">
  <div class="card-title">Recent Orders</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#ID</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php while ($row = $recentOrders->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['Order_ID'] ?></td>
          <td><?= htmlspecialchars($row['Full_Name']) ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td><?= $row['Order_Quantity'] ?></td>
          <td class="mono">₱<?= number_format($row['Order_Total'], 2) ?></td>
          <td><?php $b = match($row['Order_Status']) { 'Completed'=>'badge-green','Pending'=>'badge-yellow','Cancelled'=>'badge-red',default=>'badge-blue' }; ?><span class="badge <?= $b ?>"><?= $row['Order_Status'] ?></span></td>
          <td class="mono"><?= $row['Order_Date_Time'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require '../includes/footer.php'; ?>