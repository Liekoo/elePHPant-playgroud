<?php
require '../config.php';
require_once '../includes/auth_check.php';
require_login();
$uid = $_SESSION['user_id'];

$cart_items = $conn->query("
    SELECT c.Cart_ID, c.Quantity, c.Size_ID,
           p.Product_ID, p.Product_Name, p.Product_Price, p.Product_Quantity_Stock, p.Product_Image,
           s.Size_Label, s.Size_Name, s.Size_Price,
           /* Calculate the price for one unit: Base + Size Addon */
           (p.Product_Price + COALESCE(s.Size_Price, 0)) AS Unit_Price,
           /* Calculate the total for this row: Quantity * (Base + Size Addon) */
           (c.Quantity * (p.Product_Price + COALESCE(s.Size_Price, 0))) AS Subtotal
    FROM cart c
    JOIN products p ON c.Product_ID = p.Product_ID
    LEFT JOIN sizes s ON c.Size_ID = s.Size_ID
    WHERE c.User_ID = $uid
");

$items = [];
$grand_total = 0;
while ($r = $cart_items->fetch_assoc()) { $items[] = $r; $grand_total += $r['Subtotal']; }

$payment_types = $conn->query("SELECT * FROM payments_type");
$customer_name = $_SESSION['full_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $payment_id   = (int)$_POST['Payment_Type_ID'];
    $cust_name    = $conn->real_escape_string(trim($_POST['Customer_Name']));
    $note         = $conn->real_escape_string(trim($_POST['Order_Note'] ?? ''));
    // Use first available customer_type or default 1
    $ctype_row    = $conn->query("SELECT Customer_Type_ID FROM customer_type LIMIT 1")->fetch_assoc();
    $customer_id  = $ctype_row ? $ctype_row['Customer_Type_ID'] : 1;

    foreach ($items as $item) {
        $pid       = $item['Product_ID'];
        $sid       = $item['Size_ID'] ? $item['Size_ID'] : 'NULL';
        $qty       = $item['Quantity'];
        $price     = $item['Unit_Price'];
        $conn->query("INSERT INTO orders (User_ID, Product_ID, Size_ID, Customer_Type_ID, Payment_Type_ID, Order_Quantity, Product_Price, Customer_Name, Order_Note)
                      VALUES ($uid, $pid, $sid, $customer_id, $payment_id, $qty, $price, '$cust_name', '$note')");
        $conn->query("UPDATE products SET Product_Quantity_Stock = Product_Quantity_Stock - $qty WHERE Product_ID = $pid");
    }
    $conn->query("DELETE FROM cart WHERE User_ID = $uid");
    header('Location: orders.php?success=1'); exit;
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
    .logout-link{font-size:12px;color:rgba(253,246,238,0.4);text-decoration:none;font-family:var(--mono);transition:color 0.15s}.logout-link:hover{color:var(--rose)}
    .content{max-width:900px;margin:0 auto;padding:40px 24px}
    .page-title{font-family:var(--serif);font-size:32px;color:var(--brown-dark);margin-bottom:6px}
    .page-title span{color:var(--brown-light)}
    .page-sub{font-size:13px;color:var(--text-muted);margin-bottom:32px;font-family:var(--mono)}
    .back-link{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);text-decoration:none;margin-bottom:24px;font-family:var(--mono);transition:color 0.15s}.back-link:hover{color:var(--brown)}
    .empty{text-align:center;padding:80px 20px}
    .empty .icon{font-size:64px;margin-bottom:16px;opacity:0.4;display:block}
    .empty h3{font-family:var(--serif);font-size:22px;color:var(--brown-dark);margin-bottom:8px}
    .empty p{font-size:14px;color:var(--text-muted);margin-bottom:20px}
    .empty a{display:inline-block;padding:11px 28px;background:var(--brown);color:var(--cream);border-radius:30px;text-decoration:none;font-weight:600;font-size:13px;transition:all 0.2s}.empty a:hover{background:var(--brown-dark);transform:translateY(-1px)}
    .cart-layout{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
    @media(max-width:720px){.cart-layout{grid-template-columns:1fr}}
    .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
    .card-header{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--cream2)}
    .card-header h3{font-family:var(--serif);font-size:17px;color:var(--brown-dark)}
    .card-header span{font-size:11px;font-family:var(--mono);color:var(--text-muted)}
    .cart-item{display:flex;align-items:center;gap:14px;padding:16px 22px;border-bottom:1px solid var(--border);transition:background 0.15s}.cart-item:last-child{border-bottom:none}.cart-item:hover{background:var(--cream2)}
    .item-img{width:56px;height:56px;border-radius:10px;object-fit:cover;border:1px solid var(--border);flex-shrink:0}
    .item-img-placeholder{width:56px;height:56px;border-radius:10px;background:var(--cream3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
    .item-info{flex:1;min-width:0}
    .item-name{font-family:var(--serif);font-size:15px;font-weight:600;color:var(--brown-dark);margin-bottom:2px}
    .item-size{display:inline-block;background:var(--cream3);color:var(--text-soft);border:1px solid var(--border-dark);padding:2px 8px;border-radius:10px;font-size:10px;font-family:var(--mono);margin-bottom:3px}
    .item-price{font-size:12px;font-family:var(--mono);color:var(--text-muted)}
    .item-controls{display:flex;align-items:center;gap:8px}
    .qty-btn{width:28px;height:28px;border-radius:50%;border:1.5px solid var(--border-dark);background:var(--cream2);color:var(--brown);font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;line-height:1}
    .qty-btn:hover{background:var(--brown);color:var(--cream);border-color:var(--brown)}
    .qty-display{font-family:var(--mono);font-size:14px;font-weight:600;color:var(--brown-dark);min-width:24px;text-align:center}
    .item-subtotal{font-family:var(--serif);font-size:16px;font-weight:700;color:var(--brown);min-width:80px;text-align:right}
    .remove-btn{background:none;border:none;color:var(--text-muted);font-size:16px;cursor:pointer;padding:4px;border-radius:6px;transition:color 0.15s;line-height:1}.remove-btn:hover{color:var(--rose)}
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
    .name-display{background:var(--cream3);border:1.5px solid var(--border-dark);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--brown-dark);font-weight:600;display:flex;align-items:center;gap:8px}
    .checkout-btn{width:100%;padding:14px;background:var(--brown);color:var(--cream);border:none;border-radius:30px;font-family:var(--sans);font-size:15px;font-weight:700;cursor:pointer;transition:all 0.2s;margin-top:16px}
    .checkout-btn:hover{background:var(--brown-dark);transform:translateY(-1px);box-shadow:0 8px 24px rgba(155,106,62,0.35)}
    .divider{border:none;border-top:1px dashed var(--border);margin:14px 0}
    .footer{background:var(--brown-deep);color:rgba(253,246,238,0.4);text-align:center;padding:20px;font-size:12px;font-family:var(--mono);margin-top:60px}
  </style>
</head>
<body>
<div class="topbar">
  <div class="logo">Sip &amp; <span>Savor</span></div>
  <div class="topbar-right">
    <a href="orders.php" class="btn btn-outline">My Orders</a>
    <a href="../auth/logout.php" class="logout-link">logout</a>
  </div>
</div>

<div class="content">
  <a href="shop.php" class="back-link">← Back to menu</a>
  <h1 class="page-title">Your <span>Order</span></h1>
  <p class="page-sub"><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?> in your cart</p>

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
          <span><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?></span>
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
              <span class="item-size"><?= htmlspecialchars($item["Size_Label"] ?? "") ?> — <?= htmlspecialchars($item["Size_Name"] ?? "") ?></span>
            <?php endif; ?>
            <div class="item-price">₱<?= number_format($item['Unit_Price'], 2) ?> each</div>
          </div>
          <div class="item-controls">
            <button class="qty-btn" onclick="changeQty(<?= $item['Cart_ID'] ?>, -1, <?= $item['Unit_Price'] ?>)">−</button>
            <span class="qty-display" id="qty-<?= $item['Cart_ID'] ?>"><?= $item['Quantity'] ?></span>
            <button class="qty-btn" onclick="changeQty(<?= $item['Cart_ID'] ?>, 1, <?= $item['Unit_Price'] ?>)" <?= $item['Quantity'] >= $item['Product_Quantity_Stock'] ? 'disabled style=opacity:0.4' : '' ?>>+</button>
          </div>
          <div class="item-subtotal" id="sub-<?= $item['Cart_ID'] ?>">₱<?= number_format($item['Subtotal'], 2) ?></div>
          <button class="remove-btn" onclick="removeItem(<?= $item['Cart_ID'] ?>)" title="Remove">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Summary -->
    <div>
      <div class="summary-card">
        <div class="summary-header">
          <h3>Order Summary</h3>
          <p>Review before placing</p>
        </div>
        <div class="summary-body">
          <?php foreach ($items as $item): ?>
          <div class="summary-row" id="srow-<?= $item['Cart_ID'] ?>">
            <span class="summary-label">
              <?= htmlspecialchars($item['Product_Name']) ?>
              <?php if ($item['Size_Name']): ?><br><span style="font-size:10px;opacity:0.7"><?= htmlspecialchars($item["Size_Label"] ?? "") ?> — <?= htmlspecialchars($item["Size_Name"] ?? "") ?></span><?php endif; ?>
              ×<?= $item['Quantity'] ?>
            </span>
            <span class="summary-value" id="sval-<?= $item['Cart_ID'] ?>">₱<?= number_format($item['Subtotal'], 2) ?></span>
          </div>
          <?php endforeach; ?>
          <div class="divider"></div>
          <div class="summary-total">
            <span class="summary-total-label">Total</span>
            <span class="summary-total-value" id="grandTotal">₱<?= number_format($grand_total, 2) ?></span>
          </div>

          <form method="POST" style="margin-top:20px">
            <div class="form-group">
              <label>Your Name</label>
              <div class="name-display">👤 <?= htmlspecialchars($customer_name) ?></div>
              <input type="hidden" name="Customer_Name" value="<?= htmlspecialchars($customer_name) ?>">
            </div>
            <div class="form-group">
              <label>Payment Method</label>
              <select name="Payment_Type_ID" required>
                <?php while ($pt = $payment_types->fetch_assoc()): ?>
                  <option value="<?= $pt['Payment_Type_ID'] ?>"><?= htmlspecialchars($pt['Payment_Type_Description']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Note / Special Request <span style="font-size:10px;text-transform:none;letter-spacing:0;color:var(--text-muted)">(optional)</span></label>
              <textarea name="Order_Note" rows="3" placeholder="e.g. Less sugar, extra pearls, no ice..."></textarea>
            </div>
            <button type="submit" name="checkout" class="checkout-btn" id="checkoutBtn">
              Place Order ☕ — ₱<?= number_format($grand_total, 2) ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="footer">🧋 Sip &amp; Savor Milk Tea — Made with love &amp; the finest ingredients</div>

<script>
let qtys  = {<?php foreach($items as $i) echo $i['Cart_ID'].':'.$i['Quantity'].','; ?>};
let prices = {<?php foreach($items as $i) echo $i['Cart_ID'].':'.floatval($i['Unit_Price']).','; ?>};

function changeQty(cid, delta, price) {
  const newQty = (qtys[cid] || 1) + delta;
  if (newQty < 1) { removeItem(cid); return; }
  fetch('cart_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=update&cart_id=${cid}&quantity=${newQty}`})
  .then(r=>r.json()).then(()=>{
    qtys[cid] = newQty;
    document.getElementById('qty-'+cid).textContent = newQty;
    const sub = (newQty * prices[cid]).toFixed(2);
    document.getElementById('sub-'+cid).textContent = '₱'+fmt(sub);
    document.getElementById('sval-'+cid).textContent = '₱'+fmt(sub);
    // update qty in summary label
    const srow = document.getElementById('srow-'+cid);
    if (srow) {
      const label = srow.querySelector('.summary-label');
      label.innerHTML = label.innerHTML.replace(/×\d+/, '×'+newQty);
    }
    recalc();
  });
}

function removeItem(cid) {
  fetch('cart_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=remove&cart_id=${cid}`})
  .then(r=>r.json()).then(()=>{
    document.getElementById('row-'+cid)?.remove();
    document.getElementById('srow-'+cid)?.remove();
    delete qtys[cid]; delete prices[cid];
    recalc();
    if (Object.keys(prices).length === 0) location.reload();
  });
}

function recalc() {
  let total = 0;
  Object.keys(prices).forEach(cid => { total += (qtys[cid]||0) * prices[cid]; });
  const f = fmt(total.toFixed(2));
  document.getElementById('grandTotal').textContent = '₱'+f;
  document.getElementById('checkoutBtn').textContent = `Place Order ☕ — ₱${f}`;
}

function fmt(n) { return parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
</script>
</body>
</html>