<?php
require '../config.php';
$pageTitle = 'Payment Types';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM Payments_Type WHERE Payment_Type_ID = $id");
    header('Location: payment_types.php?success=deleted');
    exit;
}

$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM Payments_Type WHERE Payment_Type_ID = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc = $conn->real_escape_string($_POST['Payment_Type_Description']);

    if (isset($_POST['Payment_Type_ID']) && $_POST['Payment_Type_ID'] !== '') {
        $id = (int)$_POST['Payment_Type_ID'];
        $conn->query("UPDATE Payments_Type SET Payment_Type_Description = '$desc' WHERE Payment_Type_ID = $id");
        header('Location: payment_types.php?success=updated');
    } else {
        $conn->query("INSERT INTO Payments_Type (Payment_Type_Description) VALUES ('$desc')");
        header('Location: payment_types.php?success=created');
    }
    exit;
}

$rows = $conn->query("SELECT * FROM Payments_Type ORDER BY Payment_Type_ID DESC");
require '../includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Payment <span>Types</span></h1>
</div>

<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">✓ Payment type <?= htmlspecialchars($_GET['success']) ?> successfully.</div>
<?php endif; ?>

<div class="card">
  <div class="card-title"><?= $editRow ? 'Edit Type #' . $editRow['Payment_Type_ID'] : 'New Payment Type' ?></div>
  <form method="POST">
    <?php if ($editRow): ?>
      <input type="hidden" name="Payment_Type_ID" value="<?= $editRow['Payment_Type_ID'] ?>">
    <?php endif; ?>

    <div class="form-grid">
      <div class="form-group">
        <label>Description</label>
        <input type="text" name="Payment_Type_Description" required
               value="<?= htmlspecialchars($editRow['Payment_Type_Description'] ?? '') ?>">
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <?= $editRow ? '✓ Update' : '+ Add Type' ?>
      </button>
      <?php if ($editRow): ?>
        <a href="payment_types.php" class="btn btn-ghost">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">All Payment Types</div>
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
          <td class="mono">#<?= $row['Payment_Type_ID'] ?></td>
          <td><?= htmlspecialchars($row['Payment_Type_Description']) ?></td>
          <td>
            <a href="?edit=<?= $row['Payment_Type_ID'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <a href="?delete=<?= $row['Payment_Type_ID'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this payment type?')">Del</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
