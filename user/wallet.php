<?php
/**
 * CUSTOMER — user/wallet.php
 * Sip Credits wallet — view balance, request top-up, transaction history
 */
require '../config.php';
require_once '../includes/auth_check.php';
require_login();
$uid = $_SESSION['user_id'];

// Submit top-up request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_topup'])) {
    $amount  = (float)$_POST['amount'];
    $ref     = $conn->real_escape_string(trim($_POST['reference']));
    $method  = $conn->real_escape_string($_POST['payment_method']);
    if ($amount >= 50 && !empty($ref)) {
        $conn->query("INSERT INTO topup_requests (User_ID, Amount, Reference, Payment_Method) VALUES ($uid, $amount, '$ref', '$method')");
        header('Location: wallet.php?toast=requested'); exit;
    }
}

// Fetch wallet data
$user    = $conn->query("SELECT Full_Name, Wallet_Balance FROM users WHERE User_ID=$uid")->fetch_assoc();
$balance = $user['Wallet_Balance'];

$transactions = $conn->query("
    SELECT * FROM wallet_transactions WHERE User_ID=$uid ORDER BY Created_At DESC LIMIT 20
");

$requests = $conn->query("
    SELECT * FROM topup_requests WHERE User_ID=$uid ORDER BY Created_At DESC LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sip Credits — Wallet</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --cream:#fdf6ee;--cream2:#f5e6d3;--cream3:#eddcc8;
      --brown-light:#c8956c;--brown:#9b6a3e;--brown-dark:#6b3f1f;--brown-deep:#3d1f0a;
      --rose:#d4856a;--sage:#7a9e7e;--text:#2d1810;--text-soft:#7a5c45;
      --text-muted:#a8856a;--border:#e8d5be;--border-dark:#d4bfa0;--card:#fff9f2;
      --serif:'Playfair Display',serif;--sans:'DM Sans',sans-serif;--mono:'DM Mono',monospace;--radius:16px;
    }
    body{background:var(--cream);color:var(--text);font-family:var(--sans);min-height:100vh}
    .topbar{background:var(--brown-deep);padding:0 40px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(61,31,10,0.3)}
    .logo{font-family:var(--serif);font-size:20px;color:var(--cream)}.logo span{color:var(--brown-light)}
    .topbar-right{display:flex;align-items:center;gap:12px}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all 0.2s}
    .btn-warm{color:var(--brown-deep);background:var(--brown-light)}.btn-warm:hover{background:var(--cream2);transform:translateY(-1px)}
    .btn-outline{color:var(--cream2);background:transparent;border:1.5px solid rgba(253,246,238,0.25)}.btn-outline:hover{background:rgba(253,246,238,0.1)}
    .btn-brown{width:100%;padding:13px;background:var(--brown);color:var(--cream);border:none;border-radius:30px;font-family:var(--sans);font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;margin-top:8px}
    .btn-brown:hover{background:var(--brown-dark);transform:translateY(-1px);box-shadow:0 6px 20px rgba(155,106,62,0.3)}
    .logout-link{font-size:12px;color:rgba(253,246,238,0.4);text-decoration:none;font-family:var(--mono)}.logout-link:hover{color:var(--rose)}
    .content{max-width:900px;margin:0 auto;padding:36px 24px}
    .back-link{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);text-decoration:none;margin-bottom:24px;font-family:var(--mono)}.back-link:hover{color:var(--brown)}

    /* Wallet card */
    .wallet-hero{background:linear-gradient(135deg,var(--brown-deep) 0%,#5c2d0e 60%,var(--brown-dark) 100%);border-radius:20px;padding:36px;margin-bottom:24px;position:relative;overflow:hidden}
    .wallet-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23c8956c' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
    .wallet-content{position:relative;z-index:1}
    .wallet-label{font-size:12px;font-family:var(--mono);color:rgba(253,246,238,0.5);text-transform:uppercase;letter-spacing:2px;margin-bottom:8px}
    .wallet-balance{font-family:var(--serif);font-size:52px;font-weight:700;color:var(--cream);letter-spacing:-2px;margin-bottom:4px}
    .wallet-balance .currency{font-size:28px;vertical-align:super;margin-right:4px;color:var(--brown-light)}
    .wallet-name{font-size:14px;color:rgba(253,246,238,0.6);font-family:var(--mono)}
    .wallet-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(200,149,108,0.2);border:1px solid rgba(200,149,108,0.3);color:var(--brown-light);padding:5px 14px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-top:16px}

    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
    @media(max-width:640px){.grid-2{grid-template-columns:1fr}}
    .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:20px}
    .card-title{font-family:var(--serif);font-size:18px;color:var(--brown-dark);margin-bottom:16px}
    .form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
    label{font-size:11px;font-family:var(--mono);color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
    input,select{background:var(--cream2);border:1.5px solid var(--border);border-radius:10px;color:var(--text);font-family:var(--sans);font-size:13px;padding:11px 14px;outline:none;width:100%;transition:border-color 0.2s}
    input:focus,select:focus{border-color:var(--brown-light)}
    select option{background:var(--cream)}
    .helper{font-size:11px;color:var(--text-muted);font-family:var(--mono);margin-top:4px}

    /* Transaction list */
    .tx-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
    .tx-item:last-child{border-bottom:none}
    .tx-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;margin-right:12px}
    .tx-topup{background:rgba(122,158,126,0.15)}
    .tx-purchase{background:rgba(212,133,106,0.15)}
    .tx-refund{background:rgba(200,149,108,0.15)}
    .tx-info{flex:1;min-width:0}
    .tx-label{font-size:13px;font-weight:600;color:var(--text)}
    .tx-date{font-size:11px;font-family:var(--mono);color:var(--text-muted)}
    .tx-amount-pos{font-family:var(--serif);font-size:15px;font-weight:700;color:var(--sage)}
    .tx-amount-neg{font-family:var(--serif);font-size:15px;font-weight:700;color:var(--rose)}

    /* Request status */
    .req-item{display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:10px;background:var(--cream2);margin-bottom:8px;border:1px solid var(--border)}
    .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:600}
    .badge-pending{background:rgba(200,149,108,0.15);color:var(--brown-light);border:1px solid rgba(200,149,108,0.3)}
    .badge-approved{background:rgba(122,158,126,0.15);color:var(--sage);border:1px solid rgba(122,158,126,0.3)}
    .badge-rejected{background:rgba(212,133,106,0.15);color:var(--rose);border:1px solid rgba(212,133,106,0.3)}

    .info-box{background:var(--cream2);border:1px dashed var(--border-dark);border-radius:10px;padding:14px 16px;font-size:12px;color:var(--text-soft);line-height:1.6;margin-bottom:14px}
    .info-box strong{color:var(--brown-dark)}

    /* Toast */
    .toast-bar{position:fixed;bottom:28px;right:28px;background:var(--brown-deep);color:var(--cream);padding:13px 22px;border-radius:30px;font-weight:700;font-size:13px;opacity:0;transition:opacity 0.3s,transform 0.4s cubic-bezier(.34,1.56,.64,1);transform:translateY(80px);pointer-events:none;z-index:999;box-shadow:0 8px 24px rgba(61,31,10,0.3)}
    .toast-bar.show{opacity:1;transform:translateY(0)}
    .footer{background:var(--brown-deep);color:rgba(253,246,238,0.4);text-align:center;padding:20px;font-size:12px;font-family:var(--mono);margin-top:60px}
  </style>
</head>
<body>
<div class="topbar">
  <div class="logo">Sip &amp; <span>Savor</span></div>
  <div class="topbar-right">
    <a href="shop.php" class="btn btn-warm">🧋 Menu</a>
    <a href="orders.php" class="btn btn-outline">My Orders</a>
    <a href="../auth/logout.php" class="logout-link">logout</a>
  </div>
</div>

<div class="content">
  <a href="shop.php" class="back-link">← Back to menu</a>

  <!-- Wallet balance hero -->
  <div class="wallet-hero">
    <div class="wallet-content">
      <div class="wallet-label">Sip Credits Balance</div>
      <div class="wallet-balance"><span class="currency">₱</span><?= number_format($balance, 2) ?></div>
      <div class="wallet-name"><?= htmlspecialchars($user['Full_Name']) ?></div>
      <div class="wallet-chip">🧋 Sip Credits</div>
    </div>
  </div>

  <div class="grid-2">

    <!-- Top-up request form -->
    <div class="card">
      <div class="card-title">Request Top-Up</div>
      <div class="info-box">
        Send your payment via <strong>GCash or Maya</strong> to our number, then fill in your reference number below. Staff will verify and credit your Sip Credits within a few minutes.
      </div>
      <form method="POST">
        <div class="form-group">
          <label>Payment Method</label>
          <select name="payment_method">
            <option>GCash</option>
            <option>Maya</option>
            <option>Cash</option>
          </select>
        </div>
        <div class="form-group">
          <label>Amount (₱)</label>
          <input type="number" name="amount" min="50" step="50" placeholder="Minimum ₱50" required>
          <span class="helper">Minimum top-up: ₱50</span>
        </div>
        <div class="form-group">
          <label>Reference / Transaction No.</label>
          <input type="text" name="reference" placeholder="e.g. 1234567890" required>
          <span class="helper">From your GCash/Maya SMS confirmation</span>
        </div>
        <button type="submit" name="request_topup" class="btn-brown">Submit Top-Up Request</button>
      </form>
    </div>

    <!-- Pending requests -->
    <div class="card">
      <div class="card-title">My Top-Up Requests</div>
      <?php
        $requests->data_seek(0);
        $hasReqs = false;
        while ($req = $requests->fetch_assoc()):
          $hasReqs = true;
          $badgeCls = match($req['Status']) { 'approved'=>'badge-approved','rejected'=>'badge-rejected',default=>'badge-pending' };
      ?>
      <div class="req-item">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--brown-dark)">₱<?= number_format($req['Amount'],2) ?> via <?= $req['Payment_Method'] ?></div>
          <div style="font-size:11px;font-family:var(--mono);color:var(--text-muted)">Ref: <?= htmlspecialchars($req['Reference']) ?> · <?= date('M d, h:i A', strtotime($req['Created_At'])) ?></div>
          <?php if ($req['Note']): ?><div style="font-size:11px;color:var(--rose);margin-top:3px"><?= htmlspecialchars($req['Note']) ?></div><?php endif; ?>
        </div>
        <span class="badge <?= $badgeCls ?>"><?= $req['Status'] ?></span>
      </div>
      <?php endwhile; ?>
      <?php if (!$hasReqs): ?>
        <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:20px">No top-up requests yet.</p>
      <?php endif; ?>
    </div>

  </div>

  <!-- Transaction history -->
  <div class="card">
    <div class="card-title">Transaction History</div>
    <?php
      $hasTx = false;
      while ($tx = $transactions->fetch_assoc()):
        $hasTx = true;
        $isPos = in_array($tx['Type'], ['topup','refund']);
        $icons = ['topup'=>'💰','purchase'=>'🧋','refund'=>'↩️'];
        $txClasses = ['topup'=>'tx-topup','purchase'=>'tx-purchase','refund'=>'tx-refund'];
        $labels = ['topup'=>'Sip Credits Top-Up','purchase'=>'Order Payment','refund'=>'Refund'];
    ?>
    <div class="tx-item">
      <div class="tx-icon <?= $txClasses[$tx['Type']] ?>"><?= $icons[$tx['Type']] ?></div>
      <div class="tx-info">
        <div class="tx-label"><?= $labels[$tx['Type']] ?></div>
        <div class="tx-date"><?= date('M d, Y · h:i A', strtotime($tx['Created_At'])) ?><?= $tx['Note'] ? ' — '.htmlspecialchars($tx['Note']) : '' ?></div>
      </div>
      <div>
        <div class="<?= $isPos ? 'tx-amount-pos' : 'tx-amount-neg' ?>"><?= $isPos ? '+' : '-' ?>₱<?= number_format($tx['Amount'],2) ?></div>
        <div style="font-size:10px;font-family:var(--mono);color:var(--text-muted);text-align:right">bal: ₱<?= number_format($tx['Balance_After'],2) ?></div>
      </div>
    </div>
    <?php endwhile; ?>
    <?php if (!$hasTx): ?>
      <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:32px">No transactions yet.</p>
    <?php endif; ?>
  </div>
</div>

<div class="footer">🧋 Sip &amp; Savor Milk Tea — Sip Credits never expire</div>
<div class="toast-bar" id="toast"></div>

<script>
const p=new URLSearchParams(location.search);
if(p.get('toast')==='requested'){
  const t=document.getElementById('toast');
  t.textContent='✓ Top-up request submitted! We\'ll credit your account shortly.';
  setTimeout(()=>t.classList.add('show'),100);
  setTimeout(()=>t.classList.remove('show'),4000);
  history.replaceState({},'',location.pathname);
}
</script>
</body>
</html>