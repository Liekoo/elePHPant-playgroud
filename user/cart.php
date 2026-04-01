<?php
require '../config.php';
require_once '../includes/auth_check.php';
require_login();
$uid = $_SESSION['user_id'];

$cart_items = $conn->query("
    SELECT c.Cart_ID, c.Quantity, p.Product_ID, p.Product_Name, p.Product_Price, p.Product_Quantity_Stock,
           (c.Quantity * p.Product_Price) AS Subtotal
    FROM Cart c JOIN Products p ON c.Product_ID = p.Product_ID
    WHERE c.User_ID = $uid
");

$items = [];
$grand_total = 0;
while ($r = $cart_items->fetch_assoc()) { $items[] = $r; $grand_total += $r['Subtotal']; }

$payment_types = $conn->query("SELECT * FROM Payments_Type");
$customer_types = $conn->query("SELECT * FROM Customer_Type");

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $payment_id  = (int)$_POST['Payment_Type_ID'];
    $customer_id = (int)$_POST['Customer_Type_ID'];

    foreach ($items as $item) {
        $pid   = $item['Product_ID'];
        $qty   = $item['Quantity'];
        $price = $item['Product_Price'];
        $conn->query("INSERT INTO Orders (User_ID, Product_ID, Customer_Type_ID, Payment_Type_ID, Order_Quantity, Product_Price)
                      VALUES ($uid, $pid, $customer_id, $payment_id, $qty, $price)");
        $conn->query("UPDATE Products SET Product_Quantity_Stock = Product_Quantity_Stock - $qty WHERE Product_ID = $pid");
    }
    $conn->query("DELETE FROM Cart WHERE User_ID = $uid");
    header('Location: orders.php?success=1'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cart — ShopAdmin</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#0e0f11;--surface:#16181c;--card:#1c1f25;--border:#2a2d35;--accent:#4ade80;--danger:#f87171;--warn:#fbbf24;--text:#e8eaf0;--muted:#6b7280;--radius:10px;--mono:'DM Mono',monospace;--sans:'Syne',sans-serif}
    body{background:var(--bg);color:var(--text);font-family:var(--sans);min-height:100vh}
    .topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
    .topbar-logo{font-size:18px;font-weight:700;color:var(--accent)}.topbar-logo span{color:var(--text)}
    .topbar-right{display:flex;align-items:center;gap:16px}
    .back-link{font-size:13px;color:var(--accent);text-decoration:none;font-weight:600}
    .logout-link{font-size:12px;font-family:var(--mono);color:var(--danger);text-decoration:none}
    .content{padding:32px;max-width:860px;margin:0 auto}
    h1{font-size:22px;font-weight:700;margin-bottom:24px}.accent{color:var(--accent)}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px}
    .card-title{font-size:12px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:16px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    thead th{text-align:left;padding:8px 12px;font-family:var(--mono);font-size:11px;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border)}
    tbody td{padding:12px;border-bottom:1px solid rgba(255,255,255,0.04);vertical-align:middle}
    .qty-input{width:60px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-family:var(--mono);font-size:13px;padding:5px 8px;text-align:center;outline:none}
    .remove-btn{background:rgba(248,113,113,0.15);color:var(--danger);border:1px solid rgba(248,113,113,0.3);padding:4px 10px;border-radius:6px;font-size:12px;cursor:pointer;font-family:var(--sans)}
    .total-row{display:flex;justify-content:space-between;align-items:center;padding:16px 0 0;border-top:1px solid var(--border);margin-top:8px}
    .total-label{font-size:14px;color:var(--muted);font-family:var(--mono)}
    .total-value{font-size:26px;font-weight:700;color:var(--accent);font-family:var(--mono)}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .form-group{display:flex;flex-direction:column;gap:6px}
    label{font-size:11px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px}
    select,input{background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--sans);font-size:13px;padding:10px 14px;outline:none;width:100%}
    select:focus{border-color:var(--accent)}
    select option{background:var(--surface)}
    .checkout-btn{width:100%;padding:14px;background:var(--accent);color:#0e0f11;border:none;border-radius:var(--radius);font-family:var(--sans);font-size:15px;font-weight:700;cursor:pointer;margin-top:16px;transition:background 0.15s}
    .checkout-btn:hover{background:#22c55e}
    .empty{text-align:center;padding:48px;color:var(--muted);font-size:15px}
    .empty a{color:var(--accent);text-decoration:none;font-weight:600}
    .mono{font-family:var(--mono)}
  </style>
</head>
<body>
<div class="topbar">
  <div class="topbar-logo">shop<span>cart</span></div>
  <div class="topbar-right">
    <a href="shop.php" class="back-link">← Continue Shopping</a>
    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </div>
</div>
<div class="content">
  <h1>Your <span class="accent">Cart</span></h1>

  <?php if (empty($items)): ?>
    <div class="empty">Your cart is empty. <a href="shop.php">Browse products</a></div>
  <?php else: ?>
  <div class="card">
    <div class="card-title">Cart Items</div>
    <table>
      <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr id="row-<?= $item['Product_ID'] ?>">
          <td><?= htmlspecialchars($item['Product_Name']) ?></td>
          <td class="mono">₱<?= number_format($item['Product_Price'],2) ?></td>
          <td>
            <input type="number" class="qty-input" value="<?= $item['Quantity'] ?>" min="1"
                   max="<?= $item['Product_Quantity_Stock'] ?>"
                   onchange="updateQty(<?= $item['Product_ID'] ?>, this.value)">
          </td>
          <td class="mono" id="sub-<?= $item['Product_ID'] ?>">₱<?= number_format($item['Subtotal'],2) ?></td>
          <td><button class="remove-btn" onclick="removeItem(<?= $item['Product_ID'] ?>)">Remove</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="total-row">
      <span class="total-label">Grand Total</span>
      <span class="total-value" id="grandTotal">₱<?= number_format($grand_total,2) ?></span>
    </div>
  </div>

  <form method="POST">
    <div class="card">
      <div class="card-title">Checkout Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Customer Type</label>
          <select name="Customer_Type_ID" required>
            <?php while ($ct = $customer_types->fetch_assoc()): ?>
              <option value="<?= $ct['Customer_Type_ID'] ?>"><?= htmlspecialchars($ct['Customer_Type_Description']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Payment Method</label>
          <select name="Payment_Type_ID" required>
            <?php while ($pt = $payment_types->fetch_assoc()): ?>
              <option value="<?= $pt['Payment_Type_ID'] ?>"><?= htmlspecialchars($pt['Payment_Type_Description']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
      <button type="submit" name="checkout" class="checkout-btn">✓ Place Order — ₱<?= number_format($grand_total,2) ?></button>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
let prices = {<?php foreach($items as $i) echo $i['Product_ID'].':'.$i['Product_Price'].','; ?>};

function updateQty(pid, qty) {
  fetch('cart_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=update&product_id=${pid}&quantity=${qty}`})
  .then(r=>r.json()).then(()=>recalc());
}

function removeItem(pid) {
  fetch('cart_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=remove&product_id=${pid}`})
  .then(r=>r.json()).then(()=>{ document.getElementById('row-'+pid).remove(); recalc(); });
}

function recalc() {
  let total = 0;
  document.querySelectorAll('.qty-input').forEach(inp => {
    const pid = inp.closest('tr').id.replace('row-','');
    const sub = parseInt(inp.value) * prices[pid];
    document.getElementById('sub-'+pid).textContent = '₱' + sub.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    total += sub;
  });
  document.getElementById('grandTotal').textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}
</script>
</body>
</html>
