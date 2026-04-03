<?php
require '../config.php';
$pageTitle = 'Products';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE Product_ID = $id");
    header('Location: products.php?success=deleted');
    exit;
}

$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM products WHERE Product_ID = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = $conn->real_escape_string($_POST['Product_Name']);
    $price  = (float)$_POST['Product_Price'];
    $stock  = (int)$_POST['Product_Quantity_Stock'];
    $status = $conn->real_escape_string($_POST['Product_Status']);
    $desc   = $conn->real_escape_string($_POST['Product_Description']);

    if (isset($_POST['Product_ID']) && $_POST['Product_ID'] !== '') {
        $id = (int)$_POST['Product_ID'];
        $conn->query("
            UPDATE products SET
                Product_Name           = '$name',
                Product_Price          = $price,
                Product_Quantity_Stock = $stock,
                Product_Status         = '$status',
                Product_Description    = '$desc'
            WHERE Product_ID = $id
        ");
        header('Location: products.php?success=updated');
    } else {
        $conn->query("
            INSERT INTO products (Product_Name, Product_Price, Product_Quantity_Stock, Product_Status, Product_Description)
            VALUES ('$name', $price, $stock, '$status', '$desc')
        ");
        header('Location: products.php?success=created');
    }
    exit;
}

$products = $conn->query("SELECT * FROM products ORDER BY Product_ID DESC");
require "../includes/header.php";
?>

<div class="page-header">
  <h1 class="page-title">Pro<span>ducts</span></h1>
</div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">✓ Product <?= htmlspecialchars($_GET['success']) ?> successfully.</div>
<?php endif; ?>

<div class="card">
  <div class="card-title"><?= $editRow ? 'Edit Product #' . $editRow['Product_ID'] : 'New Product' ?></div>
  <form method="POST">
    <?php if ($editRow): ?>
      <input type="hidden" name="Product_ID" value="<?= $editRow['Product_ID'] ?>">
    <?php endif; ?>

    <div class="form-grid">
      <div class="form-group">
        <label>Product Name</label>
        <input type="text" name="Product_Name" required
               value="<?= htmlspecialchars($editRow['Product_Name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Price (₱)</label>
        <input type="number" name="Product_Price" step="0.01" min="0" required
               value="<?= $editRow['Product_Price'] ?? '' ?>">
      </div>

      <div class="form-group">
        <label>Stock Quantity</label>
        <input type="number" name="Product_Quantity_Stock" min="0" required
               value="<?= $editRow['Product_Quantity_Stock'] ?? 0 ?>">
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="Product_Status">
          <?php foreach (['Active', 'Inactive', 'Out of Stock'] as $s): ?>
            <option <?= ($editRow && $editRow['Product_Status'] == $s) ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group full">
        <label>Description</label>
        <textarea name="Product_Description"><?= htmlspecialchars($editRow['Product_Description'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <?= $editRow ? '✓ Update Product' : '+ Add Product' ?>
      </button>
      <?php if ($editRow): ?>
        <a href="/pos/vibe/admin/products.php" class="btn btn-ghost">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">All Products</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#ID</th>
          <th>Name</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th>Description</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $products->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['Product_ID'] ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td class="mono">₱<?= number_format($row['Product_Price'], 2) ?></td>
          <td><?= $row['Product_Quantity_Stock'] ?></td>
          <td>
            <?php
              $badge = match($row['Product_Status']) {
                'Active'       => 'badge-green',
                'Inactive'     => 'badge-red',
                'Out of Stock' => 'badge-yellow',
                default        => 'badge-blue'
              };
            ?>
            <span class="badge <?= $badge ?>"><?= $row['Product_Status'] ?></span>
          </td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)">
            <?= htmlspecialchars($row['Product_Description'] ?? '—') ?>
          </td>
          <td>
            <a href="?edit=<?= $row['Product_ID'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <a href="?delete=<?= $row['Product_ID'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete product #<?= $row['Product_ID'] ?>?')">Del</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require "../includes/footer.php"; ?>