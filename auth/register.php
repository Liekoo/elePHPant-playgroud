<?php
session_start();
require '../config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php'); exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $conn->real_escape_string(trim($_POST['full_name']));
    $username  = $conn->real_escape_string(trim($_POST['username']));
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    if (empty($full_name) || empty($username) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username already taken
        $check = $conn->query("SELECT User_ID FROM Users WHERE Username = '$username'");
        if ($check->num_rows > 0) {
            $error = 'Username is already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $hash = $conn->real_escape_string($hash);
            $conn->query("INSERT INTO Users (Full_Name, Username, Password, Role, Status)
                          VALUES ('$full_name', '$username', '$hash', 'user', 'active')");
            $success = 'Account created! You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — ShopAdmin</title>
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
    .box {
      background: var(--card); border: 1px solid var(--border);
      border-radius: 16px; padding: 48px 40px; width: 100%; max-width: 440px;
    }
    .logo { font-size: 24px; font-weight: 700; color: var(--accent); margin-bottom: 4px; }
    .logo span { color: var(--text); }
    .subtitle { font-size: 13px; color: var(--muted); font-family: var(--mono); margin-bottom: 32px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
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
      font-weight: 700; cursor: pointer; margin-top: 6px; transition: background 0.15s;
    }
    .btn:hover { background: #22c55e; }
    .alert {
      padding: 12px 16px; border-radius: 10px;
      font-size: 13px; font-family: var(--mono); margin-bottom: 20px;
    }
    .alert-error   { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3); color: var(--danger); }
    .alert-success { background: rgba(74,222,128,0.1);  border: 1px solid rgba(74,222,128,0.3);  color: var(--accent); }
    .footer-link { text-align: center; margin-top: 20px; font-size: 13px; color: var(--muted); }
    .footer-link a { color: var(--accent); text-decoration: none; font-weight: 600; }
    .footer-link a:hover { text-decoration: underline; }
    .strength-bar { height: 4px; border-radius: 2px; background: var(--border); margin-top: 6px; overflow: hidden; }
    .strength-fill { height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background 0.3s; }
  </style>
</head>
<body>
<div class="box">
  <div class="logo">shop<span>admin</span></div>
  <div class="subtitle">Create your account</div>

  <?php if ($error): ?>
    <div class="alert alert-error">✕ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" required autofocus
             value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" required autocomplete="username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" id="pw" required
             autocomplete="new-password" oninput="checkStrength(this.value)">
      <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Create Account</button>
  </form>
  <?php endif; ?>

  <div class="footer-link">
    Already have an account? <a href="login.php">Sign in</a>
  </div>
</div>

<script>
function checkStrength(val) {
  const fill = document.getElementById('strengthFill');
  let score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const colors = ['#f87171','#fbbf24','#fbbf24','#4ade80','#4ade80'];
  fill.style.width  = (score * 20) + '%';
  fill.style.background = colors[score - 1] || 'transparent';
}
</script>
</body>
</html>