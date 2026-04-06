<?php
require '../config.php';
$pageTitle = 'Orders';
$msg = $err = '';

// --- DELETE ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM orders WHERE Order_ID = $id");
    header('Location: orders.php?success=deleted');
    exit;
}

// --- EDIT: load row ---
$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM orders WHERE Order_ID = $id")->fetch_assoc();
}

// --- CREATE / UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id       = (int)$_POST['Product_ID'];
    $customer_type_id = (int)$_POST['Customer_Type_ID'];
    $payment_type_id  = (int)$_POST['Payment_Type_ID'];
    $order_quantity   = (int)$_POST['Order_Quantity'];
    $order_status     = $conn->real_escape_string($_POST['Order_Status']);

    // Fetch current product price
    $priceRow = $conn->query("SELECT Product_Price FROM products WHERE Product_ID = $product_id")->fetch_assoc();
    $product_price = $priceRow['Product_Price'];

    if (isset($_POST['Order_ID']) && $_POST['Order_ID'] !== '') {
        $id = (int)$_POST['Order_ID'];
        $conn->query("
            UPDATE orders SET
                Product_ID       = $product_id,
                Customer_Type_ID = $customer_type_id,
                Payment_Type_ID  = $payment_type_id,
                Order_Quantity   = $order_quantity,
                Product_Price    = $product_price,
                Order_Status     = '$order_status'
            WHERE Order_ID = $id
        ");
        header('Location: orders.php?success=updated');
    } else {
        $conn->query("
            INSERT INTO orders (Product_ID, Customer_Type_ID, Payment_Type_ID, Order_Quantity, Product_Price, Order_Status)
            VALUES ($product_id, $customer_type_id, $payment_type_id, $order_quantity, $product_price, '$order_status')
        ");
        header('Location: orders.php?success=created');
    }
    exit;
}

// --- READ ---
$orders = $conn->query("
    SELECT o.*, p.Product_Name, ct.Customer_Type_Description, pt.Payment_Type_Description
    FROM orders o
    JOIN products p       ON o.Product_ID       = p.Product_ID
    JOIN customer_type ct ON o.Customer_Type_ID  = ct.Customer_Type_ID
    JOIN payments_type pt ON o.Payment_Type_ID   = pt.Payment_Type_ID
    ORDER BY o.Order_Date_Time DESC
");

$products      = $conn->query("SELECT Product_ID, Product_Name, Product_Price FROM products");
$customerTypes = $conn->query("SELECT * FROM customer_type");
$paymentTypes  = $conn->query("SELECT * FROM payments_type");

require "../includes/header.php";
?>

<div class="page-header">
  <h1 class="page-title">Or<span>ders</span></h1>
</div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">
    ✓ Order <?= htmlspecialchars($_GET['success']) ?> successfully.
  </div>
<?php endif; ?>

<!-- Form -->
<div class="card">
  <div class="card-title"><?= $editRow ? 'Edit Order #' . $editRow['Order_ID'] : 'New Order' ?></div>
  <form method="POST">
    <?php if ($editRow): ?>
      <input type="hidden" name="Order_ID" value="<?= $editRow['Order_ID'] ?>">
    <?php endif; ?>

    <div class="form-grid">
      <div class="form-group">
        <label>Product</label>
        <select name="Product_ID" required id="productSelect" onchange="updatePrice(this)">
          <option value="">— Select product —</option>
          <?php
            $products->data_seek(0);
            while ($p = $products->fetch_assoc()):
          ?>
            <option value="<?= $p['Product_ID'] ?>"
                    data-price="<?= $p['Product_Price'] ?>"
                    <?= ($editRow && $editRow['Product_ID'] == $p['Product_ID']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['Product_Name']) ?> — ₱<?= number_format($p['Product_Price'], 2) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Customer Type</label>
        <select name="Customer_Type_ID" required>
          <option value="">— Select type —</option>
          <?php while ($ct = $customerTypes->fetch_assoc()): ?>
            <option value="<?= $ct['Customer_Type_ID'] ?>"
                    <?= ($editRow && $editRow['Customer_Type_ID'] == $ct['Customer_Type_ID']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($ct['Customer_Type_Description']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Payment Type</label>
        <select name="Payment_Type_ID" required>
          <option value="">— Select payment —</option>
          <?php while ($pt = $paymentTypes->fetch_assoc()): ?>
            <option value="<?= $pt['Payment_Type_ID'] ?>"
                    <?= ($editRow && $editRow['Payment_Type_ID'] == $pt['Payment_Type_ID']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($pt['Payment_Type_Description']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Quantity</label>
        <input type="number" name="Order_Quantity" min="1" required
               value="<?= $editRow['Order_Quantity'] ?? 1 ?>"
               oninput="updateTotal()">
      </div>

      <div class="form-group">
        <label>Unit Price (auto)</label>
        <input type="text" id="unitPrice" readonly
               value="₱<?= $editRow ? number_format($editRow['Product_Price'], 2) : '0.00' ?>"
               style="opacity:0.5; cursor:not-allowed;">
      </div>

      <div class="form-group">
        <label>Est. Total (auto)</label>
        <input type="text" id="estTotal" readonly
               value="₱<?= $editRow ? number_format($editRow['Order_Total'], 2) : '0.00' ?>"
               style="opacity:0.5; cursor:not-allowed;">
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="Order_Status">
          <?php foreach (['Pending','Processing','Completed','Cancelled'] as $s): ?>
            <option <?= ($editRow && $editRow['Order_Status'] == $s) ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <?= $editRow ? '✓ Update Order' : '+ Add Order' ?>
      </button>
      <?php if ($editRow): ?>
        <a href="/admin/orders.php" class="btn btn-ghost">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Table -->
<div class="card">
  <div class="card-title">All Orders</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#ID</th>
          <th>Product</th>
          <th>Customer Type</th>
          <th>Payment</th>
          <th>Qty</th>
          <th>Unit Price</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $orders->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['Order_ID'] ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td><?= htmlspecialchars($row['Customer_Type_Description']) ?></td>
          <td><?= htmlspecialchars($row['Payment_Type_Description']) ?></td>
          <td><?= $row['Order_Quantity'] ?></td>
          <td class="mono">₱<?= number_format($row['Product_Price'], 2) ?></td>
          <td class="mono">₱<?= number_format($row['Order_Total'], 2) ?></td>
          <td>
            <?php
              $badge = match($row['Order_Status']) {
                'Completed'  => 'badge-green',
                'Pending'    => 'badge-yellow',
                'Cancelled'  => 'badge-red',
                'Processing' => 'badge-blue',
                default      => 'badge-blue'
              };
            ?>
            <span class="badge <?= $badge ?>"><?= $row['Order_Status'] ?></span>
          </td>
          <td class="mono"><?= $row['Order_Date_Time'] ?></td>
          <td>
            <a href="?edit=<?= $row['Order_ID'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <a href="?delete=<?= $row['Order_ID'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete order #<?= $row['Order_ID'] ?>?')">Del</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
let currentPrice = <?= $editRow ? $editRow['Product_Price'] : 0 ?>;

function updatePrice(sel) {
  const opt = sel.options[sel.selectedIndex];
  currentPrice = parseFloat(opt.dataset.price) || 0;
  document.getElementById('unitPrice').value = '₱' + currentPrice.toFixed(2);
  updateTotal();
}

function updateTotal() {
  const qty = parseInt(document.querySelector('[name=Order_Quantity]').value) || 0;
  document.getElementById('estTotal').value = '₱' + (qty * currentPrice).toFixed(2);
}
</script>

<?php require "../includes/footer.php"; ?>