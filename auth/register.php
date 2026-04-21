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
        $check = $conn->query("SELECT User_ID FROM users WHERE Username = '$username'");
        if ($check->num_rows > 0) {
            $error = 'Username is already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $hash = $conn->real_escape_string($hash);
            $conn->query("INSERT INTO users (Full_Name, Username, Password, Role, Status) VALUES ('$full_name','$username','$hash','user','active')");
            $success = 'Account created! You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join Us — Sip & Savor</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --water:#0070ff;--water-mid:#1588ff;--water-bright:#0097ff;--water-light:#00b1ff;
  --sky:#e6f4ff;--sky2:#cceeff;
  --deep:#002d6e;--deep2:#003d8f;--navy:#001a4d;
  --text:#051c3a;--text-muted:#5e8ab4;--border:#b3d4f0;
  --serif:'Playfair Display',serif;--sans:'DM Sans',sans-serif;--mono:'DM Mono',monospace;
}
body{background:var(--sky);font-family:var(--sans);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{display:flex;width:100%;max-width:900px;border-radius:24px;overflow:hidden;box-shadow:0 24px 60px rgba(0,26,77,0.2)}

.left{
  flex:0 0 340px;background:linear-gradient(160deg,var(--navy) 0%,var(--deep2) 60%,#005bb5 100%);
  padding:60px 40px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden;
}
.left::before{content:'💧';position:absolute;font-size:200px;opacity:0.06;bottom:-30px;right:-20px;line-height:1}
.left-tag{display:inline-block;background:rgba(0,177,255,0.15);color:var(--water-light);border:1px solid rgba(0,177,255,0.3);padding:5px 14px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:20px}
.left-title{font-family:var(--serif);font-size:32px;color:#e6f4ff;line-height:1.2;margin-bottom:12px}
.left-title span{color:var(--water-light)}
.left-sub{font-size:13px;color:rgba(168,212,245,0.6);line-height:1.7}

.right{flex:1;background:#f5faff;padding:48px;display:flex;flex-direction:column;justify-content:center}
.logo{font-family:var(--serif);font-size:20px;color:var(--deep2);margin-bottom:4px}
.logo span{color:var(--water)}
.tagline{font-size:12px;color:var(--text-muted);font-family:var(--mono);margin-bottom:28px}
h2{font-family:var(--serif);font-size:24px;color:var(--deep2);margin-bottom:4px}
.subtitle{font-size:13px;color:var(--text-muted);margin-bottom:22px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.full{grid-column:1/-1}
label{font-size:11px;font-family:var(--mono);color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
input{background:var(--sky);border:1.5px solid var(--border);border-radius:10px;color:var(--text);font-family:var(--sans);font-size:13px;padding:11px 14px;outline:none;transition:border-color 0.2s;width:100%}
input:focus{border-color:var(--water-bright);background:#fff}
.btn{width:100%;padding:12px;background:var(--water);color:#fff;border:none;border-radius:30px;font-family:var(--sans);font-size:14px;font-weight:600;cursor:pointer;margin-top:14px;transition:all 0.2s}
.btn:hover{background:var(--deep2);box-shadow:0 6px 20px rgba(0,112,255,0.3);transform:translateY(-1px)}
.alert{padding:11px 15px;border-radius:10px;font-size:12px;font-family:var(--mono);margin-bottom:16px}
.alert-error{background:rgba(192,74,0,0.08);border:1px solid rgba(192,74,0,0.22);color:#c04a00}
.alert-success{background:rgba(0,151,255,0.08);border:1px solid rgba(0,151,255,0.25);color:var(--water-mid)}
.strength-bar{height:3px;border-radius:2px;background:var(--border);margin-top:5px;overflow:hidden}
.strength-fill{height:100%;width:0;border-radius:2px;transition:width 0.3s,background 0.3s}
.footer-links{display:flex;flex-direction:column;gap:8px;align-items:center;margin-top:16px}
.footer-links a{font-size:13px;color:var(--water);text-decoration:none;font-weight:500}
.footer-links a:hover{color:var(--deep2);text-decoration:underline}
.footer-links .back{font-size:12px;color:var(--text-muted);font-family:var(--mono)}
</style>
</head>
<body>
<div class="wrap">
  <div class="left">
    <div class="left-tag">New here?</div>
    <h2 class="left-title">Join our<br><span>tea family</span></h2>
    <p class="left-sub">Create your account and start ordering and enjoin exlusive offers</p>
  </div>
  <div class="right">
    <div class="logo">Aqua<span>luxe</span></div>
    <div class="tagline">Pure water, pure care.</div>
    <h2>Create Account</h2>
    <p class="subtitle">It's free and only takes a minute!</p>

    <?php if ($error): ?>
      <div class="alert alert-error">✕ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <div class="form-grid">
        <div class="form-group full">
          <label>Full Name</label>
          <input type="text" name="full_name" required autofocus value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="form-group full">
          <label>Username</label>
          <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" id="pw" required oninput="checkStrength(this.value)">
          <div class="strength-bar"><div class="strength-fill" id="sf"></div></div>
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required>
        </div>
      </div>
      <button type="submit" class="btn">Join Us</button>
    </form>
    <?php endif; ?>
    <div class="footer-links">
      <a href="login.php">Already have an account? Sign in</a>
      <a href="../user/shop.php" class="back">← Browse menu first</a>
    </div>
  </div>
</div>
<script>
function checkStrength(v) {
  const f = document.getElementById('sf');
  let s = 0;
  if(v.length>=6)s++;if(v.length>=10)s++;
  if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const c=['#d4856a','#c8956c','#b8956a','#7a9e7e','#5a8e5e'];
  f.style.width=(s*20)+'%';f.style.background=c[s-1]||'transparent';
}
</script>
</body>
</html>