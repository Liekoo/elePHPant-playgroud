<?php
require '../config.php';
require_once '../includes/auth_check.php';
require_login();
$uid = $_SESSION['user_id'];

$cart_items = $conn->query("
    SELECT c.Cart_ID, c.Quantity, c.Size_ID,
           p.Product_ID, p.Product_Name, p.Product_Price, p.Product_Quantity_Stock, p.Product_Image,
           s.Size_Label, s.Size_Name,
           p.Product_Price + COALESCE(s.Size_Price, 0) AS Unit_Price,
           (c.Quantity * (p.Product_Price + COALESCE(s.Size_Price, 0))) AS Subtotal
    FROM cart c
    JOIN products p ON c.Product_ID = p.Product_ID
    LEFT JOIN sizes s ON c.Size_ID = s.Size_ID
    WHERE c.User_ID = $uid
");

$items = [];
$grand_total = 0;
while ($r = $cart_items->fetch_assoc()) { $items[] = $r; $grand_total += $r['Subtotal']; }

$payment_types  = $conn->query("SELECT * FROM payments_type");
$customer_name  = $_SESSION['full_name'];
$walletRow      = $conn->query("SELECT Wallet_Balance FROM users WHERE User_ID=$uid")->fetch_assoc();
$wallet_balance = $walletRow['Wallet_Balance'];

// ── Handle checkout ──────────────────────────────────────
// FIX: Read Payment_Type_ID from POST (wallet or numeric ID)
// FIX: isset($_POST['checkout']) works because we added hidden input name="checkout" value="1"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $payment_id  = trim($_POST['Payment_Type_ID'] ?? '');
    $note        = $conn->real_escape_string(trim($_POST['Order_Note'] ?? ''));
    $use_wallet  = ($payment_id === 'wallet');
    $cust_name   = $conn->real_escape_string($customer_name);
    $ctype_row   = $conn->query("SELECT Customer_Type_ID FROM customer_type LIMIT 1")->fetch_assoc();
    $customer_id = $ctype_row ? $ctype_row['Customer_Type_ID'] : 1;

    // Recalculate total fresh
    $fresh_total = 0;
    foreach ($items as $item) { $fresh_total += $item['Subtotal']; }

    if (empty($payment_id)) {
        // No payment — do nothing, fall through to render page
    } elseif ($use_wallet && $wallet_balance < $fresh_total) {
        $wallet_error = true;
    } else {
        // Determine actual payment_type_id to store
        if ($use_wallet) {
            $ptRow      = $conn->query("SELECT Payment_Type_ID FROM payments_type LIMIT 1")->fetch_assoc();
            $actual_pid = $ptRow ? $ptRow['Payment_Type_ID'] : 1;
        } else {
            $actual_pid = (int)$payment_id;
        }

        // Insert orders + deduct stock
        foreach ($items as $item) {
            $pid   = $item['Product_ID'];
            $sid   = $item['Size_ID'] ? $item['Size_ID'] : 'NULL';
            $qty   = $item['Quantity'];
            $price = $item['Unit_Price'];
            $fn    = $use_wallet ? ($note ? $note . ' [Wallet ]' : 'Paid with Wallet ') : $note;
            $fne   = $conn->real_escape_string($fn);
            $conn->query("INSERT INTO orders (User_ID,Product_ID,Size_ID,Customer_Type_ID,Payment_Type_ID,Order_Quantity,Product_Price,Customer_Name,Order_Note)
                          VALUES ($uid,$pid,$sid,$customer_id,$actual_pid,$qty,$price,'$cust_name','$fne')");
            $conn->query("UPDATE products SET Product_Quantity_Stock = Product_Quantity_Stock - $qty WHERE Product_ID = $pid");
        }

        // Deduct wallet balance + log transaction
        if ($use_wallet) {
            $new_bal = $wallet_balance - $fresh_total;
            $conn->query("UPDATE users SET Wallet_Balance = $new_bal WHERE User_ID = $uid");
            $wNote = $conn->real_escape_string('Order payment via Wallet ');
            $conn->query("INSERT INTO wallet_transactions (User_ID,Type,Amount,Balance_After,Note,Status)
                          VALUES ($uid,'purchase',$fresh_total,$new_bal,'$wNote','approved')");
        }

        $conn->query("DELETE FROM cart WHERE User_ID = $uid");
        header('Location: orders.php?success=1'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Order — Wallet & Savor</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono&display=swap" rel="stylesheet">
  <style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --water:#0070ff;--water-mid:#1588ff;--water-bright:#0097ff;--water-light:#00b1ff;
  --sky:#e6f4ff;--sky2:#cceeff;--sky3:#b3e5fc;
  --deep:#002d6e;--deep2:#003d8f;--navy:#001a4d;
  --text:#051c3a;--text-soft:#2d5a8e;--text-muted:#5e8ab4;
  --border:#b3d4f0;--border-dark:#7ab3e0;--card:#f5faff;
  --serif:'Playfair Display',serif;--sans:'DM Sans',sans-serif;--mono:'DM Mono',monospace;--radius:16px;
}
body{background:#eef7ff;color:var(--text);font-family:var(--sans);min-height:100vh}
.topbar{background:var(--navy);padding:0 40px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(0,26,77,0.4)}
.logo{font-family:var(--serif);font-size:20px;color:#e6f4ff}.logo span{color:var(--water-light)}
.topbar-right{display:flex;align-items:center;gap:12px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all 0.2s}
.btn-outline{color:#cce8ff;background:transparent;border:1.5px solid rgba(0,177,255,0.3)}.btn-outline:hover{background:rgba(0,112,255,0.15)}
.logout-link{font-size:12px;color:rgba(168,212,245,0.4);text-decoration:none;font-family:var(--mono)}.logout-link:hover{color:var(--water-light)}
.content{max-width:900px;margin:0 auto;padding:40px 24px}
.page-title{font-family:var(--serif);font-size:32px;color:var(--deep2);margin-bottom:6px}.page-title span{color:var(--water)}
.page-sub{font-size:13px;color:var(--text-muted);margin-bottom:32px;font-family:var(--mono)}
.back-link{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);text-decoration:none;margin-bottom:24px;font-family:var(--mono)}.back-link:hover{color:var(--water)}
.empty{text-align:center;padding:80px 20px}
.empty .icon{font-size:64px;margin-bottom:16px;opacity:0.4;display:block}
.empty h3{font-family:var(--serif);font-size:22px;color:var(--deep2);margin-bottom:8px}
.empty p{font-size:14px;color:var(--text-muted);margin-bottom:20px}
.empty a{display:inline-block;padding:11px 28px;background:var(--water);color:#fff;border-radius:30px;text-decoration:none;font-weight:600;font-size:13px}
.cart-layout{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
@media(max-width:720px){.cart-layout{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--sky)}
.card-header h3{font-family:var(--serif);font-size:17px;color:var(--deep2)}
.cart-item{display:flex;align-items:center;gap:14px;padding:16px 22px;border-bottom:1px solid var(--border);transition:background 0.15s}.cart-item:last-child{border-bottom:none}.cart-item:hover{background:var(--sky)}
.item-img{width:56px;height:56px;border-radius:10px;object-fit:cover;border:1px solid var(--border);flex-shrink:0}
.item-img-placeholder{width:56px;height:56px;border-radius:10px;background:var(--sky2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.item-info{flex:1;min-width:0}
.item-name{font-family:var(--serif);font-size:15px;font-weight:600;color:var(--deep2);margin-bottom:2px}
.item-size{display:inline-block;background:var(--sky2);color:var(--text-soft);border:1px solid var(--border-dark);padding:2px 8px;border-radius:10px;font-size:10px;font-family:var(--mono);margin-bottom:3px}
.item-price{font-size:12px;font-family:var(--mono);color:var(--text-muted)}
.item-controls{display:flex;align-items:center;gap:8px}
.qty-btn{width:28px;height:28px;border-radius:50%;border:1.5px solid var(--border-dark);background:var(--sky);color:var(--water);font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s}.qty-btn:hover{background:var(--water);color:#fff;border-color:var(--water)}
.qty-display{font-family:var(--mono);font-size:14px;font-weight:600;color:var(--deep2);min-width:24px;text-align:center}
.item-subtotal{font-family:var(--serif);font-size:16px;font-weight:700;color:var(--water);min-width:80px;text-align:right}
.remove-btn{background:none;border:none;color:var(--text-muted);font-size:16px;cursor:pointer;padding:4px;transition:color 0.15s}.remove-btn:hover{color:#c04a00}
.autobuy-section{background:var(--sky);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px}
.autobuy-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:0}
.autobuy-label{font-size:13px;font-weight:600;color:var(--deep2);display:flex;align-items:center;gap:8px}
.autobuy-sub{font-size:11px;font-family:var(--mono);color:var(--text-muted);margin-top:3px}
.toggle-wrap{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle-wrap input{opacity:0;width:100%;height:100%;position:absolute;cursor:pointer;z-index:1;margin:0}
.toggle-track{position:absolute;inset:0;background:var(--border-dark);border-radius:12px;transition:background 0.2s}
.toggle-wrap input:checked ~ .toggle-track{background:var(--water)}
.toggle-thumb{position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform 0.2s;pointer-events:none}
.toggle-wrap input:checked ~ .toggle-thumb{transform:translateX(20px)}
.autobuy-settings{margin-top:12px;padding-top:12px;border-top:1px dashed var(--border-dark);display:none}
.autobuy-settings.visible{display:block}
.autobuy-active-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(0,112,255,0.1);color:var(--water);border:1px solid rgba(0,112,255,0.25);padding:4px 12px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:600;margin-top:8px}
.cancel-autobuy{font-size:11px;color:#c04a00;font-family:var(--mono);cursor:pointer;text-decoration:underline;background:none;border:none;padding:0;margin-left:8px}
.summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;position:sticky;top:84px}
.summary-header{background:var(--navy);padding:18px 22px}
.summary-header h3{font-family:var(--serif);font-size:18px;color:#e6f4ff}
.summary-header p{font-size:12px;color:rgba(168,212,245,0.5);font-family:var(--mono);margin-top:2px}
.summary-body{padding:22px}
.summary-row{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;font-size:13px;gap:8px}
.summary-label{color:var(--text-muted);flex:1}
.summary-value{font-family:var(--mono);color:var(--deep2);font-weight:600;white-space:nowrap}
.summary-total{display:flex;justify-content:space-between;align-items:center;padding-top:14px;border-top:2px dashed var(--border-dark);margin-top:4px}
.summary-total-label{font-family:var(--serif);font-size:16px;color:var(--deep2)}
.summary-total-value{font-family:var(--serif);font-size:24px;font-weight:700;color:var(--water)}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
label{font-size:11px;font-family:var(--mono);color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
input,select,textarea{background:var(--sky);border:1.5px solid var(--border);border-radius:10px;color:var(--text);font-family:var(--sans);font-size:13px;padding:10px 14px;outline:none;width:100%;transition:border-color 0.2s}
input:focus,select:focus,textarea:focus{border-color:var(--water-bright)}
select option{background:#e6f4ff}
textarea{resize:vertical;min-height:70px}
.name-display{background:var(--sky2);border:1.5px solid var(--border-dark);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--deep2);font-weight:600}
.wallet-breakdown{display:none;background:linear-gradient(135deg,var(--navy) 0%,var(--deep2) 100%);border-radius:10px;padding:16px;margin-top:10px}
.wallet-breakdown.visible{display:block}
.wb-row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px}
.wb-label{color:rgba(168,212,245,0.65);font-family:var(--mono)}
.wb-val{color:#e6f4ff;font-family:var(--mono);font-weight:600}
.wb-divider{border:none;border-top:1px solid rgba(168,212,245,0.15);margin:10px 0}
.wb-remaining{display:flex;justify-content:space-between;font-size:15px}
.wb-remaining-label{color:rgba(168,212,245,0.75);font-family:var(--serif)}
.wb-remaining-val{color:var(--water-light);font-family:var(--serif);font-weight:700;font-size:18px}
.wb-insufficient{color:#e07040;font-size:12px;font-family:var(--mono);margin-top:8px;display:flex;gap:6px;align-items:center}
.checkout-btn{width:100%;padding:14px;background:var(--water);color:#fff;border:none;border-radius:30px;font-family:var(--sans);font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;margin-top:16px}
.checkout-btn:hover{background:var(--deep2);transform:translateY(-1px);box-shadow:0 8px 24px rgba(0,112,255,0.35)}
.checkout-btn:disabled{background:var(--sky2);color:var(--text-muted);cursor:not-allowed;transform:none;box-shadow:none}
.divider{border:none;border-top:1px dashed var(--border);margin:14px 0}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,26,77,0.55);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal-overlay.open{display:flex}
.modal{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:400px;width:90%;box-shadow:0 24px 60px rgba(0,26,77,0.25)}
.modal-icon{font-size:40px;margin-bottom:12px;display:block;text-align:center}
.modal h3{font-family:var(--serif);font-size:20px;color:var(--deep2);margin-bottom:8px;text-align:center}
.modal p{font-size:13px;color:var(--text-muted);margin-bottom:20px;text-align:center;line-height:1.6}
.modal-actions{display:flex;gap:10px;justify-content:center}
.modal-confirm{padding:10px 24px;background:var(--water);color:#fff;border:none;border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:700;cursor:pointer}.modal-confirm:hover{background:var(--deep2)}
.modal-cancel{padding:10px 24px;background:var(--sky);color:var(--text-soft);border:1.5px solid var(--border);border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer}
.toast-bar{position:fixed;bottom:28px;right:28px;background:var(--navy);color:#e6f4ff;padding:13px 22px;border-radius:30px;font-weight:700;font-size:13px;opacity:0;transition:opacity 0.3s,transform 0.4s cubic-bezier(.34,1.56,.64,1);transform:translateY(80px);pointer-events:none;z-index:999;box-shadow:0 8px 24px rgba(0,26,77,0.3)}
.toast-bar.show{opacity:1;transform:translateY(0)}
.toast-bar.error{background:#c04a00}
.footer{background:var(--navy);color:rgba(168,212,245,0.4);text-align:center;padding:20px;font-size:12px;font-family:var(--mono);margin-top:60px}
.alert-error{background:rgba(192,74,0,0.1);border:1px solid rgba(192,74,0,0.25);color:#c04a00;padding:12px 16px;border-radius:10px;font-size:13px;font-family:var(--mono);margin-bottom:16px}
.btn-warm{color:#fff;background:var(--water)}.btn-warm:hover{background:var(--water-mid);transform:translateY(-1px)}
</style>
</head>
<body>
<div class="topbar">
  <div class="logo">Aqua<span>luxe</span></div>
  <div class="topbar-right">
    <a href="shop.php" class="btn btn-warm">Home</a>
    <a href="orders.php" class="btn btn-outline">My Orders</a>
    <a href="wallet.php" class="btn btn-outline">💳 ₱<?= number_format($wallet_balance,2) ?></a>
    <a href="../auth/logout.php" class="logout-link btn btn-outline">logout</a>
  </div>
</div>

<div class="content">
  <a href="shop.php" class="back-link">← Back to menu</a>
  <h1 class="page-title">Your <span>Order</span></h1>
  <p class="page-sub"><?= count($items) ?> item<?= count($items)!=1?'s':'' ?> in your cart</p>

  <?php if (isset($wallet_error)): ?>
    <div class="alert-error">✕ Insufficient Wallet . Balance: ₱<?= number_format($wallet_balance,2) ?> — Total: ₱<?= number_format($grand_total,2) ?>. <a href="wallet.php" style="color:var(--rose)">Top up →</a></div>
  <?php endif; ?>

  <?php if (empty($items)): ?>
    <div class="empty">
      <span class="icon">🧋</span>
      <h3>Your cart is empty</h3>
      <p>Looks like you haven't added anything yet.</p>
      <a href="shop.php">Browse our menu</a>
    </div>
  <?php else: ?>
  <div class="cart-layout">
    <div>
      <div class="card">
        <div class="card-header">
          <h3>Order Items</h3>
          <span style="font-size:11px;font-family:var(--mono);color:var(--text-muted)"><?= count($items) ?> item<?= count($items)!=1?'s':'' ?></span>
        </div>
        <?php foreach ($items as $item): ?>
        <div class="cart-item" id="row-<?= $item['Cart_ID'] ?>">
          <?php if (!empty($item['Product_Image'])): ?>
            <img class="item-img" src="../<?= htmlspecialchars($item['Product_Image']) ?>" alt="">
          <?php else: ?>
            <div class="item-img-placeholder">🧋</div>
          <?php endif; ?>
          <div class="item-info">
            <div class="item-name"><?= htmlspecialchars($item['Product_Name']) ?></div>
            <?php if (!empty($item['Size_Name'])): ?>
              <span class="item-size"><?= htmlspecialchars($item['Size_Label']) ?> — <?= htmlspecialchars($item['Size_Name']) ?></span>
            <?php endif; ?>
            <div class="item-price">₱<?= number_format($item['Unit_Price'],2) ?> each</div>
          </div>
          <div class="item-controls">
            <button class="qty-btn" onclick="changeQty(<?= $item['Cart_ID'] ?>, -1, <?= $item['Unit_Price'] ?>)">−</button>
            <span class="qty-display" id="qty-<?= $item['Cart_ID'] ?>"><?= $item['Quantity'] ?></span>
            <button class="qty-btn" onclick="changeQty(<?= $item['Cart_ID'] ?>, 1, <?= $item['Unit_Price'] ?>)" <?= $item['Quantity']>=$item['Product_Quantity_Stock']?'disabled style=opacity:0.4':'' ?>>+</button>
          </div>
          <div class="item-subtotal" id="sub-<?= $item['Cart_ID'] ?>">₱<?= number_format($item['Subtotal'],2) ?></div>
          <button class="remove-btn" onclick="removeItem(<?= $item['Cart_ID'] ?>)">✕</button>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Auto-Buy -->
      <div class="autobuy-section">
        <div class="autobuy-header">
          <div>
            <div class="autobuy-label">🔁 Auto-Buy</div>
            <div class="autobuy-sub">Automatically reorder this cart using Wallet </div>
          </div>
          <label class="toggle-wrap">
            <input type="checkbox" id="autobuyToggle" onchange="handleAutobuyToggle(this.checked)">
            <div class="toggle-track"></div>
            <div class="toggle-thumb"></div>
          </label>
        </div>
        <div class="autobuy-settings" id="autobuySettings">
          <div class="form-group" style="margin-bottom:10px">
            <label>Repeat every</label>
            <select id="autobuyDay">
              <option value="1">Every day</option>
              <option value="2">Every 2 days</option>
              <option value="3">Every 3 days</option>
              <option value="7" selected>Every week</option>
              <option value="14">Every 2 weeks</option>
              <option value="30">Every month</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:10px">
            <label>Starting from</label>
            <input type="date" id="autobuyDate" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
          <button type="button" class="btn" style="background:var(--brown);color:var(--cream);width:100%;justify-content:center" onclick="confirmAutobuy()">🔁 Enable Auto-Buy</button>
        </div>
        <div id="autobuyActive" style="display:none">
          <div class="autobuy-active-badge">🔁 Auto-Buy is ON <button class="cancel-autobuy" onclick="cancelAutobuy()">Cancel</button></div>
          <div style="font-size:11px;font-family:var(--mono);color:var(--text-muted);margin-top:6px" id="autobuyInfo"></div>
        </div>
      </div>
    </div>

    <!-- Summary + checkout -->
    <div>
      <div class="summary-card">
        <div class="summary-header">
          <h3>Order Summary</h3>
          <p>Review before placing</p>
        </div>
        <div class="summary-body">
          <?php foreach ($items as $item): ?>
          <div class="summary-row" id="srow-<?= $item['Cart_ID'] ?>">
            <span class="summary-label"><?= htmlspecialchars($item['Product_Name']) ?><?php if($item['Size_Label']): ?><br><span style="font-size:10px;opacity:0.7"><?= htmlspecialchars($item['Size_Label']) ?></span><?php endif; ?> ×<span class="qty-lbl-<?= $item['Cart_ID'] ?>"><?= $item['Quantity'] ?></span></span>
            <span class="summary-value" id="sval-<?= $item['Cart_ID'] ?>">₱<?= number_format($item['Subtotal'],2) ?></span>
          </div>
          <?php endforeach; ?>
          <div class="divider"></div>
          <div class="summary-total">
            <span class="summary-total-label">Total</span>
            <span class="summary-total-value" id="grandTotal">₱<?= number_format($grand_total,2) ?></span>
          </div>

          <!-- FIX: hidden input ensures $_POST['checkout'] is always set when form submits -->
          <form method="POST" style="margin-top:20px" id="checkoutForm">
            <input type="hidden" name="checkout" value="1">
            <input type="hidden" name="Customer_Name" value="<?= htmlspecialchars($customer_name) ?>">

            <div class="form-group">
              <label>Your Name</label>
              <div class="name-display">👤 <?= htmlspecialchars($customer_name) ?></div>
            </div>

            <div class="form-group">
              <label>Payment Method</label>
              <!-- FIX: single select named Payment_Type_ID — wallet is a valid option value -->
              <select name="Payment_Type_ID" id="paymentSelect" onchange="handlePaymentChange(this.value)" required>
                <option value="">— Select payment —</option>
                <?php $payment_types->data_seek(0); while ($pt=$payment_types->fetch_assoc()): ?>
                  <option value="<?= $pt['Payment_Type_ID'] ?>"><?= htmlspecialchars($pt['Payment_Type_Description']) ?></option>
                <?php endwhile; ?>
                <option value="wallet">💳 Wallet  (balance: ₱<?= number_format($wallet_balance,2) ?>)</option>
              </select>

              <div class="wallet-breakdown" id="walletBreakdown">
                <div class="wb-row">
                  <span class="wb-label">Your balance</span>
                  <span class="wb-val">₱<?= number_format($wallet_balance,2) ?></span>
                </div>
                <div class="wb-row">
                  <span class="wb-label">Order total</span>
                  <span class="wb-val" id="wbTotal">₱<?= number_format($grand_total,2) ?></span>
                </div>
                <hr class="wb-divider">
                <div class="wb-remaining">
                  <span class="wb-remaining-label">Remaining</span>
                  <span class="wb-remaining-val" id="wbRemaining">₱<?= number_format($wallet_balance - $grand_total,2) ?></span>
                </div>
                <?php if ($wallet_balance < $grand_total): ?>
                  <div class="wb-insufficient">✕ Need ₱<?= number_format($grand_total - $wallet_balance,2) ?> more. <a href="wallet.php" style="color:var(--rose)">Top up →</a></div>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label>Note / Special Request <span style="font-size:10px;text-transform:none;letter-spacing:0;color:var(--text-muted)">(optional)</span></label>
              <textarea name="Order_Note" rows="3" placeholder="e.g. Less sugar, extra pearls, no ice..."></textarea>
            </div>

            <!-- FIX: type="button" → calls handleCheckout() which shows confirm modal for wallet, then submits form -->
            <button type="button" class="checkout-btn" id="checkoutBtn" disabled onclick="handleCheckout()">
              Select a payment method
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="footer">💧 Aqualuxe — Pure water, pure care, delivered to your door</div>

<!-- Wallet confirm modal -->
<div class="modal-overlay" id="walletModal">
  <div class="modal">
    <span class="modal-icon">💳</span>
    <h3>Confirm with Wallet </h3>
    <p id="walletModalBody"></p>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeWalletModal()">Cancel</button>
      <button class="modal-confirm" onclick="submitOrder()">Yes, place order</button>
    </div>
  </div>
</div>

<!-- Auto-buy confirm modal -->
<div class="modal-overlay" id="autobuyModal">
  <div class="modal">
    <span class="modal-icon">🔁</span>
    <h3>Enable Auto-Buy?</h3>
    <p id="autobuyModalBody"></p>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeAutobuyModal()">Cancel</button>
      <button class="modal-confirm" onclick="activateAutobuy()">Yes, enable</button>
    </div>
  </div>
</div>

<!-- Auto-buy cancel modal -->
<div class="modal-overlay" id="autobuyOffModal">
  <div class="modal">
    <span class="modal-icon">⏹️</span>
    <h3>Cancel Auto-Buy?</h3>
    <p>Your cart will no longer be ordered automatically. You can re-enable it anytime.</p>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="document.getElementById('autobuyOffModal').classList.remove('open')">Keep it on</button>
      <button class="modal-confirm" style="background:var(--rose)" onclick="deactivateAutobuy()">Yes, cancel</button>
    </div>
  </div>
</div>

<div class="toast-bar" id="toastEl"></div>

<script>
const walletBalance = <?= $wallet_balance ?>;
let grandTotal      = <?= $grand_total ?>;
let useWallet       = false;

function handlePaymentChange(val) {
  const breakdown = document.getElementById('walletBreakdown');
  const btn       = document.getElementById('checkoutBtn');
  useWallet = (val === 'wallet');
  if (useWallet) {
    breakdown.classList.add('visible');
    updateWalletBreakdown();
    const canAfford = walletBalance >= grandTotal;
    btn.disabled    = !canAfford;
    btn.textContent = canAfford ? 'Pay with Wallet  — ₱' + fmt(grandTotal) : 'Insufficient Wallet ';
  } else if (val) {
    breakdown.classList.remove('visible');
    btn.disabled    = false;
    btn.textContent = 'Place Order — ₱' + fmt(grandTotal);
  } else {
    breakdown.classList.remove('visible');
    btn.disabled    = true;
    btn.textContent = 'Select a payment method';
  }
}

function updateWalletBreakdown() {
  const remaining = walletBalance - grandTotal;
  document.getElementById('wbTotal').textContent     = '₱' + fmt(grandTotal);
  document.getElementById('wbRemaining').textContent = '₱' + fmt(remaining);
  document.getElementById('wbRemaining').style.color = remaining >= 0 ? 'var(--brown-light)' : 'var(--rose)';
}

function handleCheckout() {
  if (useWallet) {
    const remaining = walletBalance - grandTotal;
    document.getElementById('walletModalBody').innerHTML =
      `<strong>Balance:</strong> ₱${fmt(walletBalance)}<br>
       <strong>Order total:</strong> ₱${fmt(grandTotal)}<br>
       <strong>Remaining after:</strong> ₱${fmt(remaining)}<br><br>
       Wallet  will be deducted immediately.`;
    document.getElementById('walletModal').classList.add('open');
  } else {
    submitOrder();
  }
}

function closeWalletModal() {
  document.getElementById('walletModal').classList.remove('open');
}

// FIX: form.submit() works because hidden input name="checkout" value="1" is always present
function submitOrder() {
  document.getElementById('walletModal').classList.remove('open');
  document.getElementById('checkoutForm').submit();
}

function handleAutobuyToggle(checked) {
  if (checked) {
    document.getElementById('autobuySettings').classList.add('visible');
    document.getElementById('autobuyActive').style.display = 'none';
  } else {
    document.getElementById('autobuySettings').classList.remove('visible');
  }
}

function confirmAutobuy() {
  const day  = document.getElementById('autobuyDay');
  const date = document.getElementById('autobuyDate').value;
  if (!date) { showToast('Please select a start date.', true); return; }
  const dayLabel = day.options[day.selectedIndex].text;
  document.getElementById('autobuyModalBody').innerHTML =
    `Auto-Buy will place this cart order <strong>${dayLabel}</strong>, starting <strong>${date}</strong>, using your Wallet  (₱${fmt(grandTotal)} per order).<br><br>
     It will cancel automatically if your balance drops below ₱${fmt(grandTotal)}.`;
  document.getElementById('autobuyModal').classList.add('open');
}

function closeAutobuyModal() { document.getElementById('autobuyModal').classList.remove('open'); }

function activateAutobuy() {
  const day = document.getElementById('autobuyDay');
  const date = document.getElementById('autobuyDate').value;
  const dayLabel = day.options[day.selectedIndex].text;
  document.getElementById('autobuyModal').classList.remove('open');
  document.getElementById('autobuySettings').classList.remove('visible');
  document.getElementById('autobuyActive').style.display = 'block';
  document.getElementById('autobuyInfo').textContent = `${dayLabel} · starts ${date} · ₱${fmt(grandTotal)}/order`;
  localStorage.setItem('autobuy', JSON.stringify({ day: day.value, dayLabel, date, total: grandTotal }));
  showToast(`🔁 Auto-Buy enabled! ${dayLabel} starting ${date}`);
}

function cancelAutobuy() { document.getElementById('autobuyOffModal').classList.add('open'); }

function deactivateAutobuy() {
  localStorage.removeItem('autobuy');
  document.getElementById('autobuyOffModal').classList.remove('open');
  document.getElementById('autobuyActive').style.display = 'none';
  document.getElementById('autobuyToggle').checked = false;
  document.getElementById('autobuySettings').classList.remove('visible');
  showToast('Auto-Buy cancelled.');
}

window.addEventListener('load', () => {
  const saved = localStorage.getItem('autobuy');
  if (saved) {
    try {
      const ab = JSON.parse(saved);
      if (walletBalance < ab.total) {
        localStorage.removeItem('autobuy');
        showToast('⚠ Auto-Buy cancelled — insufficient Wallet  for ₱' + fmt(ab.total), true);
        return;
      }
      document.getElementById('autobuyToggle').checked = true;
      document.getElementById('autobuyActive').style.display = 'block';
      document.getElementById('autobuyInfo').textContent = `${ab.dayLabel} · starts ${ab.date} · ₱${fmt(ab.total)}/order`;
    } catch(e) { localStorage.removeItem('autobuy'); }
  }
});

let qtys   = {<?php foreach($items as $i) echo $i['Cart_ID'].':'.$i['Quantity'].','; ?>};
let prices = {<?php foreach($items as $i) echo $i['Cart_ID'].':'.floatval($i['Unit_Price']).','; ?>};

function changeQty(cid, delta, price) {
  const newQty = (qtys[cid]||1) + delta;
  if (newQty < 1) { removeItem(cid); return; }
  fetch('cart_action.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=update&cart_id=${cid}&quantity=${newQty}`})
  .then(r=>r.json()).then(()=>{
    qtys[cid]=newQty;
    document.getElementById('qty-'+cid).textContent=newQty;
    document.querySelectorAll('.qty-lbl-'+cid).forEach(el=>el.textContent=newQty);
    const sub=(newQty*prices[cid]).toFixed(2);
    document.getElementById('sub-'+cid).textContent='₱'+fmt(sub);
    document.getElementById('sval-'+cid).textContent='₱'+fmt(sub);
    recalc();
  });
}

function removeItem(cid) {
  fetch('cart_action.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=remove&cart_id=${cid}`})
  .then(r=>r.json()).then(()=>{
    document.getElementById('row-'+cid)?.remove();
    document.getElementById('srow-'+cid)?.remove();
    delete qtys[cid]; delete prices[cid];
    recalc();
    if(Object.keys(prices).length===0) location.reload();
  });
}

function recalc() {
  let total=0;
  Object.keys(prices).forEach(cid=>{total+=(qtys[cid]||0)*prices[cid];});
  grandTotal=total;
  const f=fmt(total.toFixed(2));
  document.getElementById('grandTotal').textContent='₱'+f;
  if (useWallet) { updateWalletBreakdown(); }
  const val=document.getElementById('paymentSelect').value;
  const btn=document.getElementById('checkoutBtn');
  if (useWallet) {
    const canAfford=walletBalance>=grandTotal;
    btn.disabled=!canAfford;
    btn.textContent=canAfford?'Pay with Wallet  — ₱'+f:'Insufficient Wallet ';
  } else if (val) {
    btn.disabled=false;
    btn.textContent='Place Order — ₱'+f;
  }
}

function showToast(msg, isError=false) {
  const t=document.getElementById('toastEl');
  t.textContent=msg;
  t.className='toast-bar'+(isError?' error':'');
  setTimeout(()=>t.classList.add('show'),50);
  setTimeout(()=>t.classList.remove('show'),4000);
}

function fmt(n){return parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}

['walletModal','autobuyModal','autobuyOffModal'].forEach(id=>{
  document.getElementById(id).addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
});
</script>
</body>
</html>