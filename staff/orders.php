<?php
/**
 * STAFF — staff/orders.php
 * -------------------------------------------------------
 * WHO SEES THIS: Staff role only
 * PURPOSE: Manage and process incoming orders via modal
 *
 * WORKFLOW STAFF CAN ACTION:
 *   Pending          → [Prepare]    → Preparing
 *   Preparing        → [Mark Ready] → Ready for Pickup
 *   Ready for Pickup → [Complete]   → Completed
 *   Pending/Preparing → [Cancel]    → Cancelled (stock restored)
 *
 * Staff CANNOT: delete orders, manage users, manage product price/stock
 * -------------------------------------------------------
 */
require '../config.php';
$pageTitle = 'Orders';

// Handle status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $action = $_GET['action'];
    $newStatus = match($action) {
        'prepare'  => 'Preparing',
        'ready'    => 'Ready for Pickup',
        'complete' => 'Completed',
        'cancel'   => 'Cancelled',
        default    => null
    };
    if ($newStatus) {
        $conn->query("UPDATE orders SET Order_Status='$newStatus' WHERE Order_ID=$id");
        if ($newStatus === 'Cancelled') {
            $order = $conn->query("SELECT Product_ID,Order_Quantity FROM orders WHERE Order_ID=$id")->fetch_assoc();
            if ($order) $conn->query("UPDATE products SET Product_Quantity_Stock=Product_Quantity_Stock+{$order['Order_Quantity']} WHERE Product_ID={$order['Product_ID']}");
        }
        $toast = match($newStatus) {
            'Preparing'        => 'preparing',
            'Ready for Pickup' => 'ready',
            'Completed'        => 'completed',
            'Cancelled'        => 'cancelled',
            default            => 'updated'
        };
    }
    header("Location: orders.php?filter=".($_GET['filter']??'all')."&toast=$toast"); exit;
}

$filter = $_GET['filter'] ?? 'all';
$view   = $_GET['view']   ?? 'active';

$whereClause = match($filter) {
    'pending'   => "WHERE o.Order_Status='Pending'",
    'preparing' => "WHERE o.Order_Status='Preparing'",
    'ready'     => "WHERE o.Order_Status='Ready for Pickup'",
    'done'      => "WHERE o.Order_Status IN ('Completed','Cancelled')",
    default     => "WHERE o.Order_Status NOT IN ('Completed','Cancelled')"
};

if ($view === 'logs') $whereClause = "WHERE o.Order_Status IN ('Completed','Cancelled')";

$orders = $conn->query("
    SELECT o.*, p.Product_Name, p.Product_Image, s.Size_Label, s.Size_Name,
           pt.Payment_Type_Description
    FROM orders o
    JOIN products p       ON o.Product_ID     = p.Product_ID
    JOIN payments_type pt ON o.Payment_Type_ID = pt.Payment_Type_ID
    LEFT JOIN sizes s     ON o.Size_ID         = s.Size_ID
    $whereClause
    ORDER BY
      FIELD(o.Order_Status,'Pending','Preparing','Ready for Pickup','Completed','Cancelled'),
      o.Order_Date_Time DESC
");

$counts = [];
foreach (['Pending','Preparing','Ready for Pickup','Completed','Cancelled'] as $st) {
    $esc = $conn->real_escape_string($st);
    $counts[$st] = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE Order_Status='$esc'")->fetch_assoc()['c'];
}
$counts['all']  = $counts['Pending'] + $counts['Preparing'] + $counts['Ready for Pickup'];
$activeCount    = $counts['all'];
$logsCount      = $counts['Completed'] + $counts['Cancelled'];

require '../includes/staff_header.php';
?>

<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal-overlay.open{display:flex}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,0.5)}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:600;color:var(--text);font-family:var(--mono)}
.modal-close{background:none;border:none;color:var(--muted);font-size:22px;cursor:pointer;line-height:1;transition:color 0.15s}.modal-close:hover{color:var(--danger)}
.toast-bar{position:fixed;bottom:28px;right:28px;padding:13px 22px;border-radius:30px;font-weight:700;font-size:13px;z-index:999;display:flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,0.3);transform:translateY(80px);opacity:0;transition:transform 0.4s cubic-bezier(.34,1.56,.64,1),opacity 0.3s;pointer-events:none}
.toast-bar.show{transform:translateY(0);opacity:1}
.toast-success{background:var(--accent2);color:#0e0f11}
.toast-ready{background:var(--accent);color:#0e0f11}
.toast-done{background:#7c3aed;color:#fff}
.toast-cancel{background:var(--danger);color:#fff}
.view-tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.view-tab{padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.15s;border:1px solid var(--border);color:var(--muted);background:var(--card)}
.view-tab.active{background:var(--accent);color:#0e0f11;border-color:var(--accent)}
.view-tab .cnt{border-radius:20px;padding:1px 7px;font-size:11px;font-family:var(--mono);background:rgba(0,0,0,0.15)}
.view-tab:not(.active) .cnt{background:var(--border);color:var(--text)}
.order-detail-row{display:flex;gap:8px;margin-bottom:8px;font-size:13px}
.order-detail-label{color:var(--muted);font-family:var(--mono);font-size:11px;text-transform:uppercase;letter-spacing:1px;min-width:80px;padding-top:2px}
.order-detail-val{color:var(--text);font-weight:500}
</style>

<div class="page-header">
  <h1 class="page-title">Or<span>ders</span></h1>
  <span style="font-size:12px;font-family:var(--mono);color:var(--muted)"><?= date('M d, Y · h:i A') ?></span>
</div>

<!-- Stat cards -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px">
  <?php $stats=[['Pending',$counts['Pending'],'--warn','🕐'],['Preparing',$counts['Preparing'],'--accent2','👨‍🍳'],['Ready for Pickup',$counts['Ready for Pickup'],'--accent','✅'],['Completed',$counts['Completed'],'--muted','☑️'],['Cancelled',$counts['Cancelled'],'--danger','✕']];
  foreach($stats as [$label,$count,$color,$icon]): ?>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden">
    <div style="height:3px;background:var(<?= $color ?>)"></div>
    <div style="padding:12px 14px">
      <div style="font-size:10px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px"><?= $icon ?> <?= $label ?></div>
      <div style="font-size:24px;font-weight:700;color:var(<?= $color ?>);letter-spacing:-1px"><?= $count ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pending alert -->
<?php if ($counts['Pending'] > 0): ?>
<div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.25);border-radius:10px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
  <span style="font-size:13px;color:var(--warn);font-family:var(--mono)">🕐 <?= $counts['Pending'] ?> order<?= $counts['Pending']!=1?'s':'' ?> waiting to be prepared</span>
  <a href="?filter=pending" class="btn btn-sm" style="background:var(--warn);color:#0e0f11;font-weight:700">View Pending →</a>
</div>
<?php endif; ?>

<!-- View tabs -->
<div class="view-tabs">
  <a href="?view=active&filter=all"       class="view-tab <?= $view==='active'&&$filter==='all'    ?'active':'' ?>">All Active <span class="cnt"><?= $activeCount ?></span></a>
  <a href="?view=active&filter=pending"   class="view-tab <?= $filter==='pending'                  ?'active':'' ?>">Pending <span class="cnt"><?= $counts['Pending'] ?></span></a>
  <a href="?view=active&filter=preparing" class="view-tab <?= $filter==='preparing'                ?'active':'' ?>">Preparing <span class="cnt"><?= $counts['Preparing'] ?></span></a>
  <a href="?view=active&filter=ready"     class="view-tab <?= $filter==='ready'                    ?'active':'' ?>">Ready <span class="cnt"><?= $counts['Ready for Pickup'] ?></span></a>
  <a href="?view=logs"                    class="view-tab <?= $view==='logs'                       ?'active':'' ?>">Order Logs <span class="cnt"><?= $logsCount ?></span></a>
</div>

<!-- Orders list -->
<?php if ($orders->num_rows === 0): ?>
  <div style="text-align:center;padding:60px;color:var(--muted);font-family:var(--mono);font-size:14px">No orders found for this filter.</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:10px">
  <?php while ($row = $orders->fetch_assoc()):
    $status = $row['Order_Status'];
    [$clr,$bgClr,$borderClr] = match($status) {
      'Pending'          => ['--warn',    'rgba(251,191,36,0.08)',  'rgba(251,191,36,0.25)'],
      'Preparing'        => ['--accent2', 'rgba(34,211,238,0.08)',  'rgba(34,211,238,0.25)'],
      'Ready for Pickup' => ['--accent',  'rgba(74,222,128,0.08)',  'rgba(74,222,128,0.25)'],
      'Completed'        => ['--muted',   'rgba(107,114,128,0.06)', 'rgba(107,114,128,0.2)'],
      'Cancelled'        => ['--danger',  'rgba(248,113,113,0.06)', 'rgba(248,113,113,0.2)'],
      default            => ['--muted',   'rgba(107,114,128,0.06)', 'rgba(107,114,128,0.2)'],
    };
  ?>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex;cursor:pointer" onclick='openOrderModal(<?= json_encode($row) ?>)'>
    <div style="width:4px;background:var(<?= $clr ?>);flex-shrink:0"></div>
    <div style="width:72px;flex-shrink:0;display:flex;align-items:center;justify-content:center;padding:10px;border-right:1px solid var(--border)">
      <?php if (!empty($row['Product_Image'])): ?>
        <img src="../<?= htmlspecialchars($row['Product_Image']) ?>" style="width:50px;height:50px;border-radius:8px;object-fit:cover;border:1px solid var(--border)">
      <?php else: ?>
        <div style="width:50px;height:50px;border-radius:8px;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:20px">🧋</div>
      <?php endif; ?>
    </div>
    <div style="flex:1;padding:12px 16px;display:flex;flex-direction:column;gap:3px">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-family:var(--mono);font-size:11px;color:var(--muted)">#<?= $row['Order_ID'] ?></span>
        <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:600;background:<?= $bgClr ?>;color:var(<?= $clr ?>);border:1px solid <?= $borderClr ?>"><?= $status ?></span>
        <span style="font-size:11px;color:var(--muted);font-family:var(--mono)"><?= date('M d · h:i A', strtotime($row['Order_Date_Time'])) ?></span>
        <?php if (!empty($row['Order_Note'])): ?><span style="font-size:10px;background:rgba(251,191,36,0.1);color:var(--warn);border:1px solid rgba(251,191,36,0.2);padding:2px 8px;border-radius:20px;font-family:var(--mono)">📝 note</span><?php endif; ?>
      </div>
      <div style="font-size:14px;font-weight:600;color:var(--text)">
        <?= htmlspecialchars($row['Product_Name']) ?>
        <?php if (!empty($row['Size_Label'])): ?>
          <span style="font-size:10px;font-family:var(--mono);color:var(--muted);background:var(--surface);border:1px solid var(--border);padding:1px 6px;border-radius:6px;margin-left:4px"><?= $row['Size_Label'] ?></span>
        <?php endif; ?>
      </div>
      <div style="font-size:11px;color:var(--muted);font-family:var(--mono)">
        👤 <?= htmlspecialchars($row['Customer_Name'] ?: 'Guest') ?> &nbsp;·&nbsp;
        💳 <?= htmlspecialchars($row['Payment_Type_Description']) ?> &nbsp;·&nbsp;
        Qty: <?= $row['Order_Quantity'] ?> &nbsp;·&nbsp;
        <span style="color:var(--accent)">₱<?= number_format($row['Order_Total'],2) ?></span>
      </div>
    </div>
    <div style="display:flex;align-items:center;padding:0 14px;flex-shrink:0">
      <span style="font-size:12px;color:var(--muted);font-family:var(--mono)">tap to manage →</span>
    </div>
  </div>
  <?php endwhile; ?>
</div>
<?php endif; ?>

<!-- Order Action Modal -->
<div class="modal-overlay" id="orderModal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title" id="mTitle">Order Details</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div id="mContent"></div>
    <div id="mActions" style="display:flex;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);flex-wrap:wrap"></div>
  </div>
</div>

<div class="toast-bar" id="toastEl"></div>

<script>
function openOrderModal(row) {
  document.getElementById('mTitle').textContent = 'Order #' + row.Order_ID + ' — ' + row.Order_Status;

  const sizeTxt = row.Size_Label ? row.Size_Label + ' — ' + row.Size_Name : '—';
  document.getElementById('mContent').innerHTML = `
    <div style="display:flex;gap:14px;margin-bottom:16px;align-items:flex-start">
      ${row.Product_Image
        ? '<img src="../'+row.Product_Image+'" style="width:72px;height:72px;border-radius:10px;object-fit:cover;border:1px solid var(--border);flex-shrink:0">'
        : '<div style="width:72px;height:72px;border-radius:10px;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0">🧋</div>'
      }
      <div>
        <div style="font-size:17px;font-weight:600;color:var(--text);margin-bottom:6px">${row.Product_Name}</div>
        <div style="font-size:12px;color:var(--muted);font-family:var(--mono)">${row.Payment_Type_Description}</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px">
      <div><div style="font-size:10px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Customer</div><div style="font-size:13px;font-weight:500">${row.Customer_Name || 'Guest'}</div></div>
      <div><div style="font-size:10px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Size</div><div style="font-size:13px;font-weight:500">${sizeTxt}</div></div>
      <div><div style="font-size:10px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Quantity</div><div style="font-size:13px;font-weight:500">${row.Order_Quantity}</div></div>
      <div><div style="font-size:10px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Total</div><div style="font-size:16px;font-weight:700;color:var(--accent)">₱${parseFloat(row.Order_Total).toFixed(2)}</div></div>
      <div><div style="font-size:10px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px">Date</div><div style="font-size:12px;font-family:var(--mono)">${row.Order_Date_Time}</div></div>
    </div>
    ${row.Order_Note ? `<div style="background:rgba(251,191,36,0.08);border:1px dashed rgba(251,191,36,0.3);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--warn);display:flex;gap:8px"><span>📝</span><span>${row.Order_Note}</span></div>` : ''}
  `;

  const acts = document.getElementById('mActions');
  acts.innerHTML = '';

  const status = row.Order_Status;
  const id = row.Order_ID;
  const f = new URLSearchParams(location.search).get('filter') || 'all';

  if (status === 'Pending') {
    acts.innerHTML += `<a href="?action=prepare&id=${id}&filter=${f}" class="btn btn-primary" style="background:var(--accent2);color:#0e0f11">👨‍🍳 Start Preparing</a>`;
    acts.innerHTML += `<a href="?action=cancel&id=${id}&filter=${f}" class="btn btn-danger" onclick="return confirm('Cancel this order?')">✕ Cancel Order</a>`;
  } else if (status === 'Preparing') {
    acts.innerHTML += `<a href="?action=ready&id=${id}&filter=${f}" class="btn btn-primary">✅ Mark as Ready</a>`;
    acts.innerHTML += `<a href="?action=cancel&id=${id}&filter=${f}" class="btn btn-danger" onclick="return confirm('Cancel this order?')">✕ Cancel Order</a>`;
  } else if (status === 'Ready for Pickup') {
    acts.innerHTML += `<a href="?action=complete&id=${id}&filter=${f}" class="btn btn-primary" style="background:#7c3aed">☑️ Mark as Completed</a>`;
  } else {
    acts.innerHTML = `<span style="font-size:13px;font-family:var(--mono);color:var(--muted)">${status === 'Completed' ? '✓ This order is completed' : '✕ This order was cancelled'}</span>`;
  }
  acts.innerHTML += `<button class="btn btn-ghost" onclick="closeModal()" style="margin-left:auto">Close</button>`;

  document.getElementById('orderModal').classList.add('open');
}

function closeModal() { document.getElementById('orderModal').classList.remove('open'); }
document.getElementById('orderModal').addEventListener('click', function(e) { if(e.target===this) closeModal(); });

const toastMsgs = {
  preparing: { text: '👨‍🍳 Order is now being prepared!', cls: 'toast-success' },
  ready:     { text: '✅ Order marked as ready for pickup!', cls: 'toast-ready' },
  completed: { text: '☑️ Order completed!', cls: 'toast-done' },
  cancelled: { text: '✕ Order cancelled — stock restored', cls: 'toast-cancel' },
};
const p = new URLSearchParams(location.search), key = p.get('toast');
if (key && toastMsgs[key]) {
  const t = document.getElementById('toastEl');
  t.textContent = toastMsgs[key].text;
  t.classList.add(toastMsgs[key].cls);
  setTimeout(() => t.classList.add('show'), 100);
  setTimeout(() => t.classList.remove('show'), 3500);
  history.replaceState({}, '', location.pathname + '?filter=' + (p.get('filter')||'all'));
}
</script>

<?php require '../includes/footer.php'; ?>