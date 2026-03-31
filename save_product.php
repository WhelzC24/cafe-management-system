<?php
/**
 * save_product.php — Add or edit a product, with optional image upload
 *
 * Handles three requests:
 *   1. AJAX POST with toggle_availability=1  → toggles product visibility
 *   2. Multipart POST (form submit)          → saves new or edited product
 */

/*
 * Upload limits are configured at server level (.htaccess / .user.ini / php.ini).
 * We cap at app target, but never exceed effective PHP runtime limits.
 */
const APP_MAX_IMAGE_BYTES = 20 * 1024 * 1024; // 20 MB target

$php_upload_bytes = return_bytes((string) ini_get('upload_max_filesize'));
$php_post_bytes   = return_bytes((string) ini_get('post_max_size'));
$effective_php_bytes = min(
    $php_upload_bytes > 0 ? $php_upload_bytes : PHP_INT_MAX,
    $php_post_bytes > 0 ? $php_post_bytes : PHP_INT_MAX
);
if ($effective_php_bytes === PHP_INT_MAX) {
    $effective_php_bytes = APP_MAX_IMAGE_BYTES;
}
$effective_max_bytes = min(APP_MAX_IMAGE_BYTES, $effective_php_bytes);
$effective_max_mb = max(1, (int) floor($effective_max_bytes / (1024 * 1024)));

session_start();
require_once 'db.php';

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'staff'], true)) {
    header('Location: login.html');
    exit;
}

/* ================================================================
   AJAX: Toggle availability  (fetch from JS)
   ================================================================ */
if (isset($_POST['toggle_availability'])) {
    header('Content-Type: application/json');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid token.']);
        exit;
    }
    $id    = (int) ($_POST['id'] ?? 0);
    $avail = ((int) ($_POST['is_available'] ?? 0)) ? 1 : 0;
    $st    = mysqli_prepare($conn, 'UPDATE products SET is_available=? WHERE id=?');
    mysqli_stmt_bind_param($st, 'ii', $avail, $id);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    echo json_encode(['success' => $ok]);
    exit;
}

/* ================================================================
   Normal product save (multipart form POST)
   ================================================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: store_dashboard.php');
    exit;
}

/* ── Detect post_max_size exceeded (PHP discards entire body) ─── */
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    $maxPost = return_bytes(ini_get('post_max_size'));
    if ((int) $_SERVER['CONTENT_LENGTH'] > $maxPost) {
        header('Location: store_dashboard.php?error=' . urlencode(
            'Upload failed: file is too large for the server. Try a smaller image (under ' . $effective_max_mb . ' MB).'
        ));
        exit;
    }
}

/* ── CSRF ──────────────────────────────────────────────────────── */
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header('Location: store_dashboard.php?error=' . urlencode('Security token mismatch. Please refresh and try again.'));
    exit;
}

/* ── Read form fields ──────────────────────────────────────────── */
$id           = (int)   ($_POST['id']           ?? 0);
$name         = trim(    $_POST['name']          ?? '');
$category     = trim(    $_POST['category']      ?? '');
$description  = trim(    $_POST['description']   ?? '');
$price        = (float)  ($_POST['price']        ?? 0);
$is_available = (int)    ($_POST['is_available'] ?? 1);

if ($name === '' || $category === '' || $description === '' || $price <= 0) {
    header('Location: store_dashboard.php?error=' . urlencode('All required fields must be filled in.'));
    exit;
}

/* ── Fetch existing image (for edit mode) ──────────────────────── */
$existing_image = '';
if ($id > 0) {
    $chk = mysqli_prepare($conn, 'SELECT image_url FROM products WHERE id=?');
    mysqli_stmt_bind_param($chk, 'i', $id);
    mysqli_stmt_execute($chk);
    $chk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    mysqli_stmt_close($chk);
    $existing_image = $chk_row['image_url'] ?? '';
}

$image_url = $existing_image; // default: keep whatever is already stored

/* ── Priority 0: Explicit remove button pressed ────────────────── */
$explicit_remove = (($_POST['remove_image'] ?? '0') === '1');
if ($explicit_remove) {
    if ($existing_image && strpos($existing_image, 'uploads/') === 0) {
        $old = __DIR__ . '/' . $existing_image;
        if (file_exists($old)) {
            @unlink($old);
        }
    }
    $image_url = '';
    // Fall through to DB save with empty image_url
}

/* ── Priority 1: File was uploaded ────────────────────────────── */
elseif (isset($_FILES['image_file']) && is_array($_FILES['image_file'])) {
    $file_error = (int) ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($file_error === UPLOAD_ERR_OK) {
        $file    = $_FILES['image_file'];
        $maxSize = $effective_max_bytes;

        /* Size check */
        if ((int) $file['size'] > $maxSize) {
            header('Location: store_dashboard.php?error=' . urlencode('Image must be under ' . $effective_max_mb . ' MB.'));
            exit;
        }

        /* MIME type check — with fallback to extension if fileinfo unavailable */
        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $mimeType = '';
        if (function_exists('finfo_open')) {
            $fi       = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = (string) finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
        }

        /* If finfo returned empty/false, fall back to extension */
        if ($mimeType === '' || $mimeType === 'application/octet-stream') {
            $ext_from_name = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext_from_name, $allowed_exts, true)) {
                // Map extension to a safe MIME for our lookup
                $ext_mime_map = [
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png'  => 'image/png',
                    'gif'  => 'image/gif',
                    'webp' => 'image/webp',
                ];
                $mimeType = $ext_mime_map[$ext_from_name] ?? '';
            }
        }

        if (!array_key_exists($mimeType, $allowed_mimes)) {
            header('Location: store_dashboard.php?error=' . urlencode(
                'Invalid image type. Please upload a JPG, PNG, GIF, or WEBP file.'
            ));
            exit;
        }

        $ext = $allowed_mimes[$mimeType];

        /* Build safe unique filename */
        $safeName = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '_', $name)));
        $safeName = $safeName !== '' ? substr($safeName, 0, 40) : 'product';
        $filename = $safeName . '_' . uniqid() . '.' . $ext;

        /* Ensure uploads/ directory exists and is writable by PHP worker user */
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                header('Location: store_dashboard.php?error=' . urlencode(
                    'Could not create uploads folder. Please create the "uploads/" folder manually and make it writable.'
                ));
                exit;
            }
        }

        /* Try to make it group-writable before failing hard */
        if (!is_writable($uploadDir)) {
            @chmod($uploadDir, 0775);
            clearstatcache(true, $uploadDir);
        }

        if (!is_writable($uploadDir)) {
            $mode = @fileperms($uploadDir);
            $mode_text = $mode ? substr(sprintf('%o', $mode), -4) : 'unknown';
            header('Location: store_dashboard.php?error=' . urlencode(
                'Uploads folder is not writable by the web server user. '
                . 'Set owner/group so PHP can write (for example: chown -R http:http uploads and chmod 775 uploads). '
                . 'Current mode: ' . $mode_text . '.'
            ));
            exit;
        }

        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            header('Location: store_dashboard.php?error=' . urlencode(
                'Failed to move uploaded file. Check folder permissions on uploads/.'
            ));
            exit;
        }

        /* Delete old local file when replacing */
        if ($existing_image && strpos($existing_image, 'uploads/') === 0) {
            $old = __DIR__ . '/' . $existing_image;
            if (file_exists($old)) {
                @unlink($old);
            }
        }

        $image_url = 'uploads/' . $filename;

    } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
        /* A file was attempted but something went wrong — show specific message */
        $upload_error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds PHP upload_max_filesize limit. Use a smaller image (under ' . $effective_max_mb . ' MB).',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temporary folder configured. Contact your host.',
            UPLOAD_ERR_CANT_WRITE => 'Server cannot write the uploaded file. Contact your host.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        $err_msg = $upload_error_messages[$file_error] ?? "Upload failed with error code $file_error.";
        header('Location: store_dashboard.php?error=' . urlencode($err_msg));
        exit;
    }
    // UPLOAD_ERR_NO_FILE (4) means no file was chosen — keep existing image
}

/* ── Priority 2: URL was entered ───────────────────────────────── */
elseif (trim($_POST['image_url'] ?? '') !== '') {
    $url_input = trim($_POST['image_url']);
    if (filter_var($url_input, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url_input)) {
        /* Remove old local file when switching to URL */
        if ($existing_image && strpos($existing_image, 'uploads/') === 0) {
            $old = __DIR__ . '/' . $existing_image;
            if (file_exists($old)) {
                @unlink($old);
            }
        }
        $image_url = $url_input;
    } else {
        header('Location: store_dashboard.php?error=' . urlencode(
            'Invalid image URL. It must start with http:// or https://'
        ));
        exit;
    }
}
// else: no change → keep $image_url = $existing_image

/* ── Save to database ──────────────────────────────────────────── */
if ($id > 0) {
    /* UPDATE existing product */
    $st = mysqli_prepare($conn,
        'UPDATE products SET name=?, category=?, description=?, price=?, image_url=?, is_available=? WHERE id=?'
    );
    mysqli_stmt_bind_param($st, 'sssdsii',
        $name, $category, $description, $price, $image_url, $is_available, $id
    );
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    header('Location: store_dashboard.php?' . ($ok
        ? 'success=' . urlencode('Product updated successfully.')
        : 'error='   . urlencode('Database update failed. Please try again.')
    ));
} else {
    /* INSERT new product */
    $st = mysqli_prepare($conn,
        'INSERT INTO products (name, category, description, price, image_url, is_available) VALUES (?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param($st, 'sssdsi',
        $name, $category, $description, $price, $image_url, $is_available
    );
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    header('Location: store_dashboard.php?' . ($ok
        ? 'success=' . urlencode('Product added successfully.')
        : 'error='   . urlencode('Failed to add product. Please try again.')
    ));
}
exit;

/* ── Helper: convert PHP size shorthand to bytes ─────────────────── */
function return_bytes(string $val): int {
    $val  = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $num  = (int) $val;
    switch ($last) {
        case 'g': $num *= 1024;
        // fall through
        case 'm': $num *= 1024;
        // fall through
        case 'k': $num *= 1024;
    }
    return $num;
}
