<?php
require '../config.php';
$pageTitle = 'Customer Types';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM Customer_Type WHERE Customer_Type_ID = $id");
    header('Location: customer_types.php?success=deleted');
    exit;
}

$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM Customer_Type WHERE Customer_Type_ID = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc = $conn->real_escape_string($_POST['Customer_Type_Description']);

    if (isset($_POST['Customer_Type_ID']) && $_POST['Customer_Type_ID'] !== '') {
        $id = (int)$_POST['Customer_Type_ID'];
        $conn->query("UPDATE Customer_Type SET Customer_Type_Description = '$desc' WHERE Customer_Type_ID = $id");
        header('Location: customer_types.php?success=updated');
    } else {
        $conn->query("INSERT INTO Customer_Type (Customer_Type_Description) VALUES ('$desc')");
        header('Location: customer_types.php?success=created');
    }
    exit;
}

$rows = $conn->query("SELECT * FROM Customer_Type ORDER BY Customer_Type_ID DESC");
require '../includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Customer <span>Types</span></h1>
</div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">✓ Customer type <?= htmlspecialchars($_GET['success']) ?> successfully.</div>
<?php endif; ?>

<div class="card">
  <div class="card-title"><?= $editRow ? 'Edit Type #' . $editRow['Customer_Type_ID'] : 'New Customer Type' ?></div>
  <form method="POST">
    <?php if ($editRow): ?>
      <input type="hidden" name="Customer_Type_ID" value="<?= $editRow['Customer_Type_ID'] ?>">
    <?php endif; ?>

    <div class="form-grid">
      <div class="form-group">
        <label>Description</label>
        <input type="text" name="Customer_Type_Description" required
               value="<?= htmlspecialchars($editRow['Customer_Type_Description'] ?? '') ?>">
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <?= $editRow ? '✓ Update' : '+ Add Type' ?>
      </button>
      <?php if ($editRow): ?>
        <a href="customer_types.php" class="btn btn-ghost">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">All Customer Types</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#ID</th>
          <th>Description</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $rows->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['Customer_Type_ID'] ?></td>
          <td><?= htmlspecialchars($row['Customer_Type_Description']) ?></td>
          <td>
            <a href="?edit=<?= $row['Customer_Type_ID'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <a href="?delete=<?= $row['Customer_Type_ID'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this customer type?')">Del</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
