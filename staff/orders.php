<?php
/**
 * STAFF — staff/orders.php
 * -------------------------------------------------------
 * WHO SEES THIS: Staff role only
 * PURPOSE: Manage and process incoming orders
 *
 * WORKFLOW STAFF CAN ACTION:
 *   Pending        → [Prepare]     → Preparing
 *   Preparing      → [Mark Ready]  → Ready for Pickup
 *   Ready for Pickup → [Complete]  → Completed
 *   Pending/Preparing → [Cancel]   → Cancelled (stock restored)
 *
 * Staff CANNOT: delete orders, manage users, manage products price/stock
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
        // Restore stock if cancelled
        if ($newStatus === 'Cancelled') {
            $order = $conn->query("SELECT Product_ID, Order_Quantity FROM orders WHERE Order_ID=$id")->fetch_assoc();
            if ($order) {
                $conn->query("UPDATE products SET Product_Quantity_Stock = Product_Quantity_Stock + {$order['Order_Quantity']} WHERE Product_ID={$order['Product_ID']}");
            }
        }
    }
    header("Location: orders.php?filter=" . ($_GET['filter'] ?? 'all')); exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where  = match($filter) {
    'pending'  => "WHERE o.Order_Status = 'Pending'",
    'preparing'=> "WHERE o.Order_Status = 'Preparing'",
    'ready'    => "WHERE o.Order_Status = 'Ready for Pickup'",
    'done'     => "WHERE o.Order_Status IN ('Completed','Cancelled')",
    default    => ''
};

$orders = $conn->query("
    SELECT o.*, p.Product_Name, p.Product_Image, s.Size_Label, s.Size_Name,
           pt.Payment_Type_Description
    FROM orders o
    JOIN products p       ON o.Product_ID      = p.Product_ID
    JOIN payments_type pt ON o.Payment_Type_ID  = pt.Payment_Type_ID
    LEFT JOIN sizes s     ON o.Size_ID          = s.Size_ID
    $where
    ORDER BY FIELD(o.Order_Status,'Pending','Preparing','Ready for Pickup','Completed','Cancelled'),
             o.Order_Date_Time DESC
");

// Counts for tabs
$counts = [];
foreach (['Pending','Preparing','Ready for Pickup','Completed','Cancelled'] as $st) {
    $esc = $conn->real_escape_string($st);
    $counts[$st] = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE Order_Status='$esc'")->fetch_assoc()['c'];
}
$counts['all'] = array_sum($counts);

require '../includes/staff_header.php';
?>

<div class="page-header">
  <h1 class="page-title">Or<span>ders</span></h1>
</div>

<!-- Stats strip -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px">
  <?php
  $stats = [
    ['Pending',          $counts['Pending'],            '--warn',    '🕐'],
    ['Preparing',        $counts['Preparing'],           '--accent2', '👨‍🍳'],
    ['Ready for Pickup', $counts['Ready for Pickup'],    '--accent',  '✅'],
    ['Completed',        $counts['Completed'],           '--muted',   '☑️'],
    ['Cancelled',        $counts['Cancelled'],           '--danger',  '✕'],
  ];
  foreach ($stats as [$label, $count, $color, $icon]):
  ?>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px 16px">
    <div style="font-size:11px;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px"><?= $icon ?> <?= $label ?></div>
    <div style="font-size:26px;font-weight:700;color:var(<?= $color ?>);letter-spacing:-1px"><?= $count ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php
  $tabs = [
    ['all',       'All Orders',        $counts['all']],
    ['pending',   'Pending',           $counts['Pending']],
    ['preparing', 'Preparing',         $counts['Preparing']],
    ['ready',     'Ready for Pickup',  $counts['Ready for Pickup']],
    ['done',      'Done',              $counts['Completed'] + $counts['Cancelled']],
  ];
  foreach ($tabs as [$key, $label, $count]):
    $active = $filter === $key;
  ?>
  <a href="?filter=<?= $key ?>"
     style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.15s;
            <?= $active
              ? 'background:var(--accent);color:#0e0f11;'
              : 'background:var(--card);color:var(--muted);border:1px solid var(--border);' ?>">
    <?= $label ?>
    <span style="<?= $active ? 'background:rgba(0,0,0,0.15)' : 'background:var(--border)' ?>;color:<?= $active ? '#0e0f11' : 'var(--text)' ?>;border-radius:20px;padding:1px 7px;font-size:11px;font-family:var(--mono)"><?= $count ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Orders -->
<?php if ($orders->num_rows === 0): ?>
  <div style="text-align:center;padding:60px;color:var(--muted);font-family:var(--mono);font-size:14px">
    No orders found for this filter.
  </div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php while ($row = $orders->fetch_assoc()):
    $status  = $row['Order_Status'];
    $statusColor = match($status) {
      'Pending'          => ['--warn',    'rgba(251,191,36,0.1)',  'rgba(251,191,36,0.3)'],
      'Preparing'        => ['--accent2', 'rgba(34,211,238,0.1)',  'rgba(34,211,238,0.3)'],
      'Ready for Pickup' => ['--accent',  'rgba(74,222,128,0.1)',  'rgba(74,222,128,0.3)'],
      'Completed'        => ['--muted',   'rgba(107,114,128,0.1)', 'rgba(107,114,128,0.25)'],
      'Cancelled'        => ['--danger',  'rgba(248,113,113,0.1)', 'rgba(248,113,113,0.3)'],
      default            => ['--muted',   'rgba(107,114,128,0.1)', 'rgba(107,114,128,0.25)'],
    };
    [$clr, $bgClr, $borderClr] = $statusColor;
  ?>
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex">

    <!-- Color stripe -->
    <div style="width:5px;background:var(<?= $clr ?>);flex-shrink:0"></div>

    <!-- Product image -->
    <div style="width:80px;flex-shrink:0;display:flex;align-items:center;justify-content:center;padding:12px;border-right:1px solid var(--border)">
      <?php if (!empty($row['Product_Image'])): ?>
        <img src="../<?= htmlspecialchars($row['Product_Image']) ?>"
             style="width:56px;height:56px;border-radius:8px;object-fit:cover;border:1px solid var(--border)">
      <?php else: ?>
        <div style="width:56px;height:56px;border-radius:8px;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:22px">🧋</div>
      <?php endif; ?>
    </div>

    <!-- Order details -->
    <div style="flex:1;padding:14px 18px;display:flex;flex-direction:column;gap:4px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-family:var(--mono);font-size:12px;color:var(--muted)">#<?= $row['Order_ID'] ?></span>
        <span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:11px;font-family:var(--mono);font-weight:600;background:<?= $bgClr ?>;color:var(<?= $clr ?>);border:1px solid <?= $borderClr ?>"><?= $status ?></span>
        <span style="font-size:11px;color:var(--muted);font-family:var(--mono)"><?= date('M d · h:i A', strtotime($row['Order_Date_Time'])) ?></span>
      </div>

      <div style="font-size:15px;font-weight:600;color:var(--text)">
        <?= htmlspecialchars($row['Product_Name']) ?>
        <?php if (!empty($row['Size_Label'])): ?>
          <span style="font-size:11px;background:var(--surface);border:1px solid var(--border);padding:2px 8px;border-radius:8px;font-family:var(--mono);font-weight:500;color:var(--muted);margin-left:4px"><?= $row['Size_Label'] ?> — <?= $row['Size_Name'] ?></span>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:16px;font-size:12px;color:var(--muted);font-family:var(--mono);flex-wrap:wrap">
        <span>👤 <?= htmlspecialchars($row['Customer_Name'] ?: 'Guest') ?></span>
        <span>💳 <?= htmlspecialchars($row['Payment_Type_Description']) ?></span>
        <span>📦 Qty: <?= $row['Order_Quantity'] ?></span>
        <span style="color:var(--accent)">₱<?= number_format($row['Order_Total'], 2) ?></span>
      </div>

      <?php if (!empty($row['Order_Note'])): ?>
      <div style="display:flex;align-items:flex-start;gap:6px;background:rgba(251,191,36,0.08);border:1px dashed rgba(251,191,36,0.3);border-radius:8px;padding:7px 10px;margin-top:4px;font-size:12px;color:var(--warn)">
        <span>📝</span>
        <span><?= htmlspecialchars($row['Order_Note']) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;flex-direction:column;justify-content:center;gap:8px;padding:14px 16px;border-left:1px solid var(--border);flex-shrink:0;min-width:160px">
      <?php if ($status === 'Pending'): ?>
        <a href="?action=prepare&id=<?= $row['Order_ID'] ?>&filter=<?= $filter ?>"
           class="btn btn-primary btn-sm" style="justify-content:center;background:var(--accent2);color:#0e0f11">
          👨‍🍳 Prepare
        </a>
        <a href="?action=cancel&id=<?= $row['Order_ID'] ?>&filter=<?= $filter ?>"
           class="btn btn-danger btn-sm" style="justify-content:center"
           onclick="return confirm('Cancel order #<?= $row['Order_ID'] ?>?')">
          ✕ Cancel
        </a>

      <?php elseif ($status === 'Preparing'): ?>
        <a href="?action=ready&id=<?= $row['Order_ID'] ?>&filter=<?= $filter ?>"
           class="btn btn-primary btn-sm" style="justify-content:center">
          ✅ Mark Ready
        </a>
        <a href="?action=cancel&id=<?= $row['Order_ID'] ?>&filter=<?= $filter ?>"
           class="btn btn-danger btn-sm" style="justify-content:center"
           onclick="return confirm('Cancel order #<?= $row['Order_ID'] ?>?')">
          ✕ Cancel
        </a>

      <?php elseif ($status === 'Ready for Pickup'): ?>
        <a href="?action=complete&id=<?= $row['Order_ID'] ?>&filter=<?= $filter ?>"
           class="btn btn-primary btn-sm" style="justify-content:center">
          ☑️ Complete
        </a>

      <?php else: ?>
        <span style="font-size:11px;font-family:var(--mono);color:var(--muted);text-align:center">
          <?= $status === 'Completed' ? '✓ Done' : '✕ Cancelled' ?>
        </span>
      <?php endif; ?>
    </div>

  </div>
  <?php endwhile; ?>
</div>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>