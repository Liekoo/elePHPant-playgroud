<?php
require '../config.php';
$pageTitle = 'Products';

if (isset($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $used = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE Product_ID = $id")->fetch_assoc()['c'];
    if ($used > 0) { header('Location: products.php?error=inuse'); exit; }
    $row = $conn->query("SELECT Product_Image FROM products WHERE Product_ID = $id")->fetch_assoc();
    if (!empty($row['Product_Image']) && file_exists('../' . $row['Product_Image'])) unlink('../' . $row['Product_Image']);
    $conn->query("DELETE FROM product_sizes WHERE Product_ID = $id");
    $conn->query("DELETE FROM products WHERE Product_ID = $id");
    header('Location: products.php?success=deleted'); exit;
}

// Delete a size
if (isset($_GET['delete_size'])) {
    $sid = (int)$_GET['delete_size'];
    $conn->query("DELETE FROM product_sizes WHERE Size_ID = $sid");
    header('Location: products.php?success=size+deleted'); exit;
}

// Add/update size
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_size'])) {
    $pid   = (int)$_POST['size_product_id'];
    $sname = $conn->real_escape_string(trim($_POST['Size_Name']));
    $sprice= (float)$_POST['Size_Price'];
    if (isset($_POST['Size_ID']) && $_POST['Size_ID'] !== '') {
        $sid = (int)$_POST['Size_ID'];
        $conn->query("UPDATE product_sizes SET Size_Name='$sname', Size_Price=$sprice WHERE Size_ID=$sid");
    } else {
        $conn->query("INSERT INTO product_sizes (Product_ID, Size_Name, Size_Price) VALUES ($pid,'$sname',$sprice)");
    }
    header("Location: products.php?manage_sizes=$pid&success=size+saved"); exit;
}

$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM products WHERE Product_ID = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['add_size'])) {
    $name   = $conn->real_escape_string($_POST['Product_Name']);
    $price  = (float)$_POST['Product_Price'];
    $stock  = (int)$_POST['Product_Quantity_Stock'];
    $status = $conn->real_escape_string($_POST['Product_Status']);
    $desc   = $conn->real_escape_string($_POST['Product_Description']);
    $image_sql = '';
    if (!empty($_FILES['Product_Image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['Product_Image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $filename = 'uploads/products/' . uniqid('prod_') . '.' . $ext;
            move_uploaded_file($_FILES['Product_Image']['tmp_name'], '../' . $filename);
            $image_sql = ", Product_Image = '$filename'";
        }
    }
    if (isset($_POST['Product_ID']) && $_POST['Product_ID'] !== '') {
        $id = (int)$_POST['Product_ID'];
        $conn->query("UPDATE products SET Product_Name='$name',Product_Price=$price,Product_Quantity_Stock=$stock,Product_Status='$status',Product_Description='$desc'$image_sql WHERE Product_ID=$id");
        header('Location: products.php?success=updated');
    } else {
        $conn->query("INSERT INTO products (Product_Name,Product_Price,Product_Quantity_Stock,Product_Status,Product_Description) VALUES ('$name',$price,$stock,'$status','$desc')");
        $new_id = $conn->insert_id;
        if ($image_sql) $conn->query("UPDATE products SET $image_sql WHERE Product_ID=$new_id");
        header('Location: products.php?success=created');
    }
    exit;
}

// Manage sizes for a specific product
$manageSizes = null;
$existingSizes = [];
if (isset($_GET['manage_sizes'])) {
    $mid = (int)$_GET['manage_sizes'];
    $manageSizes = $conn->query("SELECT * FROM products WHERE Product_ID = $mid")->fetch_assoc();
    $sizesRes = $conn->query("SELECT * FROM product_sizes WHERE Product_ID = $mid ORDER BY Size_Price");
    while ($s = $sizesRes->fetch_assoc()) $existingSizes[] = $s;
}

$products = $conn->query("SELECT * FROM products ORDER BY Product_ID DESC");
require "../includes/header.php";
?>

<div class="page-header"><h1 class="page-title">Pro<span>ducts</span></h1></div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">✓ <?= htmlspecialchars($_GET['success']) ?> successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'inuse'): ?>
  <div class="alert alert-error">✕ Cannot delete — this product has existing orders.</div>
<?php endif; ?>

<!-- Product form -->
<div class="card">
  <div class="card-title"><?= $editRow ? 'Edit Product #'.$editRow['Product_ID'] : 'New Product' ?></div>
  <form method="POST" enctype="multipart/form-data">
    <?php if ($editRow): ?><input type="hidden" name="Product_ID" value="<?= $editRow['Product_ID'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Product Name</label>
        <input type="text" name="Product_Name" required value="<?= htmlspecialchars($editRow['Product_Name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Base Price (₱) <span style="font-size:10px;color:var(--muted);text-transform:none">(used when no sizes set)</span></label>
        <input type="number" name="Product_Price" step="0.01" min="0" required value="<?= $editRow['Product_Price'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>Stock</label>
        <input type="number" name="Product_Quantity_Stock" min="0" required value="<?= $editRow['Product_Quantity_Stock'] ?? 0 ?>">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="Product_Status">
          <?php foreach (['Active','Inactive','Out of Stock'] as $s): ?>
            <option <?= ($editRow && $editRow['Product_Status']==$s)?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Image <?= $editRow && !empty($editRow['Product_Image']) ? '(leave blank to keep)' : '' ?></label>
        <input type="file" name="Product_Image" accept="image/*" onchange="previewImg(this)">
        <?php if ($editRow && !empty($editRow['Product_Image'])): ?>
          <img src="../<?= htmlspecialchars($editRow['Product_Image']) ?>" id="imgPreview" style="margin-top:8px;width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
        <?php else: ?>
          <img id="imgPreview" style="display:none;margin-top:8px;width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
        <?php endif; ?>
      </div>
      <div class="form-group full">
        <label>Description</label>
        <textarea name="Product_Description"><?= htmlspecialchars($editRow['Product_Description'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editRow ? '✓ Update' : '+ Add Product' ?></button>
      <?php if ($editRow): ?><a href="products.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- Manage sizes panel -->
<?php if ($manageSizes): ?>
<div class="card" style="border-color:var(--accent2)">
  <div class="card-title" style="color:var(--accent2)">Sizes for: <?= htmlspecialchars($manageSizes['Product_Name']) ?></div>

  <!-- Add size form -->
  <form method="POST" style="margin-bottom:20px">
    <input type="hidden" name="add_size" value="1">
    <input type="hidden" name="size_product_id" value="<?= $manageSizes['Product_ID'] ?>">
    <div class="form-grid">
      <div class="form-group">
        <label>Size Name</label>
        <input type="text" name="Size_Name" placeholder="e.g. Small, Medium, Large" required>
      </div>
      <div class="form-group">
        <label>Price (₱)</label>
        <input type="number" name="Size_Price" step="0.01" min="0" required>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">+ Add Size</button>
      <a href="products.php" class="btn btn-ghost">Done</a>
    </div>
  </form>

  <!-- Existing sizes -->
  <?php if (!empty($existingSizes)): ?>
  <table>
    <thead><tr><th>#</th><th>Size</th><th>Price</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($existingSizes as $sz): ?>
      <tr>
        <td class="mono">#<?= $sz['Size_ID'] ?></td>
        <td><?= htmlspecialchars($sz['Size_Name']) ?></td>
        <td class="mono">₱<?= number_format($sz['Size_Price'],2) ?></td>
        <td>
          <a href="?delete_size=<?= $sz['Size_ID'] ?>&manage_sizes=<?= $manageSizes['Product_ID'] ?>"
             class="btn btn-danger btn-sm"
             onclick="return confirm('Delete this size?')">Del</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p style="color:var(--muted);font-size:13px;font-family:var(--mono)">No sizes yet — add one above.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Products table -->
<div class="card">
  <div class="card-title">All Products</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Image</th><th>#ID</th><th>Name</th><th>Base Price</th><th>Sizes</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while ($row = $products->fetch_assoc()):
         $sizeCount = $conn->query("SELECT COUNT(*) AS c FROM sizes")->fetch_assoc()['c'];
        ?>
        <tr>
          <td>
            <?php if (!empty($row['Product_Image'])): ?>
              <img src="../<?= htmlspecialchars($row['Product_Image']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
            <?php else: ?>
              <div style="width:44px;height:44px;border-radius:6px;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:18px">📦</div>
            <?php endif; ?>
          </td>
          <td class="mono">#<?= $row['Product_ID'] ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td class="mono">₱<?= number_format($row['Product_Price'],2) ?></td>
          <td>
            <span class="badge <?= $sizeCount > 0 ? 'badge-blue' : 'badge-yellow' ?>">
              <?= $sizeCount ?> size<?= $sizeCount != 1 ? 's' : '' ?>
            </span>
          </td>
          <td><?= $row['Product_Quantity_Stock'] ?></td>
          <td><?php $b=match($row['Product_Status']){'Active'=>'badge-green','Inactive'=>'badge-red',default=>'badge-yellow'}; ?><span class="badge <?=$b?>"><?=$row['Product_Status']?></span></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="?edit=<?=$row['Product_ID']?>" class="btn btn-ghost btn-sm">Edit</a>
            <a href="?manage_sizes=<?=$row['Product_ID']?>" class="btn btn-sm" style="background:rgba(34,211,238,0.1);color:var(--accent2);border:1px solid rgba(34,211,238,0.2)">Sizes</a>
            <a href="?delete=<?=$row['Product_ID']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete product?')">Del</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function previewImg(input) {
  const p = document.getElementById('imgPreview');
  if (input.files && input.files[0]) { p.src = URL.createObjectURL(input.files[0]); p.style.display='block'; }
}
</script>

<?php require '../includes/footer.php'; ?>