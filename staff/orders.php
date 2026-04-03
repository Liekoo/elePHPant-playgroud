<?php
require '../config.php';
$pageTitle = 'Orders';
$orders = $conn->query("
    SELECT o.*, p.Product_Name, u.Full_Name, ct.Customer_Type_Description, pt.Payment_Type_Description
    FROM orders o
    JOIN products p       ON o.Product_ID       = p.Product_ID
    JOIN users u          ON o.User_ID           = u.User_ID
    JOIN customer_type ct ON o.Customer_Type_ID  = ct.Customer_Type_ID
    JOIN payments_type pt ON o.Payment_Type_ID   = pt.Payment_Type_ID
    ORDER BY o.Order_Date_Time DESC
");
require '../includes/staff_header.php';
?>
<div class="page-header"><h1 class="page-title">Or<span>ders</span></h1></div>
<div class="card">
  <div class="card-title">All Orders</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#ID</th><th>Customer</th><th>Product</th><th>Payment</th><th>Qty</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php while ($row = $orders->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['Order_ID'] ?></td>
          <td><?= htmlspecialchars($row['Full_Name']) ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td><?= htmlspecialchars($row['Payment_Type_Description']) ?></td>
          <td><?= $row['Order_Quantity'] ?></td>
          <td class="mono">₱<?= number_format($row['Order_Total'],2) ?></td>
          <td><?php $b=match($row['Order_Status']){'Completed'=>'badge-green','Pending'=>'badge-yellow','Cancelled'=>'badge-red',default=>'badge-blue'}; ?><span class="badge <?= $b ?>"><?= $row['Order_Status'] ?></span></td>
          <td class="mono"><?= $row['Order_Date_Time'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require '../includes/footer.php'; ?>