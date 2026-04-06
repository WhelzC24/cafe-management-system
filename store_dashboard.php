<?php
session_start();
require_once 'db.php';

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'staff'], true)) {
    header('Location: login.html');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── Runtime upload limits (effective server cap) ──────────── */
function parse_size_to_bytes(string $val): int {
    $val = trim($val);
    if ($val === '') return 0;
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int) $val;
    switch ($last) {
        case 'g': $num *= 1024;
        // fall through
        case 'm': $num *= 1024;
        // fall through
        case 'k': $num *= 1024;
    }
    return $num;
}

const APP_TARGET_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB app target
$server_upload_bytes = parse_size_to_bytes((string) ini_get('upload_max_filesize'));
$server_post_bytes   = parse_size_to_bytes((string) ini_get('post_max_size'));
$effective_server_bytes = min(
    $server_upload_bytes > 0 ? $server_upload_bytes : PHP_INT_MAX,
    $server_post_bytes > 0 ? $server_post_bytes : PHP_INT_MAX
);
if ($effective_server_bytes === PHP_INT_MAX) {
    $effective_server_bytes = APP_TARGET_UPLOAD_BYTES;
}
$effective_upload_bytes = min(APP_TARGET_UPLOAD_BYTES, $effective_server_bytes);
$effective_upload_mb = max(1, (int) floor($effective_upload_bytes / (1024 * 1024)));
$effective_upload_label = $effective_upload_mb . ' MB';

/* ── Uploads folder health check ───────────────────────────── */
$uploads_warning = '';
$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir)) {
    $uploads_warning = 'uploads/ folder is missing. Image uploads will fail until the folder is created.';
} elseif (!is_writable($uploads_dir)) {
    $mode = @fileperms($uploads_dir);
    $mode_text = $mode ? substr(sprintf('%o', $mode), -4) : 'unknown';
    $uploads_warning = 'uploads/ is not writable by the web server (mode ' . $mode_text . '). '
        . 'Fix owner/group and permissions (recommended: chown -R http:http uploads && chmod 775 uploads).';
} elseif ($effective_upload_bytes < APP_TARGET_UPLOAD_BYTES) {
    $uploads_warning = 'Server upload limits are lower than the app target. Current effective limit is '
        . $effective_upload_label
        . '. Increase PHP upload_max_filesize/post_max_size to support larger PNG/JPG uploads.';
}

/* ── Edited product ───────────── */
$edit_id = isset($_GET['edit_product']) ? (int)$_GET['edit_product'] : 0;
$pte = ['id'=>0,'name'=>'','category'=>'Coffee','description'=>'','price'=>'','image_url'=>'','is_available'=>1];
if ($edit_id > 0) {
    $es = mysqli_prepare($conn,'SELECT id,name,category,description,price,image_url,is_available FROM products WHERE id=?');
    mysqli_stmt_bind_param($es,'i',$edit_id);
    mysqli_stmt_execute($es);
    $er = mysqli_stmt_get_result($es);
    $er2 = mysqli_fetch_assoc($er);
    mysqli_stmt_close($es);
    if ($er2) $pte = $er2;
}

/* ── Products ─────────────────── */
$products = [];
$pr = mysqli_query($conn,'SELECT id,name,category,description,price,image_url,is_available,created_at FROM products ORDER BY category ASC,name ASC');
if ($pr) { while($r=mysqli_fetch_assoc($pr)) $products[]=$r; mysqli_free_result($pr); }

/* ── Orders (last 50) ─────────── */
$orders=[];
$oq="SELECT o.id,o.customer_name,o.customer_email,o.customer_phone,o.notes,o.total_amount,o.status,o.created_at,u.fullname AS processed_by_name,
GROUP_CONCAT(CONCAT(oi.quantity,'x ',oi.product_name) ORDER BY oi.id SEPARATOR '||') AS items_summary
FROM orders o LEFT JOIN users u ON u.id=o.processed_by LEFT JOIN order_items oi ON oi.order_id=o.id
GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50";
$or=mysqli_query($conn,$oq);
if($or){while($r=mysqli_fetch_assoc($or)){$r['items']=$r['items_summary']?explode('||',$r['items_summary']):[];$orders[]=$r;}mysqli_free_result($or);}

/* ── Stats ───────────────────── */
$s=['products'=>0,'available'=>0,'pending'=>0,'today'=>0];
foreach([
    'products'=>'SELECT COUNT(*) AS c FROM products',
    'available'=>'SELECT COUNT(*) AS c FROM products WHERE is_available=1',
    'pending'=>"SELECT COUNT(*) AS c FROM orders WHERE status IN('pending','preparing')",
    'today'=>'SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE()'
] as $k=>$q){
    $res=mysqli_query($conn,$q);
    if($res){$row=mysqli_fetch_assoc($res);$s[$k]=(int)($row['c']??0);mysqli_free_result($res);}
}

$categories=['Coffee','Cold Drinks','Hot Drinks','Pastries','Food','Other'];
$statuses=['pending','preparing','ready','completed','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Dashboard — Cozy Corner Café</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* local overrides */
        .img-panel { display: block; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
<div id="alertContainer"></div>

<!-- Mobile Sidebar Toggle -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <a href="cafe/index.html" class="sidebar-brand" target="_blank">
            <div class="sidebar-brand-icon">☕</div>
            <div class="sidebar-brand-text">
                Cozy Corner
                <small>Staff Dashboard</small>
            </div>
        </a>

        <div class="sidebar-section-label">Main</div>
        <nav class="sidebar-nav">
            <button class="sidebar-link active" id="nav-orders" onclick="switchTab('orders',this);setSidebarActive(this)">
                <span class="sidebar-link-icon">📋</span>
                Orders
            </button>
            <button class="sidebar-link" id="nav-products" onclick="switchTab('products',this);setSidebarActive(this)">
                <span class="sidebar-link-icon">🛍️</span>
                Products
            </button>
            <button class="sidebar-link" id="nav-add" onclick="switchTab('add',this);setSidebarActive(this)">
                <span class="sidebar-link-icon"><?= $edit_id > 0 ? '✏️' : '➕' ?></span>
                <?= $edit_id > 0 ? 'Edit Product' : 'Add Product' ?>
            </button>

            <div class="sidebar-section-label" style="margin-top:.75rem;">Alerts</div>
            <button class="sidebar-link" id="nav-notif" onclick="openNotifPanel()">
                <span class="sidebar-link-icon">🔔</span>
                Notifications
                <span class="notif-badge" id="notifBadge"></span>
            </button>

            <?php if ($role === 'admin'): ?>
            <div class="sidebar-section-label" style="margin-top:.75rem;">Admin</div>
            <a href="admin_dashboard.php" class="sidebar-link">
                <span class="sidebar-link-icon">⚡</span>
                Admin Panel
            </a>
            <a href="staff_management.php" class="sidebar-link">
                <span class="sidebar-link-icon">👥</span>
                Staff Mgmt
            </a>
            <?php endif; ?>

            <div class="sidebar-section-label" style="margin-top:.75rem;">System</div>
            <button class="sidebar-link" onclick="runUploadSelfTest()">
                <span class="sidebar-link-icon">🧪</span>
                Upload Test
            </button>
            <a href="change_password.php" class="sidebar-link">
                <span class="sidebar-link-icon">🔑</span>
                Change Password
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['fullname'] ?? 'S', 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Staff') ?></div>
                <div class="sidebar-user-role"><?= ucfirst($role) ?></div>
            </div>
            <a href="logout.php" class="sidebar-logout" title="Logout">⬡</a>
        </div>
    </aside>

    <!-- ===== NOTIFICATION PANEL ===== -->
    <div class="notif-panel-overlay" id="notifOverlay" onclick="closeNotifPanel()"></div>
    <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-header">
            <h3>🔔 New Orders</h3>
            <button class="notif-panel-close" onclick="closeNotifPanel()">✕</button>
        </div>
        <div class="notif-list" id="notifList">
            <div class="notif-empty">No new notifications</div>
        </div>
        <div class="notif-panel-footer">
            <button class="menu-btn secondary" style="width:100%;justify-content:center;" onclick="markAllSeen()">✓ Mark all as seen</button>
        </div>
    </div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <?php if ($uploads_warning !== ''): ?>
        <div style="background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;padding:.85rem 1rem;border-radius:10px;margin:0 0 1.5rem 0;font-size:.86rem;">
            <strong>⚠ Upload warning:</strong> <?= htmlspecialchars($uploads_warning) ?>
        </div>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <p class="page-greeting">Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars(explode(' ', $_SESSION['fullname'] ?? 'there')[0]) ?>! ☕</p>
            <h1 class="page-title">Store <span>Dashboard</span></h1>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🛍️</span>
                <div><div class="stat-label">Total Products</div><div class="stat-value" data-target="<?= $s['products'] ?>">0</div></div>
            </div>
            <div class="stat-card green">
                <span class="stat-icon">✅</span>
                <div><div class="stat-label">Available</div><div class="stat-value" data-target="<?= $s['available'] ?>">0</div></div>
            </div>
            <div class="stat-card amber">
                <span class="stat-icon">⏳</span>
                <div><div class="stat-label">Active Orders</div><div class="stat-value" data-target="<?= $s['pending'] ?>">0</div></div>
            </div>
            <div class="stat-card blue">
                <span class="stat-icon">📅</span>
                <div><div class="stat-label">Today's Orders</div><div class="stat-value" data-target="<?= $s['today'] ?>">0</div></div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="dashboard-section">
            <div class="tab-bar">
                <button class="tab-btn active" onclick="switchTab('orders',this)">📋 Orders</button>
                <button class="tab-btn" onclick="switchTab('products',this)">🛍️ Products</button>
                <button class="tab-btn" onclick="switchTab('add',this)"><?= $edit_id > 0 ? '✏️ Edit Product' : '➕ Add Product' ?></button>
            </div>


        <!-- ===== ORDERS TAB ===== -->
        <div id="tab-orders" class="tab-content active" <?= $edit_id > 0 ? 'style="display:none"' : '' ?>>
            <div class="table-wrapper" style="border:none;border-radius:0;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders): ?>
                            <?php foreach ($orders as $ord): ?>
                                <tr id="order-row-<?= $ord['id'] ?>">
                                    <td><strong>#<?= $ord['id'] ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($ord['customer_name']) ?></strong>
                                        <?php if ($ord['customer_email']): ?>
                                            <br><small style="color:var(--text-light)"><?= htmlspecialchars($ord['customer_email']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($ord['customer_phone']) ?></td>
                                    <td>
                                        <ul class="order-items-list">
                                            <?php foreach ($ord['items'] as $item): ?>
                                                <li><?= htmlspecialchars($item) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td><strong>₱<?= number_format($ord['total_amount'],2) ?></strong></td>
                                    <td class="order-notes-cell"><?= htmlspecialchars($ord['notes'] ?? '—') ?></td>
                                    <td>
                                        <select class="status-select" data-order-id="<?= $ord['id'] ?>" data-status="<?= $ord['status'] ?>" onchange="updateOrderStatus(this);this.setAttribute('data-status',this.value)">
                                            <?php foreach ($statuses as $st): ?>
                                                <option value="<?= $st ?>" <?= $ord['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="font-size:.78rem;white-space:nowrap;"><?= date('M d, Y g:i A', strtotime($ord['created_at'])) ?></td>
                                    <td>
                                        <button class="menu-btn danger" style="font-size:.75rem;padding:.3rem .65rem;" onclick="markProcessed(<?= $ord['id'] ?>)">
                                            ✓ Process
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align:center;color:var(--text-light);padding:3rem;">No orders yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== PRODUCTS TAB ===== -->
        <div id="tab-products" class="tab-content">
            <div class="table-wrapper" style="border:none;border-radius:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products): ?>
                            <?php foreach ($products as $p): ?>
                                <tr id="prod-row-<?= $p['id'] ?>">
                                    <td>
                                        <?php
                                        $thumb = $p['image_url'] ?? '';
                                        // Local uploads are stored as "uploads/file.jpg" — use as-is (same directory level)
                                        // External URLs are used as-is
                                        if ($thumb):
                                        ?>
                                            <img src="<?= htmlspecialchars($thumb) ?>" class="product-img-thumb" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex'">
                                            <span class="product-img-placeholder" style="display:none;">🍽️</span>
                                        <?php else: ?>
                                            <span class="product-img-placeholder">🍽️</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                    <td><span class="role-badge user"><?= htmlspecialchars($p['category']) ?></span></td>
                                    <td style="max-width:200px;font-size:.82rem;color:var(--text-light);"><?= htmlspecialchars($p['description']) ?></td>
                                    <td><strong>₱<?= number_format($p['price'],2) ?></strong></td>
                                    <td>
                                        <button type="button" class="avail-toggle <?= $p['is_available']?'on':'off' ?>"
                                            onclick="toggleAvailability(<?= $p['id'] ?>, this)">
                                            <?= $p['is_available'] ? '✓ Available' : '✗ Hidden' ?>
                                        </button>
                                    </td>
                                    <td class="action-cell">
                                        <div class="action-buttons">
                                            <a href="?edit_product=<?= $p['id'] ?>" class="edit-btn">✏️ Edit</a>
                                            <button type="button" class="delete-btn" onclick="deleteProduct(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES, 'UTF-8') ?>, this)">🗑 Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:3rem;">No products yet. Add some using the Add Product tab.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== ADD/EDIT PRODUCT TAB ===== -->
        <div id="tab-add" class="tab-content" <?= $edit_id > 0 ? 'style="display:block"' : '' ?>>
            <div class="section-body">
                <h3 style="font-family:'Playfair Display',serif;color:var(--brown-dark);margin-bottom:1.5rem;">
                    <?= $edit_id > 0 ? '✏️ Edit Product: ' . htmlspecialchars($pte['name']) : '➕ Add New Product' ?>
                </h3>
                <form method="POST" action="save_product.php" id="productForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <?php if ($edit_id > 0): ?><input type="hidden" name="id" value="<?= $edit_id ?>"><?php endif; ?>
                    <div class="product-form">
                        <div class="field-stack">
                            <label for="pname">Product Name *</label>
                            <input type="text" id="pname" name="name" value="<?= htmlspecialchars($pte['name']) ?>" placeholder="e.g. Signature Latte" required>
                        </div>
                        <div class="field-stack">
                            <label for="pcategory">Category *</label>
                            <select id="pcategory" name="category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= $pte['category']===$cat?'selected':'' ?>><?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-stack">
                            <label for="pprice">Price (₱) *</label>
                            <input type="number" id="pprice" name="price" step="0.01" min="0" value="<?= htmlspecialchars($pte['price']) ?>" placeholder="0.00" required>
                        </div>
                        <div class="field-stack">
                            <label for="pavail">Availability</label>
                            <select id="pavail" name="is_available">
                                <option value="1" <?= $pte['is_available']?'selected':'' ?>>✓ Available</option>
                                <option value="0" <?= !$pte['is_available']?'selected':'' ?>>✗ Hidden from Menu</option>
                            </select>
                        </div>
                        <div class="field-stack full">
                            <label for="pdesc">Description *</label>
                            <textarea id="pdesc" name="description" rows="3" placeholder="Describe the product in 1–2 sentences" required><?= htmlspecialchars($pte['description']) ?></textarea>
                        </div>
                        <div class="field-stack full">
                            <label>Product Image</label>

                            <!-- Image source toggle -->
                            <div class="img-source-tabs">
                                <button type="button" class="img-tab active" onclick="switchImgTab('upload', this)">📁 Upload File</button>
                                <button type="button" class="img-tab" onclick="switchImgTab('url', this)">🔗 Use URL</button>
                            </div>

                            <!-- Current image preview -->
                            <?php
                            $cur = $pte['image_url'] ?? '';
                            $isLocal = $cur && strpos($cur, 'uploads/') === 0;
                            $isUrl   = $cur && !$isLocal;
                            $imgSrc  = $cur; // local uploads/xxx or external URL — both served relative to root
                            ?>
                            <div id="currentImgWrap" style="<?= $cur ? '' : 'display:none;' ?>margin-bottom:.75rem;">
                                <div style="font-size:.75rem;color:var(--text-light);margin-bottom:.35rem;">
                                    Current image:
                                    <span id="currentImgType"><?= $isLocal ? '📁 Uploaded file' : '🔗 External URL' ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:.75rem;">
                                    <img id="currentImgThumb"
                                         src="<?= htmlspecialchars($imgSrc) ?>"
                                         alt="Current"
                                         style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:2px solid var(--cream-border);">
                                    <button type="button" onclick="clearCurrentImage()" class="menu-btn secondary" style="font-size:.78rem;padding:.3rem .75rem;">
                                        ✕ Remove image
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="image_url" id="imageUrlHidden" value="<?= htmlspecialchars($cur) ?>">
                            <input type="hidden" name="remove_image" id="removeImageFlag" value="0">

                            <!-- Upload panel -->
                            <div id="imgPanel-upload" class="img-panel">
                                <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('image_file').click()">
                                    <div class="dropzone-icon">🖼️</div>
                                    <div class="dropzone-text">
                                        <strong>Click to upload</strong> or drag &amp; drop
                                    </div>
                                    <div class="dropzone-sub">JPG, PNG, GIF, WEBP — max <?= htmlspecialchars($effective_upload_label) ?></div>
                                    <div id="dropzoneFileName" class="dropzone-filename" style="display:none;"></div>
                                </div>
                                <input type="file" id="image_file" name="image_file"
                                       accept="image/jpeg,image/png,image/gif,image/webp"
                                       style="display:none;"
                                       onchange="handleFileSelect(this)">
                                <div id="uploadPreviewWrap" style="display:none;margin-top:.75rem;">
                                    <div style="font-size:.75rem;color:var(--text-light);margin-bottom:.3rem;">Preview:</div>
                                    <img id="uploadPreview" src="" alt="Preview"
                                         style="max-width:180px;max-height:120px;border-radius:8px;border:2px solid var(--cream-border);object-fit:cover;">
                                </div>
                            </div>

                            <!-- URL panel -->
                            <div id="imgPanel-url" class="img-panel" style="display:none;">
                                <input type="text" id="pimage_url_input"
                                       placeholder="https://images.unsplash.com/photo-…?w=600&q=80"
                                       value="<?= $isUrl ? htmlspecialchars($cur) : '' ?>"
                                       oninput="handleUrlInput(this.value)">
                                <div id="urlPreviewWrap" style="display:none;margin-top:.75rem;">
                                    <div style="font-size:.75rem;color:var(--text-light);margin-bottom:.3rem;">Preview:</div>
                                    <img id="urlPreview" src="" alt="Preview"
                                         style="max-width:180px;max-height:120px;border-radius:8px;border:2px solid var(--cream-border);object-fit:cover;"
                                         onerror="this.style.display='none';document.getElementById('urlBadge').style.display='inline'">
                                    <span id="urlBadge" style="display:none;color:#dc2626;font-size:.78rem;">⚠ Could not load image from this URL</span>
                                </div>
                                <small style="color:var(--text-light);font-size:.75rem;margin-top:.4rem;display:block;">
                                    Tip: <a href="https://unsplash.com" target="_blank" style="color:var(--brown-warm);">Unsplash.com</a>
                                    has free high-quality food &amp; drink photos. Append <code>?w=600&amp;q=80</code> for a fast-loading size.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:1rem;margin-top:1.5rem;flex-wrap:wrap;">
                        <button type="submit" class="menu-btn primary" style="padding:.8rem 2rem;">
                            <?= $edit_id > 0 ? '💾 Save Changes' : '➕ Add Product' ?>
                        </button>
                        <?php if ($edit_id > 0): ?>
                            <a href="store_dashboard.php" class="menu-btn secondary" style="padding:.8rem 1.5rem;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        </div><!-- /dashboard-section -->
    </main><!-- /main-content -->
</div><!-- /app-layout -->


<script>
/* ── Show redirect messages from URL params (success= / error=) ── */
(function () {
    const p = new URLSearchParams(window.location.search);
    const msg_success = p.get('success');
    const msg_error   = p.get('error');
    if (msg_success || msg_error) {
        // Wait for DOM then show alert
        window.addEventListener('DOMContentLoaded', function () {
            if (msg_success) showAlert('✓ ' + decodeURIComponent(msg_success), 'success');
            if (msg_error)   showAlert('✗ ' + decodeURIComponent(msg_error),   'error');
        });
        // Clean URL so alerts don't reshow on manual refresh
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, '', cleanUrl);
    }
})();

/* Tab switching */
function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (btn) btn.classList.add('active');
}
function switchToAdd() {
    switchTab('add', document.querySelectorAll('.tab-btn')[2]);
}
<?php if ($edit_id > 0): ?>
window.addEventListener('DOMContentLoaded', () => {
    switchTab('add', document.querySelectorAll('.tab-btn')[2]);
    setTimeout(() => {
        const t = document.getElementById('tab-add');
        if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 80);
    <?php if (isset($isUrl) && $isUrl): ?>
    const imgTabs = document.querySelectorAll('.img-tab');
    if (imgTabs.length >= 2) switchImgTab('url', imgTabs[1]);
    <?php endif; ?>
});
<?php endif; ?>

/* ── Image Upload / URL Handling ─────────────────────── */
const clientMaxUploadBytes = <?= (int) $effective_upload_bytes ?>;
const clientMaxUploadLabel = <?= json_encode($effective_upload_label) ?>;

// Switch between Upload and URL tabs
function switchImgTab(mode, btn) {
    document.querySelectorAll('.img-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.img-panel').forEach(p => p.style.display = 'none');
    btn.classList.add('active');
    document.getElementById('imgPanel-' + mode).style.display = 'block';
    // If switching to URL tab, restore URL value from hidden field if it's an external URL
    if (mode === 'url') {
        const hidden = document.getElementById('imageUrlHidden');
        const urlInput = document.getElementById('pimage_url_input');
        if (hidden.value && hidden.value.startsWith('http') && urlInput) {
            urlInput.value = hidden.value;
            handleUrlInput(hidden.value);
        }
    }
}

// File selected via click or drag-drop
function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;

    // Client-side size check (effective server/app cap)
    if (file.size > clientMaxUploadBytes) {
        alert('Image must be under ' + clientMaxUploadLabel + '. Please choose a smaller file.');
        input.value = '';
        return;
    }

    // Show filename in dropzone
    const nameEl = document.getElementById('dropzoneFileName');
    if (nameEl) { nameEl.textContent = '📎 ' + file.name; nameEl.style.display = 'block'; }

    // Preview
    const reader = new FileReader();
    reader.onload = e => {
        const wrap = document.getElementById('uploadPreviewWrap');
        const img  = document.getElementById('uploadPreview');
        if (wrap && img) { img.src = e.target.result; wrap.style.display = 'block'; }
    };
    reader.readAsDataURL(file);

    // Clear the hidden URL field — file upload takes priority
    document.getElementById('imageUrlHidden').value = '';
    // Reset remove flag — user is now providing a new file
    const flagF = document.getElementById('removeImageFlag');
    if (flagF) flagF.value = '0';
    // Hide current image strip since we're replacing it
    document.getElementById('currentImgWrap').style.display = 'none';
}

// URL typed into the URL input
function handleUrlInput(url) {
    const hidden    = document.getElementById('imageUrlHidden');
    const wrap      = document.getElementById('urlPreviewWrap');
    const img       = document.getElementById('urlPreview');
    const badge     = document.getElementById('urlBadge');
    if (!hidden || !wrap || !img) return;

    // Clear file input when URL is entered
    const fileInput = document.getElementById('image_file');
    if (fileInput) fileInput.value = '';
    document.getElementById('uploadPreviewWrap').style.display = 'none';
    document.getElementById('dropzoneFileName').style.display = 'none';

    if (url.startsWith('http')) {
        hidden.value = url;
        img.src = url;
        img.style.display = 'block';
        if (badge) badge.style.display = 'none';
        wrap.style.display = 'block';
        // Reset remove flag — user is providing a new URL
        const flagU = document.getElementById('removeImageFlag');
        if (flagU) flagU.value = '0';
        // Hide current image strip
        document.getElementById('currentImgWrap').style.display = 'none';
    } else {
        hidden.value = '';
        wrap.style.display = 'none';
    }
}

// Remove current image (sets remove_image flag so PHP actually clears it)
function clearCurrentImage() {
    const flag = document.getElementById('removeImageFlag');
    if (flag) flag.value = '1';
    document.getElementById('imageUrlHidden').value = '';
    document.getElementById('currentImgWrap').style.display = 'none';
    const fileInput = document.getElementById('image_file');
    if (fileInput) fileInput.value = '';
    const previewWrap = document.getElementById('uploadPreviewWrap');
    if (previewWrap) previewWrap.style.display = 'none';
    const nameEl = document.getElementById('dropzoneFileName');
    if (nameEl) { nameEl.textContent = ''; nameEl.style.display = 'none'; }
    const urlInput = document.getElementById('pimage_url_input');
    if (urlInput) urlInput.value = '';
    const urlWrap = document.getElementById('urlPreviewWrap');
    if (urlWrap) urlWrap.style.display = 'none';
    const urlBadge = document.getElementById('urlBadge');
    if (urlBadge) urlBadge.style.display = 'none';
}

/* Uploads self-test: verifies folder existence, writability, and real write/delete */
function runUploadSelfTest() {
    fetch('upload_self_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showAlert('✓ ' + (d.message || 'Upload self-test passed.'), 'success');
        } else {
            showAlert('✗ ' + (d.message || 'Upload self-test failed.'), 'error');
        }
    })
    .catch(() => showAlert('✗ Upload self-test request failed.', 'error'));
}

// Drag-and-drop on the dropzone
document.addEventListener('DOMContentLoaded', function () {
    const dz = document.getElementById('dropzone');
    if (!dz) return;
    ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
    dz.addEventListener('drop', function (e) {
        const file = e.dataTransfer?.files?.[0];
        if (!file || !file.type.startsWith('image/')) return;
        const fi = document.getElementById('image_file');
        const dt = new DataTransfer();
        dt.items.add(file);
        fi.files = dt.files;
        handleFileSelect(fi);
    });
});

/* Order status update */
function updateOrderStatus(sel) {
    const id = sel.dataset.orderId;
    const status = sel.value;
    fetch('update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status)
    })
    .then(r => r.json())
    .then(d => showAlert(d.success ? '✓ Status updated to ' + status : (d.message || 'Update failed.'), d.success ? 'success' : 'error'))
    .catch(() => showAlert('Update failed.', 'error'));
}

/* Mark processed */
function markProcessed(id) {
    if (!confirm('Mark order #' + id + ' as completed?')) return;
    fetch('update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + encodeURIComponent(id) + '&status=completed'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const row = document.getElementById('order-row-' + id);
            if (row) {
                row.style.opacity = '0.5';
                row.style.pointerEvents = 'none';
                // Update the status dropdown to reflect completed status
                const sel = row.querySelector('.status-select');
                if (sel) { sel.value = 'completed'; sel.disabled = true; }
            }
            showAlert('✓ Order #' + id + ' marked as completed.', 'success');
        } else { showAlert(d.message || 'Failed.', 'error'); }
    });
}

/* Toggle availability */
function toggleAvailability(id, btn) {
    const newVal = btn.classList.contains('on') ? 0 : 1;
    fetch('save_product.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'toggle_availability=1&id=' + encodeURIComponent(id) + '&is_available=' + newVal + '&csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            btn.classList.toggle('on', newVal === 1);
            btn.classList.toggle('off', newVal === 0);
            btn.textContent = newVal === 1 ? '✓ Available' : '✗ Hidden';
            showAlert(newVal === 1 ? '✓ Product is now visible on menu.' : '✓ Product hidden from menu.', 'success');
        } else { showAlert(d.message || 'Failed.', 'error'); }
    });
}

/* Custom Delete Modal */
function showConfirmModal(title, message, onConfirm) {
    const existing = document.getElementById('customConfirmModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'customConfirmModal';
    overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px);';

    const box = document.createElement('div');
    box.style.cssText = 'background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; max-width: 400px; width: 90%; font-family: sans-serif;';
    
    box.innerHTML = `
        <h3 style="margin: 0 0 1rem 0; color: #991b1b; font-size: 1.2rem;">${title}</h3>
        <p style="margin: 0 0 1.5rem 0; color: #4b5563; font-size: 0.95rem; line-height: 1.5;">${message}</p>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <button id="modalCancelBtn" style="padding: 0.6rem 1.2rem; border: none; background: #e5e7eb; border-radius: 6px; cursor: pointer; color: #374151; font-weight: 600; font-size: 0.9rem; transition: background 0.2s;">Cancel</button>
            <button id="modalConfirmBtn" style="padding: 0.6rem 1.2rem; border: none; background: #fee2e2; border-radius: 6px; cursor: pointer; color: #991b1b; font-weight: 600; font-size: 0.9rem; transition: background 0.2s;">Confirm Delete</button>
        </div>
    `;
    
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    document.getElementById('modalCancelBtn').onclick = () => overlay.remove();
    document.getElementById('modalConfirmBtn').onclick = () => {
        overlay.remove();
        onConfirm();
    };
}

/* Delete product */
function deleteProduct(id, name, btnElement) {
    showConfirmModal('Delete Product?', 'Are you sure you want to delete "' + name + '"? This cannot be undone.', () => {
        // Disable button to prevent double-clicks
        if (btnElement) {
            btnElement.disabled = true;
            btnElement.textContent = 'Deleting...';
        }

        fetch('delete_product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id) + '&csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>'
        })
        .then(async r => {
            if (!r.ok) {
                const rawText = await r.text();
                throw new Error(`HTTP ${r.status}: ${rawText.substring(0, 100)}`);
            }
            return r.json();
        })
        .then(d => {
            if (d.success) {
                const row = document.getElementById('prod-row-' + id);
                if (row) row.remove();
                showAlert('🗑 Product deleted.', 'success');
            } else {
                if (d.message === 'Unauthorized' || d.message === 'Invalid token.') {
                    showAlert('Session expired. Redirecting to login...', 'error');
                    setTimeout(() => window.location.href = 'login.html', 1500);
                    return;
                }
                showAlert(d.message || 'Delete failed.', 'error');
                if (btnElement) {
                    btnElement.disabled = false;
                    btnElement.textContent = '🗑 Delete';
                }
            }
        })
        .catch(e => {
            console.error("Delete Error:", e);
            showAlert('Error: ' + e.message, 'error');
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.textContent = '🗑 Delete';
            }
        });
    });
}

/* Alert helper */
function showAlert(msg, type) {
    const c = document.getElementById('alertContainer');
    const el = document.createElement('div');
    el.className = 'alert-msg ' + (type || '');
    el.textContent = msg;
    // Add a close button for errors so they don't disappear before being read
    if (type === 'error') {
        el.style.cursor = 'pointer';
        el.title = 'Click to dismiss';
        el.addEventListener('click', () => el.remove());
    }
    c.appendChild(el);
    const timeout = type === 'error' ? 8000 : 4000; // errors stay 8s
    setTimeout(() => { if (el.parentNode) el.remove(); }, timeout);
}

/* Auto-refresh orders every 60s */
const _isEditMode = <?= $edit_id > 0 ? 'true' : 'false' ?>;
let _userHasTyped = false;
document.getElementById('pname')?.addEventListener('input', () => { _userHasTyped = true; });
document.getElementById('pdesc')?.addEventListener('input', () => { _userHasTyped = true; });
setInterval(() => {
    const ordersActive = document.getElementById('tab-orders')?.classList.contains('active');
    if (ordersActive && !_userHasTyped) location.reload();
}, 60000);

/* ── Sidebar active link sync ────────────────── */
function setSidebarActive(el) {
    document.querySelectorAll('.sidebar .sidebar-link').forEach(l => l.classList.remove('active'));
    el.classList.add('active');
}

/* ── Mobile sidebar toggle ───────────────────── */
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('mobile-open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
});
document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('sidebarOverlay').classList.remove('show');
});

/* ── Count-up animation ──────────────────────── */
function animateCountUp(el, target, duration = 900) {
    let start = 0;
    const step = Math.ceil(target / (duration / 16));
    const timer = setInterval(() => {
        start += step;
        if (start >= target) { el.textContent = target; clearInterval(timer); }
        else { el.textContent = start; }
    }, 16);
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stat-value[data-target]').forEach(el => {
        const target = parseInt(el.dataset.target) || 0;
        animateCountUp(el, target);
    });
});

/* ── Notification system ─────────────────────── */
let _pendingOrders = [];
let _maxSeenId = 0;

function chime() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine'; osc.frequency.setValueAtTime(880, ctx.currentTime);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.6);
    } catch(e) {}
}

function pollNotifications() {
    fetch('notifications.php')
        .then(r => r.json())
        .then(d => {
            if (d.count > 0) {
                _pendingOrders = d.orders;
                _maxSeenId = d.max_id;
                updateBell(d.count);
                d.orders.forEach(o => showOrderToast(o));
                chime();
            }
        })
        .catch(() => {});
}

function updateBell(count) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.classList.add('show');
    } else {
        badge.classList.remove('show');
    }
}

function showOrderToast(order) {
    const c = document.getElementById('alertContainer');
    const el = document.createElement('div');
    el.className = 'alert-msg info';
    el.innerHTML = `🛒 New order from <strong>${order.customer}</strong> — ₱${order.total}`;
    el.style.cursor = 'pointer';
    el.title = 'Click to view orders';
    el.addEventListener('click', () => { switchTab('orders', document.querySelector('.tab-btn')); el.remove(); });
    c.appendChild(el);
    setTimeout(() => { if (el.parentNode) el.remove(); }, 6000);
}

function openNotifPanel() {
    const panel   = document.getElementById('notifPanel');
    const overlay = document.getElementById('notifOverlay');
    const list    = document.getElementById('notifList');
    panel?.classList.add('open');
    overlay?.classList.add('open');
    // Render items
    if (_pendingOrders.length === 0) {
        list.innerHTML = '<div class="notif-empty">🎉 All caught up! No new orders.</div>';
    } else {
        list.innerHTML = _pendingOrders.map(o => `
            <div class="notif-item">
                <div class="notif-item-top">
                    <span class="notif-item-customer">${o.customer}</span>
                    <span class="notif-item-time">${o.time}</span>
                </div>
                <div class="notif-item-items">${o.items}</div>
                <div class="notif-item-total">₱${o.total}</div>
            </div>
        `).join('');
    }
}

function closeNotifPanel() {
    document.getElementById('notifPanel')?.classList.remove('open');
    document.getElementById('notifOverlay')?.classList.remove('open');
}

function markAllSeen() {
    if (_maxSeenId > 0) {
        fetch('mark_notifications_seen.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'max_id=' + _maxSeenId
        });
    }
    _pendingOrders = [];
    updateBell(0);
    closeNotifPanel();
}

// Start polling after 5s delay, then every 20s
setTimeout(() => { pollNotifications(); setInterval(pollNotifications, 20000); }, 5000);
</script>
</body>
</html>
