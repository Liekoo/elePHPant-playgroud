<?php
require '../config.php';
$pageTitle = 'Users';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM Users WHERE User_ID = $id AND Role != 'admin'");
    header('Location: users.php?success=deleted'); exit;
}

$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM Users WHERE User_ID = $id")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $conn->real_escape_string($_POST['Full_Name']);
    $username  = $conn->real_escape_string($_POST['Username']);
    $role      = $conn->real_escape_string($_POST['Role']);
    $status    = $conn->real_escape_string($_POST['Status']);

    if (isset($_POST['User_ID']) && $_POST['User_ID'] !== '') {
        $id = (int)$_POST['User_ID'];
        $pwd_sql = '';
        if (!empty($_POST['Password'])) {
            $hash = password_hash($_POST['Password'], PASSWORD_BCRYPT);
            $hash = $conn->real_escape_string($hash);
            $pwd_sql = ", Password = '$hash'";
        }
        $conn->query("UPDATE Users SET Full_Name='$full_name', Username='$username', Role='$role', Status='$status'$pwd_sql WHERE User_ID=$id");
        header('Location: users.php?success=updated');
    } else {
        $hash = password_hash($_POST['Password'], PASSWORD_BCRYPT);
        $hash = $conn->real_escape_string($hash);
        $conn->query("INSERT INTO Users (Full_Name, Username, Password, Role, Status) VALUES ('$full_name','$username','$hash','$role','$status')");
        header('Location: users.php?success=created');
    }
    exit;
}

$users = $conn->query("SELECT * FROM Users ORDER BY Created_At DESC");
require '../includes/header.php';
?>
<div class="page-header"><h1 class="page-title">User<span>s</span></h1></div>
<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success">✓ User <?= htmlspecialchars($_GET['success']) ?> successfully.</div>
<?php endif; ?>
<div class="card">
  <div class="card-title"><?= $editRow ? 'Edit User #'.$editRow['User_ID'] : 'New User' ?></div>
  <form method="POST">
    <?php if ($editRow): ?><input type="hidden" name="User_ID" value="<?= $editRow['User_ID'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="Full_Name" required value="<?= htmlspecialchars($editRow['Full_Name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="Username" required value="<?= htmlspecialchars($editRow['Username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password <?= $editRow ? '(leave blank to keep)' : '' ?></label>
        <input type="password" name="Password" <?= $editRow ? '' : 'required' ?>>
      </div>
      <div class="form-group">
        <label>Role</label>
        <select name="Role">
          <?php foreach (['admin','staff','user'] as $r): ?>
            <option <?= ($editRow && $editRow['Role']==$r)?'selected':'' ?>><?= $r ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="Status">
          <?php foreach (['active','inactive'] as $s): ?>
            <option <?= ($editRow && $editRow['Status']==$s)?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editRow ? '✓ Update User' : '+ Add User' ?></button>
      <?php if ($editRow): ?><a href="users.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="card">
  <div class="card-title">All Users</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#ID</th><th>Full Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while ($row = $users->fetch_assoc()): ?>
        <tr>
          <td class="mono">#<?= $row['User_ID'] ?></td>
          <td><?= htmlspecialchars($row['Full_Name']) ?></td>
          <td class="mono"><?= htmlspecialchars($row['Username']) ?></td>
          <td><?php $rb = match($row['Role']) { 'admin'=>'badge-red','staff'=>'badge-blue',default=>'badge-green' }; ?><span class="badge <?= $rb ?>"><?= $row['Role'] ?></span></td>
          <td><span class="badge <?= $row['Status']=='active'?'badge-green':'badge-yellow' ?>"><?= $row['Status'] ?></span></td>
          <td class="mono"><?= $row['Created_At'] ?></td>
          <td>
            <a href="?edit=<?= $row['User_ID'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <?php if ($row['Role'] !== 'admin'): ?>
              <a href="?delete=<?= $row['User_ID'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')">Del</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require '../includes/footer.php'; ?>
