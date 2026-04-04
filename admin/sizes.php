<?php
require '../config.php';
$pageTitle = 'Sizes';

if (isset($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $used = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE Size_ID=$id")->fetch_assoc()['c'];
    if ($used > 0) { header('Location: sizes.php?error=inuse'); exit; }
    $conn->query("DELETE FROM sizes WHERE Size_ID=$id");
    header('Location: sizes.php?success=deleted'); exit;
}

$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM sizes WHERE Size_ID=$id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label  = strtoupper($conn->real_escape_string(trim($_POST['Size_Label'])));
    $name   = $conn->real_escape_string(trim($_POST['Size_Name']));
    $price  = (float)$_POST['Size_Price'];
    $sort   = (int)$_POST['Sort_Order'];

    if (isset($_POST['Size_ID']) && $_POST['Size_ID'] !== '') {
        $id = (int)$_POST['Size_ID'];
        $conn->query("UPDATE sizes SET Size_Label='$label',Size_Name='$name',Size_Price=$price,Sort_Order=$sort WHERE Size_ID=$id");
        header('Location: sizes.php?success=updated');
    } else {
        $conn->query("INSERT INTO sizes (Size_Label,Size_Name,Size_Price,Sort_Order) VALUES ('$label','$name',$price,$sort)");
        header('Location: sizes.php?success=created');
    }
    exit;
}

$sizes = $conn->query("SELECT * FROM sizes ORDER BY Sort_Order ASC");
require '../includes/header.php';
?>

<div class="page-header"><h1 class="page-title">Drink <span>Sizes</span></h1></div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">✓ Size <?= htmlspecialchars($_GET['success']) ?> successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error']==='inuse'): ?>
  <div class="alert alert-error">✕ Cannot delete — this size is linked to existing orders.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

  <!-- Form -->
  <div class="card">
    <div class="card-title"><?= $editRow ? 'Edit Size' : 'Add New Size' ?></div>
    <form method="POST">
      <?php if ($editRow): ?><input type="hidden" name="Size_ID" value="<?= $editRow['Size_ID'] ?>"><?php endif; ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Button Label <span style="font-size:10px;text-transform:none;color:var(--muted)">(e.g. S, M, L)</span></label>
          <input type="text" name="Size_Label" maxlength="5" required
                 value="<?= htmlspecialchars($editRow['Size_Label'] ?? '') ?>"
                 style="text-transform:uppercase;font-weight:700;font-size:18px;text-align:center;letter-spacing:2px">
        </div>
        <div class="form-group">
          <label>Full Name <span style="font-size:10px;text-transform:none;color:var(--muted)">(e.g. Small)</span></label>
          <input type="text" name="Size_Name" required value="<?= htmlspecialchars($editRow['Size_Name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Price Add-on (₱) <span style="font-size:10px;text-transform:none;color:var(--muted)">(0 = no extra)</span></label>
          <input type="number" name="Size_Price" step="0.01" min="0" required value="<?= $editRow['Size_Price'] ?? '0.00' ?>">
        </div>
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" name="Sort_Order" min="0" required value="<?= $editRow['Sort_Order'] ?? '0' ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editRow ? '✓ Update Size' : '+ Add Size' ?></button>
        <?php if ($editRow): ?><a href="sizes.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Sizes list -->
  <div class="card">
    <div class="card-title">All Sizes — applies to every product</div>

    <!-- Live preview -->
    <div style="display:flex;gap:10px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)">
      <?php
        $sizes->data_seek(0);
        while ($sz = $sizes->fetch_assoc()):
      ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div style="width:44px;height:44px;border-radius:50%;background:var(--accent);color:#0e0f11;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;font-family:var(--mono)">
          <?= htmlspecialchars($sz['Size_Label']) ?>
        </div>
        <span style="font-size:10px;color:var(--muted);font-family:var(--mono)"><?= htmlspecialchars($sz['Size_Name']) ?></span>
        <span style="font-size:10px;color:var(--accent);font-family:var(--mono)">+₱<?= number_format($sz['Size_Price'],2) ?></span>
      </div>
      <?php endwhile; ?>
    </div>

    <table>
      <thead><tr><th>Button</th><th>Name</th><th>Add-on Price</th><th>Order</th><th>Actions</th></tr></thead>
      <tbody>
        <?php
          $sizes->data_seek(0);
          while ($sz = $sizes->fetch_assoc()):
        ?>
        <tr>
          <td>
            <span style="display:inline-flex;width:36px;height:36px;border-radius:50%;background:var(--surface);border:2px solid var(--border);align-items:center;justify-content:center;font-weight:800;font-size:14px;font-family:var(--mono);color:var(--text)">
              <?= htmlspecialchars($sz['Size_Label']) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($sz['Size_Name']) ?></td>
          <td class="mono">+₱<?= number_format($sz['Size_Price'],2) ?></td>
          <td class="mono"><?= $sz['Sort_Order'] ?></td>
          <td>
            <a href="?edit=<?= $sz['Size_ID'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <a href="?delete=<?= $sz['Size_ID'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this size?')">Del</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

</div>

<div class="card" style="border-color:rgba(34,211,238,0.3)">
  <div class="card-title" style="color:var(--accent2)">How sizes work</div>
  <p style="font-size:13px;color:var(--muted);line-height:1.7">
    Sizes apply globally to <strong style="color:var(--text)">all products</strong>. The <strong style="color:var(--text)">Size Price</strong> is an add-on to the product's base price.
    For example, if a product costs ₱67 and the customer picks <strong style="color:var(--text)">Large (+₱30)</strong>, the final price is <strong style="color:var(--accent)">₱97</strong>.
    Setting a size price to <strong style="color:var(--text)">₱0</strong> means no extra charge for that size.
  </p>
</div>

<?php require '../includes/footer.php'; ?>
