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

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $payment_id  = $_POST['Payment_Type_ID'];
    $note        = $conn->real_escape_string(trim($_POST['Order_Note'] ?? ''));
    $use_wallet  = ($payment_id === 'wallet');
    $cust_name   = $conn->real_escape_string($customer_name);
    $ctype_row   = $conn->query("SELECT Customer_Type_ID FROM customer_type LIMIT 1")->fetch_assoc();
    $customer_id = $ctype_row ? $ctype_row['Customer_Type_ID'] : 1;

    if ($use_wallet && $wallet_balance < $grand_total) {
        $wallet_error = true;
    } else {
        if ($use_wallet) {
            $ptRow      = $conn->query("SELECT Payment_Type_ID FROM payments_type LIMIT 1")->fetch_assoc();
            $actual_pid = $ptRow ? $ptRow['Payment_Type_ID'] : 1;
        } else {
            $actual_pid = (int)$payment_id;
        }

        foreach ($items as $item) {
            $pid   = $item['Product_ID'];
            $sid   = $item['Size_ID'] ? $item['Size_ID'] : 'NULL';
            $qty   = $item['Quantity'];
            $price = $item['Unit_Price'];
            $fn    = $use_wallet ? ($note ? $note.' [Sip Credits]' : 'Paid with Sip Credits') : $note;
            $fne   = $conn->real_escape_string($fn);
            $conn->query("INSERT INTO orders (User_ID,Product_ID,Size_ID,Customer_Type_ID,Payment_Type_ID,Order_Quantity,Product_Price,Customer_Name,Order_Note)
                          VALUES ($uid,$pid,$sid,$customer_id,$actual_pid,$qty,$price,'$cust_name','$fne')");
            $conn->query("UPDATE products SET Product_Quantity_Stock=Product_Quantity_Stock-$qty WHERE Product_ID=$pid");
        }

        if ($use_wallet) {
            $new_bal = $wallet_balance - $grand_total;
            $conn->query("UPDATE users SET Wallet_Balance=$new_bal WHERE User_ID=$uid");
            $conn->query("INSERT INTO wallet_transactions (User_ID,Type,Amount,Balance_After,Note,Status)
                          VALUES ($uid,'purchase',$grand_total,$new_bal,'Order payment','approved')");
        }

        $conn->query("DELETE FROM cart WHERE User_ID=$uid");
        header('Location: orders.php?success=1'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Order — Sip & Savor</title>
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
    .btn-outline{color:var(--cream2);background:transparent;border:1.5px solid rgba(253,246,238,0.25)}.btn-outline:hover{background:rgba(253,246,238,0.1)}
    .logout-link{font-size:12px;color:rgba(253,246,238,0.4);text-decoration:none;font-family:var(--mono)}.logout-link:hover{color:var(--rose)}

    .content{max-width:900px;margin:0 auto;padding:40px 24px}
    .page-title{font-family:var(--serif);font-size:32px;color:var(--brown-dark);margin-bottom:6px}.page-title span{color:var(--brown-light)}
    .page-sub{font-size:13px;color:var(--text-muted);margin-bottom:32px;font-family:var(--mono)}
    .back-link{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);text-decoration:none;margin-bottom:24px;font-family:var(--mono)}.back-link:hover{color:var(--brown)}

    .empty{text-align:center;padding:80px 20px}
    .empty .icon{font-size:64px;margin-bottom:16px;opacity:0.4;display:block}
    .empty h3{font-family:var(--serif);font-size:22px;color:var(--brown-dark);margin-bottom:8px}
    .empty p{font-size:14px;color:var(--text-muted);margin-bottom:20px}
    .empty a{display:inline-block;padding:11px 28px;background:var(--brown);color:var(--cream);border-radius:30px;text-decoration:none;font-weight:600;font-size:13px}

    .cart-layout{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
    @media(max-width:720px){.cart-layout{grid-template-columns:1fr}}

    .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
    .card-header{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--cream2)}
    .card-header h3{font-family:var(--serif);font-size:17px;color:var(--brown-dark)}

    .cart-item{display:flex;align-items:center;gap:14px;padding:16px 22px;border-bottom:1px solid var(--border);transition:background 0.15s}.cart-item:last-child{border-bottom:none}.cart-item:hover{background:var(--cream2)}
    .item-img{width:56px;height:56px;border-radius:10px;object-fit:cover;border:1px solid var(--border);flex-shrink:0}
    .item-img-placeholder{width:56px;height:56px;border-radius:10px;background:var(--cream3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
    .item-info{flex:1;min-width:0}
    .item-name{font-family:var(--serif);font-size:15px;font-weight:600;color:var(--brown-dark);margin-bottom:2px}
    .item-size{display:inline-block;background:var(--cream3);color:var(--text-soft);border:1px solid var(--border-dark);padding:2px 8px;border-radius:10px;font-size:10px;font-family:var(--mono);margin-bottom:3px}
    .item-price{font-size:12px;font-family:var(--mono);color:var(--text-muted)}
    .item-controls{display:flex;align-items:center;gap:8px}
    .qty-btn{width:28px;height:28px;border-radius:50%;border:1.5px solid var(--border-dark);background:var(--cream2);color:var(--brown);font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s}.qty-btn:hover{background:var(--brown);color:var(--cream);border-color:var(--brown)}
    .qty-display{font-family:var(--mono);font-size:14px;font-weight:600;color:var(--brown-dark);min-width:24px;text-align:center}
    .item-subtotal{font-family:var(--serif);font-size:16px;font-weight:700;color:var(--brown);min-width:80px;text-align:right}
    .remove-btn{background:none;border:none;color:var(--text-muted);font-size:16px;cursor:pointer;padding:4px;transition:color 0.15s}.remove-btn:hover{color:var(--rose)}

    /* Auto-buy section */
    .autobuy-section{background:var(--cream2);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px}
    .autobuy-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:0}
    .autobuy-label{font-size:13px;font-weight:600;color:var(--brown-dark);display:flex;align-items:center;gap:8px}
    .autobuy-sub{font-size:11px;font-family:var(--mono);color:var(--text-muted);margin-top:3px}
    .toggle-wrap{position:relative;width:44px;height:24px;flex-shrink:0}
    .toggle-wrap input{opacity:0;width:100%;height:100%;position:absolute;cursor:pointer;z-index:1;margin:0}
    .toggle-track{position:absolute;inset:0;background:var(--border-dark);border-radius:12px;transition:background 0.2s}
    .toggle-wrap input:checked ~ .toggle-track{background:var(--brown)}
    .toggle-thumb{position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform 0.2s;pointer-events:none}
    .toggle-wrap input:checked ~ .toggle-thumb{transform:translateX(20px)}
    .autobuy-settings{margin-top:12px;padding-top:12px;border-top:1px dashed var(--border-dark);display:none}
    .autobuy-settings.visible{display:block}
    .autobuy-active-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(155,106,62,0.15);color:var(--brown);border:1px solid rgba(155,106,62,0.3);padding:4px 12px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:600;margin-top:8px}
    .cancel-autobuy{font-size:11px;color:var(--rose);font-family:var(--mono);cursor:pointer;text-decoration:underline;background:none;border:none;padding:0;margin-left:8px}

    /* Summary card */
    .summary-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;position:sticky;top:84px}
    .summary-header{background:var(--brown-deep);padding:18px 22px}
    .summary-header h3{font-family:var(--serif);font-size:18px;color:var(--cream)}
    .summary-header p{font-size:12px;color:rgba(253,246,238,0.5);font-family:var(--mono);margin-top:2px}
    .summary-body{padding:22px}
    .summary-row{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;font-size:13px;gap:8px}
    .summary-label{color:var(--text-muted);flex:1}
    .summary-value{font-family:var(--mono);color:var(--brown-dark);font-weight:600;white-space:nowrap}
    .summary-total{display:flex;justify-content:space-between;align-items:center;padding-top:14px;border-top:2px dashed var(--border-dark);margin-top:4px}
    .summary-total-label{font-family:var(--serif);font-size:16px;color:var(--brown-dark)}
    .summary-total-value{font-family:var(--serif);font-size:24px;font-weight:700;color:var(--brown)}

    .form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
    label{font-size:11px;font-family:var(--mono);color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
    input,select,textarea{background:var(--cream2);border:1.5px solid var(--border);border-radius:10px;color:var(--text);font-family:var(--sans);font-size:13px;padding:10px 14px;outline:none;width:100%;transition:border-color 0.2s}
    input:focus,select:focus,textarea:focus{border-color:var(--brown-light)}
    select option{background:var(--cream)}
    textarea{resize:vertical;min-height:70px}
    .name-display{background:var(--cream3);border:1.5px solid var(--border-dark);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--brown-dark);font-weight:600}

    /* Wallet breakdown (hidden until wallet selected) */
    .wallet-breakdown{display:none;background:linear-gradient(135deg,var(--brown-deep) 0%,#5c2d0e 100%);border-radius:10px;padding:16px;margin-top:10px}
    .wallet-breakdown.visible{display:block}
    .wb-row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px}
    .wb-label{color:rgba(253,246,238,0.6);font-family:var(--mono)}
    .wb-val{color:var(--cream);font-family:var(--mono);font-weight:600}
    .wb-divider{border:none;border-top:1px solid rgba(253,246,238,0.15);margin:10px 0}
    .wb-remaining{display:flex;justify-content:space-between;font-size:15px}
    .wb-remaining-label{color:rgba(253,246,238,0.7);font-family:var(--serif)}
    .wb-remaining-val{color:var(--brown-light);font-family:var(--serif);font-weight:700;font-size:18px}
    .wb-insufficient{color:var(--rose);font-size:12px;font-family:var(--mono);margin-top:8px;display:flex;gap:6px;align-items:center}

    .checkout-btn{width:100%;padding:14px;background:var(--brown);color:var(--cream);border:none;border-radius:30px;font-family:var(--sans);font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;margin-top:16px}
    .checkout-btn:hover{background:var(--brown-dark);transform:translateY(-1px);box-shadow:0 8px 24px rgba(155,106,62,0.35)}
    .checkout-btn:disabled{background:var(--cream3);color:var(--text-muted);cursor:not-allowed;transform:none;box-shadow:none}
    .divider{border:none;border-top:1px dashed var(--border);margin:14px 0}

    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(61,31,10,0.5);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:400px;width:90%;box-shadow:0 24px 60px rgba(61,31,10,0.25)}
    .modal-icon{font-size:40px;margin-bottom:12px;display:block;text-align:center}
    .modal h3{font-family:var(--serif);font-size:20px;color:var(--brown-dark);margin-bottom:8px;text-align:center}
    .modal p{font-size:13px;color:var(--text-muted);margin-bottom:20px;text-align:center;line-height:1.6}
    .modal-actions{display:flex;gap:10px;justify-content:center}
    .modal-confirm{padding:10px 24px;background:var(--brown);color:var(--cream);border:none;border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:700;cursor:pointer;transition:all 0.2s}.modal-confirm:hover{background:var(--brown-dark)}
    .modal-cancel{padding:10px 24px;background:var(--cream2);color:var(--text-soft);border:1.5px solid var(--border);border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer}

    /* Toast */
    .toast-bar{position:fixed;bottom:28px;right:28px;background:var(--brown-deep);color:var(--cream);padding:13px 22px;border-radius:30px;font-weight:700;font-size:13px;opacity:0;transition:opacity 0.3s,transform 0.4s cubic-bezier(.34,1.56,.64,1);transform:translateY(80px);pointer-events:none;z-index:999;box-shadow:0 8px 24px rgba(61,31,10,0.3)}
    .toast-bar.show{opacity:1;transform:translateY(0)}
    .toast-bar.error{background:var(--rose)}

    .footer{background:var(--brown-deep);color:rgba(253,246,238,0.4);text-align:center;padding:20px;font-size:12px;font-family:var(--mono);margin-top:60px}
    .alert-error{background:rgba(212,133,106,0.12);border:1px solid rgba(212,133,106,0.3);color:var(--rose);padding:12px 16px;border-radius:10px;font-size:13px;font-family:var(--mono);margin-bottom:16px}
  </style>
</head>
<body>
<div class="topbar">
  <div class="logo">Sip &amp; <span>Savor</span></div>
  <div class="topbar-right">
    <a href="orders.php" class="btn btn-outline">My Orders</a>
    <a href="wallet.php" class="btn btn-outline">💳 ₱<?= number_format($wallet_balance,2) ?></a>
    <a href="../auth/logout.php" class="logout-link">logout</a>
  </div>
</div>

<div class="content">
  <a href="shop.php" class="back-link">← Back to menu</a>
  <h1 class="page-title">Your <span>Order</span></h1>
  <p class="page-sub"><?= count($items) ?> item<?= count($items)!=1?'s':'' ?> in your cart</p>

  <?php if (isset($wallet_error)): ?>
    <div class="alert-error">✕ Insufficient Sip Credits. Balance: ₱<?= number_format($wallet_balance,2) ?> — Total: ₱<?= number_format($grand_total,2) ?>. <a href="wallet.php" style="color:var(--rose)">Top up →</a></div>
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

    <!-- Left: items + auto-buy -->
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
            <div class="autobuy-label">
              🔁 Auto-Buy
            </div>
            <div class="autobuy-sub">Automatically reorder this cart using Sip Credits</div>
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
          <button type="button" class="btn" style="background:var(--brown);color:var(--cream);width:100%;justify-content:center" onclick="confirmAutobuy()">
            🔁 Enable Auto-Buy
          </button>
        </div>
        <div id="autobuyActive" style="display:none">
          <div class="autobuy-active-badge">
            🔁 Auto-Buy is ON
            <button class="cancel-autobuy" onclick="cancelAutobuy()">Cancel</button>
          </div>
          <div style="font-size:11px;font-family:var(--mono);color:var(--text-muted);margin-top:6px" id="autobuyInfo"></div>
        </div>
      </div>
    </div>

    <!-- Right: summary + checkout -->
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

          <form method="POST" style="margin-top:20px" id="checkoutForm">
            <input type="hidden" name="Customer_Name" value="<?= htmlspecialchars($customer_name) ?>">
            <div class="form-group">
              <label>Your Name</label>
              <div class="name-display">👤 <?= htmlspecialchars($customer_name) ?></div>
            </div>

            <div class="form-group">
              <label>Payment Method</label>
              <select name="Payment_Type_ID" id="paymentSelect" onchange="handlePaymentChange(this.value)" required>
                <option value="">— Select payment —</option>
                <?php $payment_types->data_seek(0); while ($pt=$payment_types->fetch_assoc()): ?>
                  <option value="<?= $pt['Payment_Type_ID'] ?>"><?= htmlspecialchars($pt['Payment_Type_Description']) ?></option>
                <?php endwhile; ?>
                <option value="wallet">💳 Sip Credits (balance: ₱<?= number_format($wallet_balance,2) ?>)</option>
              </select>

              <!-- Wallet breakdown — only shown when wallet is selected -->
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
                  <div class="wb-insufficient">✕ Insufficient funds — need ₱<?= number_format($grand_total - $wallet_balance,2) ?> more. <a href="wallet.php" style="color:var(--rose)">Top up →</a></div>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label>Note / Special Request <span style="font-size:10px;text-transform:none;letter-spacing:0;color:var(--text-muted)">(optional)</span></label>
              <textarea name="Order_Note" rows="3" placeholder="e.g. Less sugar, extra pearls, no ice..."></textarea>
            </div>

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

<div class="footer">🧋 Sip &amp; Savor Milk Tea — Made with love &amp; the finest ingredients</div>

<!-- Wallet confirm modal -->
<div class="modal-overlay" id="walletModal">
  <div class="modal">
    <span class="modal-icon">💳</span>
    <h3>Confirm with Sip Credits</h3>
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

<!-- Toast -->
<div class="toast-bar" id="toastEl"></div>

<script>
const walletBalance = <?= $wallet_balance ?>;
let grandTotal      = <?= $grand_total ?>;
let useWallet       = false;
let autobuyActive   = false;

// ── Payment change ──────────────────────────────────────
function handlePaymentChange(val) {
  const breakdown = document.getElementById('walletBreakdown');
  const btn       = document.getElementById('checkoutBtn');
  useWallet = (val === 'wallet');

  if (useWallet) {
    breakdown.classList.add('visible');
    updateWalletBreakdown();
    const canAfford = walletBalance >= grandTotal;
    btn.disabled    = !canAfford;
    btn.textContent = canAfford
      ? 'Pay with Sip Credits — ₱' + fmt(grandTotal)
      : 'Insufficient Sip Credits';
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

// ── Checkout ─────────────────────────────────────────────
function handleCheckout() {
  if (useWallet) {
    const remaining = walletBalance - grandTotal;
    document.getElementById('walletModalBody').innerHTML =
      `<strong>Balance:</strong> ₱${fmt(walletBalance)}<br>
       <strong>Order total:</strong> ₱${fmt(grandTotal)}<br>
       <strong>Remaining after:</strong> ₱${fmt(remaining)}<br><br>
       Sip Credits will be deducted immediately.`;
    document.getElementById('walletModal').classList.add('open');
  } else {
    submitOrder();
  }
}

function closeWalletModal() {
  document.getElementById('walletModal').classList.remove('open');
}

function submitOrder() {
  document.getElementById('walletModal').classList.remove('open');
  document.getElementById('checkoutForm').submit();
}

// ── Auto-buy ─────────────────────────────────────────────
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
    `Auto-Buy will place this cart order <strong>${dayLabel}</strong>, starting <strong>${date}</strong>, using your Sip Credits (₱${fmt(grandTotal)} per order).<br><br>
     It will cancel automatically if your balance drops below ₱${fmt(grandTotal)}.`;
  document.getElementById('autobuyModal').classList.add('open');
}

function closeAutobuyModal() {
  document.getElementById('autobuyModal').classList.remove('open');
}

function activateAutobuy() {
  const day  = document.getElementById('autobuyDay');
  const date = document.getElementById('autobuyDate').value;
  const dayLabel  = day.options[day.selectedIndex].text;
  autobuyActive   = true;

  document.getElementById('autobuyModal').classList.remove('open');
  document.getElementById('autobuySettings').classList.remove('visible');
  document.getElementById('autobuyActive').style.display = 'block';
  document.getElementById('autobuyInfo').textContent = `${dayLabel} · starts ${date} · ₱${fmt(grandTotal)}/order`;

  // Store in localStorage so it persists
  localStorage.setItem('autobuy', JSON.stringify({ day: day.value, dayLabel, date, total: grandTotal }));
  showToast(`🔁 Auto-Buy enabled! ${dayLabel} starting ${date}`);
}

function cancelAutobuy() {
  document.getElementById('autobuyOffModal').classList.add('open');
}

function deactivateAutobuy() {
  autobuyActive = false;
  localStorage.removeItem('autobuy');
  document.getElementById('autobuyOffModal').classList.remove('open');
  document.getElementById('autobuyActive').style.display = 'none';
  document.getElementById('autobuyToggle').checked = false;
  document.getElementById('autobuySettings').classList.remove('visible');
  showToast('Auto-Buy cancelled.');
}

// Check localStorage on load
window.addEventListener('load', () => {
  const saved = localStorage.getItem('autobuy');
  if (saved) {
    try {
      const ab = JSON.parse(saved);
      // Check if wallet still has enough funds
      if (walletBalance < ab.total) {
        localStorage.removeItem('autobuy');
        showToast('⚠ Auto-Buy cancelled — insufficient Sip Credits for ₱' + fmt(ab.total), true);
        return;
      }
      autobuyActive = true;
      document.getElementById('autobuyToggle').checked = true;
      document.getElementById('autobuyActive').style.display = 'block';
      document.getElementById('autobuyInfo').textContent = `${ab.dayLabel} · starts ${ab.date} · ₱${fmt(ab.total)}/order`;
    } catch(e) { localStorage.removeItem('autobuy'); }
  }
});

// ── Cart qty/remove ───────────────────────────────────────
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
  if(useWallet) {
    updateWalletBreakdown();
    const btn=document.getElementById('checkoutBtn');
    const canAfford = walletBalance >= grandTotal;
    btn.disabled    = !canAfford;
    btn.textContent = canAfford ? 'Pay with Sip Credits — ₱'+f : 'Insufficient Sip Credits';
  } else {
    const val = document.getElementById('paymentSelect').value;
    if(val && val!=='') {
      const btn=document.getElementById('checkoutBtn');
      btn.disabled=false;
      btn.textContent='Place Order — ₱'+f;
    }
  }
}

// ── Toast ─────────────────────────────────────────────────
function showToast(msg, isError=false) {
  const t=document.getElementById('toastEl');
  t.textContent=msg;
  t.className='toast-bar'+(isError?' error':'');
  setTimeout(()=>t.classList.add('show'),50);
  setTimeout(()=>t.classList.remove('show'),4000);
}

function fmt(n){return parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}

// Close modals on backdrop click
['walletModal','autobuyModal','autobuyOffModal'].forEach(id=>{
  document.getElementById(id).addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
});
</script>
</body>
</html>