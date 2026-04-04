<?php
require_once 'layout.php';
require_once '../includes/printer_helper.php';

if (isset($_POST['complete_sale'])) {
    $data = json_decode($_POST['data'], true);
    $customer_id = (int)$data['customer_id'];
    $cash_amount = (float)$data['cash_amount'];
    $card_amount = (float)$data['card_amount'];
    $debt_amount = (float)$data['debt_amount'];
    $items = $data['items'];
    $total_amount = 0;
    
    foreach ($items as $item) {
        $total_amount += $item['price'] * $item['qty'];
    }

    $conn->begin_transaction();
    try {
        $user_id = $_SESSION['user_id'];
        
        // Determine main payment type for legacy reports
        $payment_type = 'mixed';
        if ($debt_amount == $total_amount) $payment_type = 'debt';
        elseif ($cash_amount == $total_amount) $payment_type = 'cash';
        elseif ($card_amount == $total_amount) $payment_type = 'card';

        $stmt = $conn->prepare("INSERT INTO sales (customer_id, user_id, total_amount, payment_type, cash_amount, card_amount, debt_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $customer_val = $customer_id ?: null;
        $stmt->bind_param("iidsddd", $customer_val, $user_id, $total_amount, $payment_type, $cash_amount, $card_amount, $debt_amount);
        $stmt->execute();
        $sale_id = $conn->insert_id;

        foreach ($items as $item) {
            $p_id = (int)$item['id'];
            $qty = (float)$item['qty']; // Now float for decimals
            $price = (float)$item['price'];
            
            $conn->query("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES ($sale_id, $p_id, $qty, $price)");
            $conn->query("UPDATE products SET quantity = quantity - $qty WHERE id = $p_id");
            
            // Check stock and notify if low
            checkStockAndNotify($p_id);
        }

        // Update customer balance if there is debt
        if ($customer_id && $debt_amount > 0) {
            $conn->query("UPDATE customers SET balance = balance - $debt_amount WHERE id = $customer_id");
        }

        $conn->commit();
        
        // Telegram Notification
        notifySale($sale_id);
        
        // --- NEW: IP Printer Integration ---
        $printer_status = false;
        if (isset($data['do_ip_print']) && $data['do_ip_print']) {
            $printer_status = printToIp($sale_id);
        }
        // ------------------------------------

        echo json_encode(['success' => true, 'sale_id' => $sale_id, 'printer_status' => $printer_status]);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Quick Add Customer AJAX
if (isset($_POST['quick_add_customer'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    if($conn->query("INSERT INTO customers (name, phone) VALUES ('$name', '$phone')")) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'name' => $name]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit();
}

// Product Search AJAX
if (isset($_GET['search'])) {
    $q = $conn->real_escape_string($_GET['search']);
    $res = $conn->query("SELECT * FROM products WHERE (name LIKE '%$q%' OR barcode = '$q') AND quantity > 0 LIMIT 10");
    $prods = [];
    while ($row = $res->fetch_assoc()) $prods[] = $row;
    echo json_encode($prods);
    exit();
}

// Fetch All Products for Modal with Pagination
if (isset($_GET['get_all_products'])) {
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    $search = $conn->real_escape_string($_GET['modal_search'] ?? '');
    
    $where = "WHERE p.quantity > 0";
    if($search) $where .= " AND (p.name LIKE '%$search%' OR p.barcode LIKE '%$search%')";
    
    $res = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $where ORDER BY p.name ASC LIMIT $limit OFFSET $offset");
    $prods = [];
    while ($row = $res->fetch_assoc()) $prods[] = $row;
    echo json_encode($prods);
    exit();
}

render_header('Сотув (POS)');
?>

<div class="row g-4">
    <div class="col-md-8">
        <div class="pos-card p-4 h-100">
            <div class="d-flex gap-3 mb-4">
                <div class="input-group shadow-sm flex-grow-1">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="productSearch" class="form-control form-control-lg border-start-0 ps-0" placeholder="Штрих-код ёки товар қидириш (Enter)..." autocomplete="off">
                </div>
                <button class="btn btn-primary d-flex align-items-center px-4 rounded-3 shadow-sm transition-all" onclick="openAllProductsModal()">
                    <i class="bi bi-grid-3x3-gap me-2 fs-5"></i> Барча товарлар
                </button>
            </div>
            
            <div id="searchResults" class="list-group mb-4 shadow-lg border-0" style="position: absolute; width: 100%; max-width: 800px; z-index: 1050; display: none; margin-top: -1rem;"></div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="40">№</th>
                            <th>Маҳсулот</th>
                            <th width="100">Нарх</th>
                            <th width="120">Миқдор</th>
                            <th width="120">Жами</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="cartItems">
                        <!-- Items will appear here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="pos-card p-4">
            <h5 class="fw-bold mb-4">Чек маълумотлари</h5>
            
            <div class="mb-3">
                <label class="form-label">Мижоз</label>
                <div class="d-flex gap-2">
                    <select id="customerSelect" class="form-select">
                        <option value="">Оддий мижоз</option>
                        <?php
                        $customers = $conn->query("SELECT * FROM customers ORDER BY name ASC");
                        while($c = $customers->fetch_assoc()) echo "<option value='{$c['id']}'>{$c['name']} ({$c['balance']})</option>";
                        ?>
                    </select>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal"><i class="bi bi-person-plus"></i></button>
                </div>
            </div>

            <hr>
            
            <div class="d-flex justify-content-between mb-2">
                <span>Жами:</span>
                <span class="fw-bold" id="totalDisplay">0 сум</span>
            </div>
            
            <div class="mb-4 bg-light p-3 rounded-3">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label text-success fw-bold mb-0">Нахт тўлов</label>
                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" onclick="setPaymentMode('cash')">Max</button>
                    </div>
                    <input type="number" id="cashInput" class="form-control form-control-lg" value="0" oninput="calculateDebt()">
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label text-info fw-bold mb-0">Карта орқали</label>
                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2" onclick="setPaymentMode('card')">Max</button>
                    </div>
                    <input type="number" id="cardInput" class="form-control form-control-lg" value="0" oninput="calculateDebt()">
                </div>
                <div class="d-flex justify-content-between p-2 mt-2 bg-white rounded shadow-sm align-items-center">
                    <span class="text-danger fw-bold">ҚАРЗ:</span>
                    <span class="text-danger fw-bold fs-5" id="debtDisplay">0 сум</span>
                </div>
            </div>

            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="printReceipt" checked>
                <label class="form-check-label" for="printReceipt">Браузердан чиқариш</label>
            </div>
            
            <?php if(getSetting('printer_ip')): ?>
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" id="ipPrint" checked>
                <label class="form-check-label" for="ipPrint">Принтерга жўнатиш (IP)</label>
            </div>
            <?php endif; ?>

            <button id="completeSaleBtn" class="btn btn-primary btn-lg w-100 py-3" onclick="completeSale()">
                <i class="bi bi-check2-circle me-2"></i> СОТУВНИ ЯКУНЛАШ
            </button>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Янги мижоз қўшиш</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="text" id="newCustName" class="form-control mb-2" placeholder="Исми" required>
          <input type="text" id="newCustPhone" class="form-control" placeholder="Телефон">
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-primary" onclick="quickAddCustomer()">ҚЎШИШ</button>
      </div>
    </div>
  </div>
</div>

<!-- All Products Modal -->
<div class="modal fade" id="allProductsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem; overflow: hidden;">
      <div class="modal-header bg-white py-3 px-4 border-0 border-bottom">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary"><i class="bi bi-grid-3x3-gap fs-5"></i></div>
            Барча товарлар рўйхати
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4" style="background: #fcfcfc;" id="modalBodyScroll">
          <div class="input-group mb-4 shadow-sm" style="border-radius: 12px; overflow: hidden;">
              <span class="input-group-text bg-white border-end-0 text-muted ps-3"><i class="bi bi-search fs-5"></i></span>
              <input type="text" id="modalProductSearch" class="form-control form-control-lg border-start-0 ps-1 py-3" placeholder="Товар номи ёки штрих-код бўйича излаш..." oninput="handleModalSearch()">
          </div>
          
          <div id="modalProductGrid" class="row g-3">
              <!-- Cards Loaded via JS -->
          </div>

          <div id="modalLoader" class="text-center py-5 d-none">
              <div class="spinner-border text-primary" role="status"></div>
              <div class="text-muted small mt-2">Юкланмоқда...</div>
          </div>
      </div>
    </div>
  </div>
</div>

<style>
.product-card {
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
    cursor: pointer;
    border-radius: 18px;
    height: 100%;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
    border-color: var(--bs-primary) !important;
}
.product-img-container {
    height: 120px;
    background: #f8f9fa;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    overflow: hidden;
    position: relative;
}
.product-img-container img {
    height: 100%;
    width: 100%;
    object-fit: contain;
    padding: 10px;
}
.card-low-stock {
    background-color: #fff5f5 !important;
    border-color: #ffdada !important;
}
.card-low-label {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 5;
    font-size: 0.6rem;
    font-weight: 800;
}
</style>

<script>
let cart = [];

let scrollState = {
    offset: 0,
    limit: 16,
    loading: false,
    hasMore: true,
    search: '',
    bsModal: null
};

function openAllProductsModal() {
    if(!scrollState.bsModal) {
        scrollState.bsModal = new bootstrap.Modal(document.getElementById('allProductsModal'));
        document.getElementById('modalBodyScroll').addEventListener('scroll', handleModalScroll);
    }
    
    // Reset state and reload
    scrollState.offset = 0;
    scrollState.hasMore = true;
    scrollState.search = '';
    document.getElementById('modalProductSearch').value = '';
    document.getElementById('modalProductGrid').innerHTML = '';
    
    scrollState.bsModal.show();
    loadModalProducts();
}

function handleModalSearch() {
    scrollState.search = document.getElementById('modalProductSearch').value;
    scrollState.offset = 0;
    scrollState.hasMore = true;
    document.getElementById('modalProductGrid').innerHTML = '';
    loadModalProducts();
}

function handleModalScroll() {
    const body = document.getElementById('modalBodyScroll');
    if (body.scrollTop + body.clientHeight >= body.scrollHeight - 100) {
        loadModalProducts();
    }
}

function loadModalProducts() {
    if (scrollState.loading || !scrollState.hasMore) return;
    
    scrollState.loading = true;
    document.getElementById('modalLoader').classList.remove('d-none');

    fetch(`?get_all_products=1&offset=${scrollState.offset}&limit=${scrollState.limit}&modal_search=${encodeURIComponent(scrollState.search)}`)
    .then(r => r.json())
    .then(data => {
        const grid = document.getElementById('modalProductGrid');
        if (data.length < scrollState.limit) scrollState.hasMore = false;
        
        data.forEach(p => {
            const isLowStock = parseFloat(p.quantity) < 5;
            const img = p.image ? `../${p.image}` : null;
            const card = document.createElement('div');
            card.className = 'col-6 col-md-4 col-lg-3';
            card.innerHTML = `
                <div class="card product-card border shadow-sm p-3 ${isLowStock ? 'card-low-stock' : 'bg-white'}" onclick='addToCart(${JSON.stringify(p)})'>
                    <span class="card-low-label badge ${isLowStock ? 'bg-danger' : 'bg-light text-muted'} rounded-pill">${scrollState.offset + 1}</span>
                    <div class="product-img-container shadow-sm border-0">
                        ${img ? `<img src="${img}" alt="${p.name}">` : `<i class="bi bi-box-seam fs-1 text-muted opacity-25"></i>`}
                    </div>
                    <div class="text-center mt-2">
                        <h6 class="card-title fw-bold mb-1 text-truncate" title="${p.name}">${p.name}</h6>
                        <small class="text-muted d-block mb-2 font-monospace">${p.barcode}</small>
                        <div class="d-flex flex-column align-items-center mt-auto">
                            <span class="text-primary fw-extrabold fs-5 mb-1">${parseFloat(p.sale_price).toLocaleString()} <small>сум</small></span>
                            <span class="badge ${isLowStock ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success'} rounded-pill px-3 py-2 w-100">
                                ${isLowStock ? '<i class="bi bi-exclamation-triangle me-1"></i>' : ''}
                                Олдин: ${parseFloat(p.quantity).toLocaleString()} ${p.unit}
                            </span>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(card);
            scrollState.offset++;
        });

        if (grid.innerHTML === '') {
            grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">Товар топилмади</div>';
        }

        scrollState.loading = false;
        document.getElementById('modalLoader').classList.add('d-none');
    });
}

document.getElementById('productSearch').addEventListener('input', function() {
    let q = this.value;
    if(q.length < 2) {
        document.getElementById('searchResults').style.display = 'none';
        return;
    }
    
    fetch('?search=' + q)
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.length === 0) {
            html = '<div class="list-group-item text-muted text-center py-3">Товар топилмади</div>';
        }
        data.forEach(p => {
            html += `<button type="button" class="list-group-item list-group-item-action border-0 border-bottom py-3" onclick='addToCart(${JSON.stringify(p)})'>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold">${p.name}</h6>
                                <small class="text-muted">Қолдиқ: ${p.quantity} ${p.unit} | Штрих: ${p.barcode}</small>
                            </div>
                            <span class="badge bg-primary rounded-pill p-2 fs-6">${parseFloat(p.sale_price).toLocaleString()} сум</span>
                        </div>
                    </button>`;
        });
        document.getElementById('searchResults').innerHTML = html;
        document.getElementById('searchResults').style.display = 'block';
    });
});

// Barcode scanner support (Enter key)
document.getElementById('productSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        let q = this.value;
        if(q.length < 1) return;

        fetch('?search=' + q)
        .then(r => r.json())
        .then(data => {
            if(data.length > 0) {
                // If it's an exact barcode match or there's only one result, add it
                let exactMatch = data.find(p => p.barcode === q);
                if(exactMatch) {
                    addToCart(exactMatch);
                } else if (data.length === 1) {
                    addToCart(data[0]);
                }
            }
        });
    }
});

function addToCart(p) {
    let index = cart.findIndex(item => item.id === p.id);
    if(index > -1) {
        if(cart[index].qty < p.quantity) cart[index].qty++;
    } else {
        cart.push({id: p.id, name: p.name, price: p.sale_price, qty: 1, max: p.quantity, unit: p.unit});
    }
    renderCart();
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('productSearch').value = '';
}

function renderCart() {
    let html = '';
    let total = 0;
    cart.forEach((item, index) => {
        let itemTotal = item.price * item.qty;
        total += itemTotal;
        html += `<tr>
                    <td class="text-muted small fw-bold">${index + 1}</td>
                    <td>${item.name}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-end fw-bold" 
                               value="${item.price}" 
                               oninput="updatePrice(${index}, this.value)" 
                               style="width: 100px;">
                    </td>
                    <td>
                        <div class="input-group input-group-sm" style="width: 170px;">
                            <button class="btn btn-outline-secondary" onclick="updateQty(${index}, -0.1)">-</button>
                            <input type="number" step="0.001" class="form-control text-center fw-bold" 
                                   value="${item.qty}" 
                                   oninput="updateQtyDirect(${index}, this.value)">
                            <span class="input-group-text bg-light small fw-bold" style="min-width: 45px;">${item.unit}</span>
                            <button class="btn btn-outline-secondary" onclick="updateQty(${index}, 0.1)">+</button>
                        </div>
                    </td>
                    <td id="itemTotal-${index}" class="fw-bold">${itemTotal.toLocaleString()}</td>
                    <td><button class="btn btn-sm text-danger" onclick="removeFromCart(${index})"><i class="bi bi-x-lg"></i></button></td>
                </tr>`;
    });
    document.getElementById('cartItems').innerHTML = html;
    document.getElementById('totalDisplay').innerText = total.toLocaleString() + ' сум';
    
    // Auto-fill cash input with total as default
    document.getElementById('cashInput').value = total;
    document.getElementById('cardInput').value = 0;
    calculateDebt();
}

function updatePrice(index, newPrice) {
    let p = parseFloat(newPrice) || 0;
    cart[index].price = p;
    
    // Update only the item total and grand totals to avoid focus loss
    let itemTotal = p * cart[index].qty;
    document.getElementById(`itemTotal-${index}`).innerText = itemTotal.toLocaleString();
    
    recalculateGrandTotal();
}

function recalculateGrandTotal() {
    let total = 0;
    cart.forEach(item => {
        total += item.price * item.qty;
    });
    document.getElementById('totalDisplay').innerText = total.toLocaleString() + ' сум';
    
    // Update payment fields
    document.getElementById('cashInput').value = total;
    document.getElementById('cardInput').value = 0;
    calculateDebt();
}

function setPaymentMode(mode) {
    let totalText = document.getElementById('totalDisplay').innerText;
    let total = parseFloat(totalText.replace(/\s/g, '').replace('сум', '')) || 0;
    
    if (mode === 'cash') {
        document.getElementById('cashInput').value = total;
        document.getElementById('cardInput').value = 0;
    } else if (mode === 'card') {
        document.getElementById('cashInput').value = 0;
        document.getElementById('cardInput').value = total;
    }
    calculateDebt();
}

function calculateDebt() {
    let totalText = document.getElementById('totalDisplay').innerText;
    let total = parseFloat(totalText.replace(/\s/g, '').replace('сум', '')) || 0;
    let cash = parseFloat(document.getElementById('cashInput').value) || 0;
    let card = parseFloat(document.getElementById('cardInput').value) || 0;
    
    let debt = total - cash - card;
    if (debt < 0) debt = 0;
    
    document.getElementById('debtDisplay').innerText = debt.toLocaleString() + ' сум';
    return debt;
}

function updateQty(index, delta) {
    let newQty = parseFloat(cart[index].qty) + delta;
    if(newQty > 0 && newQty <= cart[index].max) {
        cart[index].qty = Math.round(newQty * 1000) / 1000;
        renderCart();
    }
}

function updateQtyDirect(index, val) {
    let q = parseFloat(val) || 0;
    if(q >= 0) {
        if(q > cart[index].max) q = cart[index].max;
        cart[index].qty = q;
        
        // Update item total and grand totals without full re-render
        let itemTotal = q * cart[index].price;
        document.getElementById(`itemTotal-${index}`).innerText = itemTotal.toLocaleString();
        recalculateGrandTotal();
    }
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function quickAddCustomer() {
    let name = document.getElementById('newCustName').value;
    let phone = document.getElementById('newCustPhone').value;
    if(!name) return alert('Исмни ёзинг!');

    let formData = new FormData();
    formData.append('quick_add_customer', '1');
    formData.append('name', name);
    formData.append('phone', phone);

    fetch('dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            let select = document.getElementById('customerSelect');
            let opt = document.createElement('option');
            opt.value = res.id;
            opt.text = res.name;
            select.add(opt);
            select.value = res.id;
            
            bootstrap.Modal.getInstance(document.getElementById('addCustomerModal')).hide();
            document.getElementById('newCustName').value = '';
            document.getElementById('newCustPhone').value = '';
        } else {
            alert('Хатолик: ' + res.error);
        }
    });
}

function completeSale() {
    if(cart.length === 0) return alert('Сават бўш!');
    
    let totalText = document.getElementById('totalDisplay').innerText;
    let total = parseFloat(totalText.replace(/\s/g, '').replace('сум', '')) || 0;
    let cash = parseFloat(document.getElementById('cashInput').value) || 0;
    let card = parseFloat(document.getElementById('cardInput').value) || 0;
    let debt = calculateDebt();
    let customerId = document.getElementById('customerSelect').value;

    if (debt > 0 && !customerId) {
        return alert('Қарз бўлганда мижозни танлаш шарт!');
    }
    
    let formData = new FormData();
    formData.append('complete_sale', '1');
    
    formData.append('data', JSON.stringify({
        customer_id: customerId,
        cash_amount: cash,
        card_amount: card,
        debt_amount: debt,
        items: cart,
        do_ip_print: document.getElementById('ipPrint') ? document.getElementById('ipPrint').checked : false
    }));

    // Disable button to prevent double-click
    const btn = document.getElementById('completeSaleBtn');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> ЮКЛАНМОҚДА...';

    fetch('dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            alert('Сотув муваффақиятли якунланди!');
            
            if (document.getElementById('printReceipt').checked) {
                window.open('print_receipt.php?id=' + res.sale_id, '_blank', 'width=400,height=600');
            }

            cart = [];
            renderCart();
        } else {
            alert('Хатолик: ' + res.error);
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

// Persist Print Switches State
document.addEventListener('DOMContentLoaded', () => {
    const pr = document.getElementById('printReceipt');
    const ip = document.getElementById('ipPrint');
    
    if (localStorage.getItem('printReceipt') !== null) {
        pr.checked = localStorage.getItem('printReceipt') === 'true';
    }
    if (ip && localStorage.getItem('ipPrint') !== null) {
        ip.checked = localStorage.getItem('ipPrint') === 'true';
    }

    pr.addEventListener('change', () => localStorage.setItem('printReceipt', pr.checked));
    if (ip) ip.addEventListener('change', () => localStorage.setItem('ipPrint', ip.checked));
});
</script>

<?php render_footer(); ?>
