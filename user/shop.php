<?php
session_start();
require '../config.php';

$search   = $conn->real_escape_string($_GET['search'] ?? '');
$where    = $search
    ? "WHERE Product_Status='Active' AND (Product_Name LIKE '%$search%' OR Product_Description LIKE '%$search%')"
    : "WHERE Product_Status='Active'";
$products = $conn->query("SELECT * FROM products $where ORDER BY Product_Name");

$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
$cart_count   = 0;
if ($is_logged_in) {
    $uid        = $_SESSION['user_id'];
    $cart_count = $conn->query("SELECT SUM(Quantity) AS c FROM cart WHERE User_ID=$uid")->fetch_assoc()['c'] ?? 0;
}

// Load global sizes
$sizes = [];
$sr = $conn->query("SELECT * FROM sizes ORDER BY Sort_Order ASC");
while ($s = $sr->fetch_assoc()) $sizes[] = $s;
$defaultSize = $sizes[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sip & Savor — Milk Tea Shop</title>
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
    .logo{display:flex;align-items:center;gap:10px}
    .logo-icon{font-size:26px}
    .logo-text{font-family:var(--serif);font-size:22px;color:var(--cream)}.logo-text span{color:var(--brown-light)}
    .topbar-right{display:flex;align-items:center;gap:12px}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all 0.2s}
    .btn-outline{color:var(--cream2);background:transparent;border:1.5px solid rgba(253,246,238,0.25)}.btn-outline:hover{background:rgba(253,246,238,0.1)}
    .btn-warm{color:var(--brown-deep);background:var(--brown-light)}.btn-warm:hover{background:var(--cream2);transform:translateY(-1px)}
    .btn-cart{color:var(--brown-deep);background:var(--cream2)}.btn-cart:hover{background:var(--cream);transform:translateY(-1px)}
    .cart-count{background:var(--brown);color:var(--cream);border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:11px;font-family:var(--mono)}
    .user-chip{font-size:13px;color:var(--cream2);font-weight:500}
    .logout-link{font-size:12px;color:rgba(253,246,238,0.4);text-decoration:none;font-family:var(--mono);transition:color 0.15s}.logout-link:hover{color:var(--rose)}

    .hero{background:linear-gradient(135deg,var(--brown-deep) 0%,#5c2d0e 50%,var(--brown-dark) 100%);padding:64px 40px;text-align:center;position:relative;overflow:hidden}
    .hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23c8956c' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
    .hero-content{position:relative;z-index:1}
    .hero-tag{display:inline-block;background:rgba(200,149,108,0.2);color:var(--brown-light);border:1px solid rgba(200,149,108,0.3);padding:5px 16px;border-radius:20px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:16px}
    .hero-title{font-family:var(--serif);font-size:48px;color:var(--cream);line-height:1.15;margin-bottom:12px}.hero-title span{color:var(--brown-light)}
    .hero-sub{font-size:15px;color:rgba(253,246,238,0.6);max-width:480px;margin:0 auto 32px;line-height:1.6}
    .search-bar{display:flex;max-width:480px;margin:0 auto;border-radius:30px;overflow:hidden;background:var(--cream);box-shadow:0 4px 24px rgba(0,0,0,0.2)}
    .search-bar input{background:transparent;border:none;color:var(--text);font-family:var(--sans);font-size:14px;padding:14px 22px;outline:none;flex:1}.search-bar input::placeholder{color:var(--text-muted)}
    .search-bar button{padding:14px 24px;background:var(--brown);color:var(--cream);border:none;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:background 0.15s}.search-bar button:hover{background:var(--brown-dark)}
    
    /* Guest Banner Container */
    .guest-banner {
      background: var(--cream2); /* Darker background makes text pop */
      border-bottom: 1px solid var(--border-dark);
      padding: 10px 0;
      overflow: hidden; /* Hides the text outside the edges */
      position: relative;
      white-space: nowrap;
    }

    /* The moving track */
    .banner-track {
      display: flex;
      width: max-content;
      animation: slideLeft 30s linear infinite; /* Adjust speed here (30s) */
    }

    .banner-content {
      display: flex;
      align-items: center;
      gap: 60px; /* Space between messages */
      padding-right: 60px;
    }

    .banner-content span, .banner-content strong {
      font-size: 13px;
      color: var(--brown-light);
      font-family: var(--mono);
    }

    .banner-content strong {
      color: var(--brown-light);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    /* The Animation */
    @keyframes slideLeft {
      0% { transform: translateX(0); }
      100% { transform: translateX(-50%); } /* Move half the width for seamless loop */
    }

    /* Pause on hover so users can read */
    .guest-banner:hover .banner-track {
      animation-play-state: paused;
    }

    .content{padding:40px;max-width:1400px;margin:0 auto}
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
    .section-title{font-family:var(--serif);font-size:26px;color:var(--brown-dark)}.section-title span{color:var(--brown-light)}
    .section-count{font-size:12px;font-family:var(--mono);color:var(--text-muted);background:var(--cream3);padding:4px 12px;border-radius:20px}
    .clear-link{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);text-decoration:none;margin-bottom:20px;font-family:var(--mono)}.clear-link:hover{color:var(--brown)}

    .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px}
    .product-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column;transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s;position:relative}
    .product-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(155,106,62,0.15);border-color:var(--border-dark)}
    .product-img{width:100%;height:200px;object-fit:cover;display:block}
    .product-img-placeholder{width:100%;height:200px;background:linear-gradient(135deg,var(--cream2) 0%,var(--cream3) 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;border-bottom:1px solid var(--border)}
    .product-img-placeholder .icon{font-size:48px;opacity:0.5}
    .product-img-placeholder .label{font-size:11px;font-family:var(--mono);color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
    .product-body{padding:16px 18px 10px;flex:1;display:flex;flex-direction:column;gap:6px}
    .product-name{font-family:var(--serif);font-size:17px;font-weight:600;color:var(--brown-dark);line-height:1.3}
    .product-desc{font-size:12px;color:var(--text-muted);line-height:1.6;flex:1}

    /* Size pills */
    .size-section{padding:0 18px 14px}
    .size-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .size-label-text{font-size:11px;font-family:var(--mono);color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-right:2px}

    .size-pill{
      width:38px;height:38px;border-radius:50%;
      border:2px solid var(--border-dark);
      background:var(--cream2);color:var(--text-soft);
      font-family:var(--mono);font-size:13px;font-weight:800;
      cursor:pointer;transition:all 0.18s;
      display:flex;align-items:center;justify-content:center;
      position:relative;letter-spacing:0;
    }
    .size-pill:hover{border-color:var(--brown-light);background:var(--cream3);color:var(--brown-dark)}
    .size-pill.active{
      border-color:var(--brown);background:var(--brown);color:var(--cream);
      box-shadow:0 3px 10px rgba(155,106,62,0.35);
      transform:scale(1.08);
    }
    .size-pill .tooltip{
      position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);
      background:var(--brown-deep);color:var(--cream);
      padding:4px 10px;border-radius:8px;font-size:10px;white-space:nowrap;
      opacity:0;pointer-events:none;transition:opacity 0.15s;font-family:var(--sans);font-weight:600;
    }
    .size-pill .tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:4px solid transparent;border-top-color:var(--brown-deep)}
    .size-pill:hover .tooltip,.size-pill.active .tooltip{opacity:1}

    .product-footer{display:flex;align-items:center;justify-content:space-between;padding:10px 18px 12px;border-top:1px dashed var(--border)}
    .product-price{font-family:var(--serif);font-size:22px;font-weight:700;color:var(--brown);transition:all 0.2s}
    .product-stock{font-size:10px;font-family:var(--mono);color:var(--text-muted)}
    .stock-low{color:var(--rose)!important}

    .add-btn{margin:0 18px 18px;padding:11px;background:var(--brown);color:var(--cream);border:none;border-radius:30px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s}
    .add-btn:hover{background:var(--brown-dark);transform:translateY(-1px);box-shadow:0 4px 12px rgba(155,106,62,0.3)}
    .add-btn:disabled{background:var(--cream3);color:var(--text-muted);cursor:not-allowed;transform:none;box-shadow:none}
    .add-btn.guest{background:transparent;color:var(--brown);border:1.5px solid var(--brown-light)}.add-btn.guest:hover{background:var(--cream2)}

    .stock-badge{position:absolute;top:12px;right:12px;padding:4px 10px;border-radius:20px;font-size:10px;font-family:var(--mono);font-weight:500}
    .stock-badge.out{background:rgba(212,133,106,0.15);color:var(--rose);border:1px solid rgba(212,133,106,0.3)}
    .stock-badge.low{background:rgba(122,158,126,0.15);color:var(--sage);border:1px solid rgba(122,158,126,0.3)}

    .toast{position:fixed;bottom:28px;right:28px;background:var(--brown-deep);color:var(--cream);padding:13px 22px;border-radius:30px;font-weight:600;font-size:13px;opacity:0;transition:opacity 0.3s,transform 0.3s;transform:translateY(8px);pointer-events:none;z-index:999;box-shadow:0 8px 24px rgba(61,31,10,0.3)}
    .toast.show{opacity:1;transform:translateY(0)}
    .empty{text-align:center;padding:80px 20px;color:var(--text-muted)}
    .empty .icon{font-size:56px;margin-bottom:16px;opacity:0.4}
    .empty p{font-size:15px;font-family:var(--serif)}.empty a{color:var(--brown);text-decoration:none;font-weight:600}
    .footer{background:var(--brown-deep);color:rgba(253,246,238,0.4);text-align:center;padding:24px;font-size:12px;font-family:var(--mono);margin-top:60px}
  </style>
</head>
<body>
<div class="topbar">
  <div class="logo">
    <span class="logo-icon">🧋</span>
    <div class="logo-text">Sip &amp; <span>Savor</span></div>
  </div>
  <div class="topbar-right">
    <?php if ($is_logged_in): ?>
      <span class="user-chip">Hi, <?= htmlspecialchars($_SESSION['full_name']) ?> 👋</span>
      <a href="orders.php" class="btn btn-outline">My Orders</a>
      <a href="cart.php" class="btn btn-cart">🛒 Cart <span class="cart-count" id="cartCount"><?= $cart_count ?></span></a>
      <a href="../auth/logout.php" class="logout-link">logout</a>
    <?php else: ?>
      <a href="../auth/login.php?redirect=user/shop.php" class="btn btn-outline">Sign In</a>
      <a href="../auth/register.php" class="btn btn-warm">Join Us</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$is_logged_in): ?>
<div class="guest-banner">
  <div class="banner-track">
    <div class="banner-content">
      <span>🍵 Welcome! Browse our menu freely.</span>
      <strong>Sign in to order your favorites.</strong>
      <span>✨ Check out our new Strawberry Series!</span>
      <strong>🧋 Get 10% off on your first order.</strong>
    </div>
    <div class="banner-content">
      <span>🍵 Welcome! Browse our menu freely.</span>
      <strong>Sign in to order your favorites.</strong>
      <span>✨ Check out our new Strawberry Series!</span>
      <strong>🧋 Get 10% off on your first order.</strong>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="hero">
  <div class="hero-content">
    <div class="hero-tag">☕ Fresh &amp; Handcrafted</div>
    <h1 class="hero-title">Every sip tells a<br><span>sweet story</span></h1>
    <p class="hero-sub">Crafted with the finest tea, fresh milk, and love. Find your perfect cup today.</p>
    <form class="search-bar" method="GET">
      <input type="text" name="search" placeholder="Search for your favorite drink..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      <button type="submit">Search</button>
    </form>
  </div>
</div>

<div class="content">
  <?php if ($search): ?>
    <a href="shop.php" class="clear-link">← Back to full menu</a>
  <?php endif; ?>
  <div class="section-header">
    <h2 class="section-title"><?= $search ? 'Results for <span>"'.htmlspecialchars($search).'"</span>' : 'Our <span>Menu</span>' ?></h2>
    <span class="section-count"><?= $products->num_rows ?> item<?= $products->num_rows != 1 ? 's' : '' ?></span>
  </div>

  <?php if ($products->num_rows === 0): ?>
    <div class="empty">
      <div class="icon">🍵</div>
      <p>No drinks found. <a href="shop.php">See full menu</a></p>
    </div>
  <?php else: ?>
  <div class="products-grid">
    <?php while ($p = $products->fetch_assoc()): ?>
    <div class="product-card">
      <?php if ($p['Product_Quantity_Stock'] < 1): ?>
        <span class="stock-badge out">sold out</span>
      <?php elseif ($p['Product_Quantity_Stock'] <= 5): ?>
        <span class="stock-badge low">only <?= $p['Product_Quantity_Stock'] ?> left</span>
      <?php endif; ?>

      <?php if (!empty($p['Product_Image'])): ?>
        <img class="product-img" src="../<?= htmlspecialchars($p['Product_Image']) ?>" alt="<?= htmlspecialchars($p['Product_Name']) ?>">
      <?php else: ?>
        <div class="product-img-placeholder">
          <div class="icon">🧋</div>
          <div class="label">Photo coming soon</div>
        </div>
      <?php endif; ?>

      <div class="product-body">
        <div class="product-name"><?= htmlspecialchars($p['Product_Name']) ?></div>
        <?php if (!empty($p['Product_Description'])): ?>
          <div class="product-desc"><?= htmlspecialchars($p['Product_Description']) ?></div>
        <?php endif; ?>
      </div>

      <?php if (!empty($sizes)): ?>
      <div class="size-section">
        <div class="size-row">
          <span class="size-label-text">Size</span>
          <?php foreach ($sizes as $idx => $sz): ?>
          <button type="button"
            class="size-pill <?= $idx === 0 ? 'active' : '' ?>"
            onclick="selectSize(<?= $p['Product_ID'] ?>, <?= $sz['Size_ID'] ?>, <?= $sz['Size_Price'] ?>, <?= $p['Product_Price'] ?>, this)"
            data-size-id="<?= $sz['Size_ID'] ?>"
            data-addon="<?= $sz['Size_Price'] ?>">
            <?= htmlspecialchars($sz['Size_Label']) ?>
            <span class="tooltip"><?= htmlspecialchars($sz['Size_Name']) ?> — ₱<?= number_format($p['Product_Price'] + $sz['Size_Price'], 2) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="product-footer">
        <div class="product-price" id="price-<?= $p['Product_ID'] ?>">
          ₱<?= number_format($p['Product_Price'] + ($defaultSize ? $defaultSize['Size_Price'] : 0), 2) ?>
        </div>
        <div class="product-stock <?= $p['Product_Quantity_Stock'] <= 5 && $p['Product_Quantity_Stock'] > 0 ? 'stock-low' : '' ?>">
          <?= $p['Product_Quantity_Stock'] ?> available
        </div>
      </div>

      <?php if ($p['Product_Quantity_Stock'] < 1): ?>
        <button class="add-btn" disabled>Sold Out</button>
      <?php elseif ($is_logged_in): ?>
        <button class="add-btn"
          id="addbtn-<?= $p['Product_ID'] ?>"
          data-product-id="<?= $p['Product_ID'] ?>"
          data-selected-size="<?= $defaultSize ? $defaultSize['Size_ID'] : '' ?>"
          onclick="addToCart(<?= $p['Product_ID'] ?>, this)">
          + Add to Order
        </button>
      <?php else: ?>
        <button class="add-btn guest" onclick="window.location='../auth/login.php?redirect=user/shop.php'">Sign in to Order</button>
      <?php endif; ?>
    </div>
    <?php endwhile; ?>
  </div>
  <?php endif; ?>
</div>

<div class="footer">🧋 Sip &amp; Savor Milk Tea — Made with love &amp; the finest ingredients</div>
<div class="toast" id="toast">🧋 Added to your order!</div>

<script>
const selectedSizes = {};

function selectSize(productId, sizeId, addon, basePrice, btn) {
  const addBtn = document.getElementById('addbtn-' + productId);
  const priceDisplay = document.getElementById('price-' + productId);
  
  // Check if this button is already active (the "Double Click" logic)
  if (btn.classList.contains('active')) {
    // DESELECT: Remove active state and reset data
    btn.classList.remove('active');
    delete selectedSizes[productId];
    
    // Revert price to just the Base Price
    priceDisplay.textContent = '₱' + parseFloat(basePrice).toLocaleString('en-PH', {minimumFractionDigits:2});
    
    // Clear the size from the Add to Cart button
    if (addBtn) addBtn.dataset.selectedSize = '';
  } else {
    // SELECT: Normal behavior
    // Remove active class from all other buttons in this specific product card
    btn.closest('.size-row').querySelectorAll('.size-pill').forEach(b => b.classList.remove('active'));
    
    // Activate this button
    btn.classList.add('active');
    selectedSizes[productId] = sizeId;
    
    // Update price with the addon
    const total = parseFloat(basePrice) + parseFloat(addon);
    priceDisplay.textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
    
    // Update the Add to Cart button with the new Size ID
    if (addBtn) addBtn.dataset.selectedSize = sizeId;
  }
}

function addToCart(productId, btn) {
  const sizeId = btn.dataset.selectedSize || '';
  
  // Validation: Don't allow adding if no size is selected
  if (!sizeId) {
    alert("Please select a size first!");
    return;
  }

  btn.disabled = true; 
  btn.textContent = 'Adding...';
  
  fetch('cart_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=add&product_id=${productId}&size_id=${sizeId}`
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false; 
    btn.textContent = '+ Add to Order';
    document.getElementById('cartCount').textContent = data.cart_count;
    
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
  });
}

</script>
</body>
</html>