<?php
require '../config.php';
$pageTitle = 'Shop';
require_once '../includes/auth_check.php';
require_login();

// Search
$search = $conn->real_escape_string($_GET['search'] ?? '');
$where  = $search ? "WHERE Product_Status='Active' AND (Product_Name LIKE '%$search%' OR Product_Description LIKE '%$search%')" : "WHERE Product_Status='Active'";
$products = $conn->query("SELECT * FROM Products $where ORDER BY Product_Name");

// Cart count
$uid = $_SESSION['user_id'];
$cart_count = $conn->query("SELECT SUM(Quantity) AS c FROM Cart WHERE User_ID=$uid")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop — ShopAdmin</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#0e0f11;--surface:#16181c;--card:#1c1f25;--border:#2a2d35;--accent:#4ade80;--accent2:#22d3ee;--danger:#f87171;--warn:#fbbf24;--text:#e8eaf0;--muted:#6b7280;--radius:10px;--mono:'DM Mono',monospace;--sans:'Syne',sans-serif}
    body{background:var(--bg);color:var(--text);font-family:var(--sans);min-height:100vh}
    .topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
    .topbar-logo{font-size:18px;font-weight:700;color:var(--accent)}.topbar-logo span{color:var(--text)}
    .topbar-right{display:flex;align-items:center;gap:16px}
    .cart-btn{display:flex;align-items:center;gap:8px;padding:8px 16px;background:var(--accent);color:#0e0f11;border-radius:var(--radius);font-weight:700;font-size:13px;text-decoration:none;transition:background 0.15s}
    .cart-btn:hover{background:#22c55e}
    .cart-count{background:#0e0f11;color:var(--accent);border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:11px;font-family:var(--mono)}
    .user-info{font-size:13px;color:var(--muted)}.user-info span{color:var(--text);font-weight:600}
    .logout-link{font-size:12px;font-family:var(--mono);color:var(--danger);text-decoration:none}
    .content{padding:32px}
    .search-bar{display:flex;gap:12px;margin-bottom:28px}
    .search-bar input{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--sans);font-size:14px;padding:11px 16px;outline:none;flex:1;transition:border-color 0.15s}
    .search-bar input:focus{border-color:var(--accent)}
    .search-bar button{padding:11px 20px;background:var(--accent);color:#0e0f11;border:none;border-radius:var(--radius);font-family:var(--sans);font-size:13px;font-weight:700;cursor:pointer}
    .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px}
    .product-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;flex-direction:column;gap:10px}
    .product-name{font-size:15px;font-weight:700}
    .product-desc{font-size:12px;color:var(--muted);line-height:1.5;flex:1}
    .product-price{font-size:22px;font-weight:700;color:var(--accent);font-family:var(--mono)}
    .product-stock{font-size:11px;font-family:var(--mono);color:var(--muted)}
    .add-cart-btn{width:100%;padding:10px;background:var(--accent);color:#0e0f11;border:none;border-radius:var(--radius);font-family:var(--sans);font-size:13px;font-weight:700;cursor:pointer;transition:background 0.15s}
    .add-cart-btn:hover{background:#22c55e}
    .add-cart-btn:disabled{background:var(--border);color:var(--muted);cursor:not-allowed}
    .toast{position:fixed;bottom:24px;right:24px;background:var(--accent);color:#0e0f11;padding:12px 20px;border-radius:var(--radius);font-weight:700;font-size:13px;opacity:0;transition:opacity 0.3s;pointer-events:none;z-index:999}
    .toast.show{opacity:1}
  </style>
</head>
<body>
<div class="topbar">
  <div class="topbar-logo">shop<span>store</span></div>
  <div class="topbar-right">
    <span class="user-info">Hi, <span><?= htmlspecialchars($_SESSION['full_name']) ?></span></span>
    <a href="orders.php" class="logout-link" style="color:var(--accent2)">My Orders</a>
    <a href="cart.php" class="cart-btn">🛒 Cart <span class="cart-count" id="cartCount"><?= $cart_count ?></span></a>
    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </div>
</div>
<div class="content">
  <form class="search-bar" method="GET">
    <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
    <?php if ($search): ?><a href="shop.php" style="padding:11px 16px;color:var(--muted);font-size:13px;display:flex;align-items:center">Clear</a><?php endif; ?>
  </form>
  <div class="products-grid">
    <?php while ($p = $products->fetch_assoc()): ?>
    <div class="product-card">
      <div class="product-name"><?= htmlspecialchars($p['Product_Name']) ?></div>
      <div class="product-desc"><?= htmlspecialchars($p['Product_Description'] ?? '—') ?></div>
      <div class="product-price">₱<?= number_format($p['Product_Price'],2) ?></div>
      <div class="product-stock"><?= $p['Product_Quantity_Stock'] ?> in stock</div>
      <button class="add-cart-btn" onclick="addToCart(<?= $p['Product_ID'] ?>, this)"
              <?= $p['Product_Quantity_Stock'] < 1 ? 'disabled' : '' ?>>
        <?= $p['Product_Quantity_Stock'] < 1 ? 'Out of Stock' : '+ Add to Cart' ?>
      </button>
    </div>
    <?php endwhile; ?>
  </div>
</div>
<div class="toast" id="toast">Added to cart!</div>
<script>
function addToCart(productId, btn) {
  btn.disabled = true; btn.textContent = 'Adding...';
  fetch('cart_action.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=add&product_id=' + productId
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false; btn.textContent = '+ Add to Cart';
    document.getElementById('cartCount').textContent = data.cart_count;
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
  });
}
</script>
</body>
</html>
