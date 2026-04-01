<?php
require 'config.php';
$pageTitle = 'Dashboard1';

$totalOrders   = $conn->query("SELECT COUNT(*) AS c FROM Orders")->fetch_assoc()['c'];
$totalProducts = $conn->query("SELECT COUNT(*) AS c FROM Products")->fetch_assoc()['c'];
$totalRevenue  = $conn->query("SELECT SUM(Order_Total) AS t FROM Orders")->fetch_assoc()['t'] ?? 0;
$pendingOrders = $conn->query("SELECT COUNT(*) AS c FROM Orders WHERE Order_Status = 'Pending'")->fetch_assoc()['c'];

$recentOrders = $conn->query("
    SELECT o.Order_ID, p.Product_Name, o.Order_Quantity, o.Product_Price,
           o.Order_Total, o.Order_Status, o.Order_Date_Time
    FROM Orders o
    JOIN Products p ON o.Product_ID = p.Product_ID
    ORDER BY o.Order_Date_Time DESC
    LIMIT 8
");

require 'includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Dash<span>board</span></h1>
</div>

<div class="stats-row">
  <div class="stat-card">
    <div class="stat-label">Total Orders</div>
    <div class="stat-value"><?= $totalOrders ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Products</div>
    <div class="stat-value"><?= $totalProducts ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Revenue</div>
    <div class="stat-value">₱<?= number_format($totalRevenue, 2) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Pending</div>
    <div class="stat-value"><?= $pendingOrders ?></div>
  </div>
</div>

<div class="card">
  <div class="card-title">Recent Orders</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#ID</th>
          <th>Product</th>
          <th>Qty</th>
          <th>Unit Price</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $recentOrders->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['Order_ID'] ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td><?= $row['Order_Quantity'] ?></td>
          <td class="mono">₱<?= number_format($row['Product_Price'], 2) ?></td>
          <td class="mono">₱<?= number_format($row['Order_Total'], 2) ?></td>
          <td>
            <?php
              $badge = match($row['Order_Status']) {
                'Completed' => 'badge-green',
                'Pending'   => 'badge-yellow',
                'Cancelled' => 'badge-red',
                default     => 'badge-blue'
              };
            ?>
            <span class="badge <?= $badge ?>"><?= $row['Order_Status'] ?></span>
          </td>
          <td class="mono"><?= $row['Order_Date_Time'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
