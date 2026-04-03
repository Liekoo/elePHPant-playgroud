<?php
require '../config.php';
require_once '../includes/auth_check.php';
require_login();
$uid = $_SESSION['user_id'];

$orders = $conn->query("
    SELECT o.*, p.Product_Name, p.Product_Image,
           ct.Customer_Type_Description, pt.Payment_Type_Description
    FROM orders o
    JOIN products p       ON o.Product_ID       = p.Product_ID
    JOIN customer_type ct ON o.Customer_Type_ID  = ct.Customer_Type_ID
    JOIN payments_type pt ON o.Payment_Type_ID   = pt.Payment_Type_ID
    WHERE o.User_ID = $uid
    ORDER BY o.Order_Date_Time DESC
");

$rows = [];
while ($r = $orders->fetch_assoc()) $rows[] = $r;

// Stats
$total_orders   = count($rows);
$total_spent    = array_sum(array_column($rows, 'Order_Total'));
$pending_count  = count(array_filter($rows, fn($r) => $r['Order_Status'] === 'Pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — Sip & Savor</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --cream:#fdf6ee;--cream2:#f5e6d3;--cream3:#eddcc8;
      --brown-light:#c8956c;--brown:#9b6a3e;--brown-dark:#6b3f1f;--brown-deep:#3d1f0a;
      --rose:#d4856a;--sage:#7a9e7e;--gold:#c8956c;
      --text:#2d1810;--text-soft:#7a5c45;--text-muted:#a8856a;
      --border:#e8d5be;--border-dark:#d4bfa0;--card:#fff9f2;
      --serif:'Playfair Display',serif;--sans:'DM Sans',sans-serif;--mono:'DM Mono',monospace;--radius:16px;
    }
    body{background:var(--cream);color:var(--text);font-family:var(--sans);min-height:100vh}

    .topbar{background:var(--brown-deep);padding:0 40px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(61,31,10,0.3)}
    .logo{font-family:var(--serif);font-size:20px;color:var(--cream)}.logo span{color:var(--brown-light)}
    .topbar-right{display:flex;align-items:center;gap:12px}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all 0.2s}
    .btn-warm{color:var(--brown-deep);background:var(--brown-light)}
    .btn-warm:hover{background:var(--cream2);transform:translateY(-1px)}
    .btn-outline{color:var(--cream2);background:transparent;border:1.5px solid rgba(253,246,238,0.25)}
    .btn-outline:hover{background:rgba(253,246,238,0.1)}
    .logout-link{font-size:12px;color:rgba(253,246,238,0.4);text-decoration:none;font-family:var(--mono);transition:color 0.15s}
    .logout-link:hover{color:var(--rose)}

    /* Hero strip */
    .hero-strip{background:linear-gradient(135deg,var(--brown-deep) 0%,#5c2d0e 100%);padding:36px 40px}
    .hero-strip h1{font-family:var(--serif);font-size:32px;color:var(--cream);margin-bottom:4px}
    .hero-strip h1 span{color:var(--brown-light)}
    .hero-strip p{font-size:13px;color:rgba(253,246,238,0.5);font-family:var(--mono)}

    .content{max-width:960px;margin:0 auto;padding:36px 24px}

    /* Stats */
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:32px}
    .stat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px 22px}
    .stat-label{font-size:11px;font-family:var(--mono);color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
    .stat-value{font-family:var(--serif);font-size:28px;font-weight:700;color:var(--brown)}

    /* Alert */
    .alert-success{background:rgba(122,158,126,0.12);border:1px solid rgba(122,158,126,0.3);color:var(--sage);padding:14px 18px;border-radius:var(--radius);margin-bottom:24px;font-family:var(--mono);font-size:13px}

    /* Order cards */
    .order-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px;transition:box-shadow 0.2s,border-color 0.2s}
    .order-card:hover{box-shadow:0 6px 24px rgba(155,106,62,0.1);border-color:var(--border-dark)}
    .order-card-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);background:var(--cream2)}
    .order-id{font-family:var(--mono);font-size:12px;color:var(--text-muted)}
    .order-date{font-family:var(--mono);font-size:11px;color:var(--text-muted)}
    .order-card-body{display:flex;align-items:center;gap:16px;padding:16px 20px}
    .order-img{width:64px;height:64px;border-radius:10px;object-fit:cover;border:1px solid var(--border);flex-shrink:0}
    .order-img-placeholder{width:64px;height:64px;border-radius:10px;background:var(--cream3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0}
    .order-info{flex:1}
    .order-name{font-family:var(--serif);font-size:16px;font-weight:600;color:var(--brown-dark);margin-bottom:4px}
    .order-meta{font-size:12px;color:var(--text-muted);font-family:var(--mono);display:flex;gap:12px;flex-wrap:wrap}
    .order-right{text-align:right;flex-shrink:0}
    .order-total{font-family:var(--serif);font-size:20px;font-weight:700;color:var(--brown);margin-bottom:6px}
    .order-qty{font-size:11px;font-family:var(--mono);color:var(--text-muted)}

    /* Status badges */
    .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:600}
    .badge-pending {background:rgba(200,149,108,0.15);color:var(--brown-light);border:1px solid rgba(200,149,108,0.3)}
    .badge-processing{background:rgba(122,158,126,0.15);color:var(--sage);border:1px solid rgba(122,158,126,0.3)}
    .badge-completed{background:rgba(122,158,126,0.2);color:#4a7a4e;border:1px solid rgba(122,158,126,0.4)}
    .badge-cancelled{background:rgba(212,133,106,0.15);color:var(--rose);border:1px solid rgba(212,133,106,0.3)}

    .empty{text-align:center;padding:80px 20px}
    .empty .icon{font-size:64px;margin-bottom:16px;opacity:0.4;display:block}
    .empty h3{font-family:var(--serif);font-size:22px;color:var(--brown-dark);margin-bottom:8px}
    .empty p{font-size:14px;color:var(--text-muted);margin-bottom:20px}
    .empty a{display:inline-block;padding:11px 28px;background:var(--brown);color:var(--cream);border-radius:30px;text-decoration:none;font-weight:600;font-size:13px;transition:all 0.2s}
    .empty a:hover{background:var(--brown-dark);transform:translateY(-1px);box-shadow:0 6px 16px rgba(155,106,62,0.3)}

    .section-title{font-family:var(--serif);font-size:20px;color:var(--brown-dark);margin-bottom:16px}
    .section-title span{color:var(--brown-light)}

    .footer{background:var(--brown-deep);color:rgba(253,246,238,0.4);text-align:center;padding:20px;font-size:12px;font-family:var(--mono);margin-top:60px}
  </style>
</head>
<body>

<div class="topbar">
  <div class="logo">Sip &amp; <span>Savor</span></div>
  <div class="topbar-right">
    <a href="shop.php" class="btn btn-warm">🧋 Menu</a>
    <a href="cart.php" class="btn btn-outline">🛒 Cart</a>
    <a href="../auth/logout.php" class="logout-link">logout</a>
  </div>
</div>

<div class="hero-strip">
  <h1>My <span>Orders</span></h1>
  <p>Your sip history with us</p>
</div>

<div class="content">
  <?php if (isset($_GET['success'])): ?>
    <div class="alert-success">✔️ Your order has been placed! We'll have it ready soon.</div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total Orders</div>
      <div class="stat-value"><?= $total_orders ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Spent</div>
      <div class="stat-value">₱<?= number_format($total_spent, 2) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= $pending_count ?></div>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty">
      <span class="icon">🍵</span>
      <h3>No orders yet</h3>
      <p>Your order history will appear here once you place your first order.</p>
      <a href="shop.php">Browse the menu</a>
    </div>
  <?php else: ?>
    <h2 class="section-title">Order <span>History</span></h2>
    <?php foreach ($rows as $row):
      $badge = match($row['Order_Status']) {
        'Completed'  => 'badge-completed',
        'Pending'    => 'badge-pending',
        'Processing' => 'badge-processing',
        'Cancelled'  => 'badge-cancelled',
        default      => 'badge-pending'
      };
    ?>
    <div class="order-card">
      <div class="order-card-header">
        <span class="order-id">Order #<?= $row['Order_ID'] ?></span>
        <div style="display:flex;align-items:center;gap:12px">
          <span class="badge <?= $badge ?>"><?= $row['Order_Status'] ?></span>
          <span class="order-date"><?= date('M d, Y · h:i A', strtotime($row['Order_Date_Time'])) ?></span>
        </div>
      </div>
      <div class="order-card-body">
        <?php if (!empty($row['Product_Image'])): ?>
          <img class="order-img" src="../<?= htmlspecialchars($row['Product_Image']) ?>" alt="<?= htmlspecialchars($row['Product_Name']) ?>">
        <?php else: ?>
          <div class="order-img-placeholder">🧋</div>
        <?php endif; ?>
        <div class="order-info">
          <div class="order-name"><?= htmlspecialchars($row['Product_Name']) ?></div>
          <div class="order-meta">
            <span>📦 Qty: <?= $row['Order_Quantity'] ?></span>
            <span>💳 <?= htmlspecialchars($row['Payment_Type_Description']) ?></span>
            <span>👤 <?= htmlspecialchars($row['Customer_Type_Description']) ?></span>
            <span>🏷️ ₱<?= number_format($row['Product_Price'], 2) ?>/ea</span>
          </div>
        </div>
        <div class="order-right">
          <div class="order-total">₱<?= number_format($row['Order_Total'], 2) ?></div>
          <div class="order-qty"><?= $row['Order_Quantity'] ?> × ₱<?= number_format($row['Product_Price'], 2) ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="footer">🧋 Sip &amp; Savor Milk Tea — Made with love &amp; the finest ingredients</div>
</body>
</html>