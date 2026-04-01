<?php
session_start();
require '../config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM Users WHERE Username = '$username' AND Status = 'active'");
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['Password'])) {
        $_SESSION['user_id']   = $user['User_ID'];
        $_SESSION['username']  = $user['Username'];
        $_SESSION['full_name'] = $user['Full_Name'];
        $_SESSION['role']      = $user['Role'];

        switch ($user['Role']) {
            case 'admin': header('Location: ../admin/dashboard.php'); break;
            case 'staff': header('Location: ../staff/dashboard.php'); break;
            case 'user':  header('Location: ../user/shop.php');       break;
        }
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — ShopAdmin</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0e0f11; --surface: #16181c; --card: #1c1f25;
      --border: #2a2d35; --accent: #4ade80; --danger: #f87171;
      --text: #e8eaf0; --muted: #6b7280;
      --mono: 'DM Mono', monospace; --sans: 'Syne', sans-serif;
    }
    body {
      background: var(--bg); color: var(--text); font-family: var(--sans);
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
    }
    .login-box {
      background: var(--card); border: 1px solid var(--border);
      border-radius: 16px; padding: 48px 40px; width: 100%; max-width: 420px;
    }
    .logo { font-size: 24px; font-weight: 700; color: var(--accent); margin-bottom: 6px; }
    .logo span { color: var(--text); }
    .subtitle { font-size: 13px; color: var(--muted); font-family: var(--mono); margin-bottom: 36px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
    label { font-size: 11px; font-family: var(--mono); color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
    input {
      background: var(--bg); border: 1px solid var(--border); border-radius: 10px;
      color: var(--text); font-family: var(--sans); font-size: 14px;
      padding: 12px 16px; outline: none; transition: border-color 0.15s; width: 100%;
    }
    input:focus { border-color: var(--accent); }
    .btn {
      width: 100%; padding: 13px; background: var(--accent); color: #0e0f11;
      border: none; border-radius: 10px; font-family: var(--sans); font-size: 14px;
      font-weight: 700; cursor: pointer; margin-top: 8px; transition: background 0.15s;
    }
    .btn:hover { background: #22c55e; }
    .error {
      background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3);
      color: var(--danger); padding: 12px 16px; border-radius: 10px;
      font-size: 13px; font-family: var(--mono); margin-bottom: 20px;
    }
  </style>
</head>
<body>
<div class="login-box">
  <div class="logo">shop<span>admin</span></div>
  <div class="subtitle">Sign in to continue</div>
  <?php if ($error): ?>
    <div class="error">✕ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit" class="btn">Sign In</button>
  </form>
</div>
</body>
</html>
