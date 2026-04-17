<?php
require '../config.php';
$pageTitle = 'Orders';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM orders WHERE Order_ID = $id");
    header('Location: orders.php?toast=deleted&view=' . ($_GET['view'] ?? 'active')); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id       = (int)$_POST['Product_ID'];
    $customer_type_id = (int)$_POST['Customer_Type_ID'];
    $payment_type_id  = (int)$_POST['Payment_Type_ID'];
    $order_quantity   = (int)$_POST['Order_Quantity'];
    $order_status     = $conn->real_escape_string($_POST['Order_Status']);
    $size_id          = !empty($_POST['Size_ID']) ? (int)$_POST['Size_ID'] : 'NULL';

    $priceRow      = $conn->query("SELECT Product_Price FROM products WHERE Product_ID=$product_id")->fetch_assoc();
    $sizeRow       = $size_id !== 'NULL' ? $conn->query("SELECT Size_Price FROM sizes WHERE Size_ID=$size_id")->fetch_assoc() : null;
    $product_price = $priceRow['Product_Price'] + ($sizeRow ? $sizeRow['Size_Price'] : 0);

    if (isset($_POST['Order_ID']) && $_POST['Order_ID'] !== '') {
        $id = (int)$_POST['Order_ID'];
        $conn->query("UPDATE orders SET Product_ID=$product_id,Size_ID=$size_id,Customer_Type_ID=$customer_type_id,Payment_Type_ID=$payment_type_id,Order_Quantity=$order_quantity,Product_Price=$product_price,Order_Status='$order_status' WHERE Order_ID=$id");
        header('Location: orders.php?toast=updated'); exit;
    } else {
        $conn->query("INSERT INTO orders (Product_ID,Size_ID,Customer_Type_ID,Payment_Type_ID,Order_Quantity,Product_Price,Order_Status) VALUES ($product_id,$size_id,$customer_type_id,$payment_type_id,$order_quantity,$product_price,'$order_status')");
        header('Location: orders.php?toast=created'); exit;
    }
}

$view        = $_GET['view'] ?? 'active';
$whereClause = $view === 'logs'
    ? "WHERE o.Order_Status IN ('Completed','Cancelled')"
    : "WHERE o.Order_Status NOT IN ('Completed','Cancelled')";

$orders        = $conn->query("SELECT o.*,p.Product_Name,s.Size_Label,s.Size_Name,ct.Customer_Type_Description,pt.Payment_Type_Description FROM orders o JOIN products p ON o.Product_ID=p.Product_ID JOIN customer_type ct ON o.Customer_Type_ID=ct.Customer_Type_ID JOIN payments_type pt ON o.Payment_Type_ID=pt.Payment_Type_ID LEFT JOIN sizes s ON o.Size_ID=s.Size_ID $whereClause ORDER BY o.Order_Date_Time DESC");
$products      = $conn->query("SELECT Product_ID,Product_Name,Product_Price FROM products");
$customerTypes = $conn->query("SELECT * FROM customer_type");
$paymentTypes  = $conn->query("SELECT * FROM payments_type");
$sizes         = $conn->query("SELECT * FROM sizes ORDER BY Sort_Order");
$activeCount   = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE Order_Status NOT IN ('Completed','Cancelled')")->fetch_assoc()['c'];
$logsCount     = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE Order_Status IN ('Completed','Cancelled')")->fetch_assoc()['c'];

require "../includes/header.php";
?>
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal-overlay.open{display:flex}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;width:100%;max-width:720px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,0.5)}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:600;color:var(--text);font-family:var(--mono)}
.modal-close{background:none;border:none;color:var(--muted);font-size:22px;cursor:pointer;line-height:1;transition:color 0.15s}.modal-close:hover{color:var(--danger)}
.toast-bar{position:fixed;bottom:28px;right:28px;padding:13px 22px;border-radius:30px;font-weight:700;font-size:13px;z-index:999;display:flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,0.3);transform:translateY(80px);opacity:0;transition:transform 0.4s cubic-bezier(.34,1.56,.64,1),opacity 0.3s;pointer-events:none}
.toast-bar.show{transform:translateY(0);opacity:1}
.toast-success{background:var(--accent);color:#0e0f11}
.toast-danger{background:var(--danger);color:#fff}
.view-tabs{display:flex;gap:8px;margin-bottom:20px}
.view-tab{padding:7px 18px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.15s;border:1px solid var(--border);color:var(--muted);background:var(--card)}
.view-tab.active{background:var(--accent);color:#0e0f11;border-color:var(--accent)}
.view-tab .cnt{border-radius:20px;padding:1px 7px;font-size:11px;font-family:var(--mono);background:rgba(0,0,0,0.15)}
.view-tab:not(.active) .cnt{background:var(--border);color:var(--text)}
</style>

<div class="page-header">
  <h1 class="page-title">Or<span>ders</span></h1>
  <button class="btn btn-primary" onclick="openModal()">+ New Order</button>
</div>

<div class="view-tabs">
  <a href="?view=active" class="view-tab <?= $view==='active'?'active':'' ?>">Active Orders <span class="cnt"><?= $activeCount ?></span></a>
  <a href="?view=logs"   class="view-tab <?= $view==='logs'  ?'active':'' ?>">Order Logs <span class="cnt"><?= $logsCount ?></span></a>
</div>

<div class="card">
  <div class="card-title"><?= $view==='logs' ? 'Completed &amp; Cancelled — Order Logs' : 'Active Orders — latest first' ?></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#ID</th><th>Date</th><th>Customer</th><th>Product</th><th>Size</th><th>Payment</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Note</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while ($row = $orders->fetch_assoc()):
          $badge = match($row['Order_Status']) {
            'Completed'=>'badge-green','Pending'=>'badge-yellow','Cancelled'=>'badge-red',
            'Preparing'=>'badge-blue','Ready for Pickup'=>'badge-green',default=>'badge-blue'
          };
        ?>
        <tr>
          <td class="mono">#<?= $row['Order_ID'] ?></td>
          <td class="mono" style="font-size:11px;white-space:nowrap"><?= date('M d, Y', strtotime($row['Order_Date_Time'])) ?><br><?= date('h:i A', strtotime($row['Order_Date_Time'])) ?></td>
          <td><?= htmlspecialchars($row['Customer_Name'] ?: '—') ?></td>
          <td><?= htmlspecialchars($row['Product_Name']) ?></td>
          <td class="mono"><?= !empty($row['Size_Label']) ? $row['Size_Label'].' — '.$row['Size_Name'] : '—' ?></td>
          <td><?= htmlspecialchars($row['Payment_Type_Description']) ?></td>
          <td><?= $row['Order_Quantity'] ?></td>
          <td class="mono">₱<?= number_format($row['Product_Price'],2) ?></td>
          <td class="mono">₱<?= number_format($row['Order_Total'],2) ?></td>
          <td><span class="badge <?= $badge ?>"><?= $row['Order_Status'] ?></span></td>
          <td style="font-size:11px;color:var(--muted);max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($row['Order_Note'] ?? '') ?>"><?= htmlspecialchars($row['Order_Note'] ?? '—') ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn btn-ghost btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)'>Edit</button>
            <a href="?delete=<?= $row['Order_ID'] ?>&view=<?= $view ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete order #<?= $row['Order_ID'] ?>?')">Del</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="orderModal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">New Order</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <form method="POST" id="orderForm">
      <input type="hidden" name="Order_ID" id="fOrderId">
      <div class="form-grid">
        <div class="form-group">
          <label>Product</label>
          <select name="Product_ID" required id="fProduct" onchange="updatePrice()">
            <option value="">— Select product —</option>
            <?php $products->data_seek(0); while ($p=$products->fetch_assoc()): ?>
              <option value="<?= $p['Product_ID'] ?>" data-price="<?= $p['Product_Price'] ?>"><?= htmlspecialchars($p['Product_Name']) ?> — ₱<?= number_format($p['Product_Price'],2) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Size</label>
          <select name="Size_ID" id="fSize" onchange="updatePrice()">
            <option value="">— No size —</option>
            <?php $sizes->data_seek(0); while ($sz=$sizes->fetch_assoc()): ?>
              <option value="<?= $sz['Size_ID'] ?>" data-addon="<?= $sz['Size_Price'] ?>"><?= $sz['Size_Label'] ?> — <?= htmlspecialchars($sz['Size_Name']) ?> (+₱<?= number_format($sz['Size_Price'],2) ?>)</option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Customer Type</label>
          <select name="Customer_Type_ID" required id="fCustType">
            <option value="">— Select type —</option>
            <?php $customerTypes->data_seek(0); while ($ct=$customerTypes->fetch_assoc()): ?>
              <option value="<?= $ct['Customer_Type_ID'] ?>"><?= htmlspecialchars($ct['Customer_Type_Description']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Payment Type</label>
          <select name="Payment_Type_ID" required id="fPayType">
            <option value="">— Select payment —</option>
            <?php $paymentTypes->data_seek(0); while ($pt=$paymentTypes->fetch_assoc()): ?>
              <option value="<?= $pt['Payment_Type_ID'] ?>"><?= htmlspecialchars($pt['Payment_Type_Description']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quantity</label>
          <input type="number" name="Order_Quantity" id="fQty" min="1" value="1" required oninput="updateTotal()">
        </div>
        <div class="form-group">
          <label>Unit Price (auto)</label>
          <input type="text" id="fUnitPrice" readonly value="₱0.00" style="opacity:0.5;cursor:not-allowed">
        </div>
        <div class="form-group">
          <label>Est. Total (auto)</label>
          <input type="text" id="fTotal" readonly value="₱0.00" style="opacity:0.5;cursor:not-allowed">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="Order_Status" id="fStatus">
            <?php foreach(['Pending','Preparing','Ready for Pickup','Completed','Cancelled'] as $s): ?>
              <option><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="fSubmitBtn">+ Add Order</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-bar" id="toastEl"></div>

<script>
let currentPrice = 0;
function updatePrice() {
  const prod=document.getElementById('fProduct'),size=document.getElementById('fSize');
  currentPrice=(parseFloat(prod.options[prod.selectedIndex]?.dataset?.price)||0)+(parseFloat(size.options[size.selectedIndex]?.dataset?.addon)||0);
  document.getElementById('fUnitPrice').value='₱'+currentPrice.toFixed(2);
  updateTotal();
}
function updateTotal(){
  const qty=parseInt(document.getElementById('fQty').value)||0;
  document.getElementById('fTotal').value='₱'+(qty*currentPrice).toFixed(2);
}
function openModal(){
  document.getElementById('modalTitle').textContent='New Order';
  document.getElementById('fSubmitBtn').textContent='+ Add Order';
  document.getElementById('orderForm').reset();
  document.getElementById('fOrderId').value='';
  document.getElementById('fUnitPrice').value='₱0.00';
  document.getElementById('fTotal').value='₱0.00';
  currentPrice=0;
  document.getElementById('orderModal').classList.add('open');
}
function openEditModal(row){
  document.getElementById('modalTitle').textContent='Edit Order #'+row.Order_ID;
  document.getElementById('fSubmitBtn').textContent='✓ Update Order';
  document.getElementById('fOrderId').value=row.Order_ID;
  setSelect('fProduct',row.Product_ID);
  setSelect('fSize',row.Size_ID||'');
  setSelect('fCustType',row.Customer_Type_ID);
  setSelect('fPayType',row.Payment_Type_ID);
  setSelect('fStatus',row.Order_Status);
  document.getElementById('fQty').value=row.Order_Quantity;
  updatePrice();
  document.getElementById('orderModal').classList.add('open');
}
function setSelect(id,val){const s=document.getElementById(id);for(let o of s.options){if(String(o.value)===String(val)){o.selected=true;break;}}}
function closeModal(){document.getElementById('orderModal').classList.remove('open');}
document.getElementById('orderModal').addEventListener('click',function(e){if(e.target===this)closeModal();});

const msgs={created:{text:'✓ Order created successfully',cls:'toast-success'},updated:{text:'✓ Order updated successfully',cls:'toast-success'},deleted:{text:'✕ Order deleted',cls:'toast-danger'}};
const p=new URLSearchParams(location.search),key=p.get('toast');
if(key&&msgs[key]){
  const t=document.getElementById('toastEl');
  t.textContent=msgs[key].text;t.classList.add(msgs[key].cls);
  setTimeout(()=>t.classList.add('show'),100);
  setTimeout(()=>t.classList.remove('show'),3500);
  history.replaceState({},'',location.pathname+'?view='+(p.get('view')||'active'));
}
</script>
<?php require "../includes/footer.php"; ?>