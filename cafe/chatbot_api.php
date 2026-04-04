<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../db.php';

function normalize_text(string $text): string {
    // Lowercase and keep letters/numbers/spaces only, so keyword matching is more resilient.
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function digits_only(string $text): string {
    return preg_replace('/\D+/u', '', $text);
}

function extract_phone(string $text): ?string {
    // Prefer a standalone-looking phone number (avoid grabbing digits from order references).
    preg_match_all('/\d{7,15}/u', $text, $m);
    if (empty($m[0])) return null;

    $best = null;
    foreach ($m[0] as $cand) {
        $cand = digits_only($cand);
        if ($best === null || strlen($cand) > strlen($best)) $best = $cand;
    }

    // Typical local numbers are around 10-13 digits; keep it loose but safe.
    if ($best !== null && strlen($best) >= 10) return $best;
    return null;
}

function extract_email(string $text): ?string {
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', $text, $m)) {
        return trim((string)$m[0]);
    }
    return null;
}

function extract_order_id(string $text): ?int {
    // Capture "order #123" and "#123" patterns.
    if (preg_match('/order\s*(?:#|number)?\s*(\d{1,10})/iu', $text, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/#\s*(\d{1,10})/u', $text, $m)) {
        return (int)$m[1];
    }
    return null;
}

function friendly_status(string $status): string {
    $s = strtolower(trim($status));
    return match ($s) {
        'pending' => 'pending',
        'preparing' => 'being prepared',
        'ready' => 'ready for pickup',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => $s,
    };
}

function order_status_lookup($conn, int $orderId, ?string $phoneInput, ?string $emailInput): array {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, status, created_at, total_amount, customer_phone, customer_email FROM orders WHERE id=? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        return ['ok' => false, 'reason' => 'not_found'];
    }

    $storedPhoneDigits = digits_only((string)($row['customer_phone'] ?? ''));
    $storedEmail = strtolower(trim((string)($row['customer_email'] ?? '')));

    if ($phoneInput !== null) {
        if ($storedPhoneDigits !== digits_only($phoneInput)) {
            return ['ok' => false, 'reason' => 'mismatch'];
        }
    } elseif ($emailInput !== null) {
        if ($storedEmail !== strtolower(trim($emailInput))) {
            return ['ok' => false, 'reason' => 'mismatch'];
        }
    } else {
        return ['ok' => false, 'reason' => 'missing_verification'];
    }

    $itemsStmt = mysqli_prepare(
        $conn,
        'SELECT quantity, product_name FROM order_items WHERE order_id=? ORDER BY id ASC'
    );
    mysqli_stmt_bind_param($itemsStmt, 'i', $orderId);
    mysqli_stmt_execute($itemsStmt);
    $itemsRes = mysqli_stmt_get_result($itemsStmt);

    $items = [];
    if ($itemsRes) {
        while ($it = mysqli_fetch_assoc($itemsRes)) {
            $qty = (int)($it['quantity'] ?? 0);
            $name = trim((string)($it['product_name'] ?? ''));
            if ($name !== '' && $qty > 0) $items[] = $qty . 'x ' . $name;
        }
    }
    mysqli_stmt_close($itemsStmt);

    $itemsShort = array_slice($items, 0, 4);
    $itemsText = !empty($itemsShort) ? implode(', ', $itemsShort) : 'items unavailable';

    $createdAt = $row['created_at'] ?? null;
    $createdText = $createdAt ? date('M d, Y g:i A', strtotime((string)$createdAt)) : '—';
    $total = number_format((float)($row['total_amount'] ?? 0), 2);

    return [
        'ok' => true,
        'status' => (string)($row['status'] ?? ''),
        'created_at' => $createdText,
        'total' => $total,
        'items' => $itemsText,
    ];
}

function contains_any($text, $terms) {
    foreach ($terms as $term) {
        if (strpos($text, $term) !== false) {
            return true;
        }
    }
    return false;
}

function menu_snapshot($conn) {
    $items = [];
    $categories = [];

    $result = mysqli_query(
        $conn,
        "SELECT name, category, description, price
         FROM products
         WHERE is_available = 1
         ORDER BY category ASC, name ASC"
    );
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['price'] = (float) $row['price'];
            $items[] = $row;
            $categories[$row['category']] = true;
        }
        mysqli_free_result($result);
    }

    return [
        'items' => $items,
        'categories' => array_keys($categories),
    ];
}

function find_product_in_message($message, $products) {
    foreach ($products as $product) {
        $product_name = normalize_text((string)($product['name'] ?? ''));
        if ($product_name !== '' && strpos($message, $product_name) !== false) {
            return $product;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['answer' => 'Please send your question using a POST request.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim((string)($input['message'] ?? ''));
$normalized = normalize_text($message);

if ($message === '') {
    echo json_encode(['answer' => 'Ask me anything about our menu, hours, location, or pickup orders.']);
    exit;
}

$snapshot = menu_snapshot($conn);
$products = $snapshot['items'];
$categories = $snapshot['categories'];
$product = find_product_in_message($normalized, $products);

$answer = '';

$hasOrderWord = contains_any($normalized, ['order', 'pickup']);
$orderIdMaybe = extract_order_id($message);
$hasTrackingIntent = $hasOrderWord && contains_any($normalized, [
    'status', 'tracking', 'track', 'where is', 'ready', 'preparing', 'completed', 'cancelled', 'pending'
]);
$wantsOrderStatus = $orderIdMaybe !== null || $hasTrackingIntent;

if (contains_any($normalized, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
    $answer = 'Hi! I can help with our menu, item pricing, opening hours, location, and how to place a pickup order.';
} elseif ($wantsOrderStatus) {
    $phoneInput = extract_phone($message);
    $emailInput = extract_email($message);

    if ($orderIdMaybe === null) {
        $answer = 'For order status, please send your order reference number (example: #123) and your phone number used when ordering.';
    } else {
        $lookup = order_status_lookup($conn, $orderIdMaybe, $phoneInput, $emailInput);
        if (($lookup['ok'] ?? false) === true) {
            $statusText = friendly_status((string)$lookup['status']);
            $answer = 'Order #' . $orderIdMaybe . ' is currently ' . $statusText . '.' .
                ' Placed: ' . $lookup['created_at'] . '.' .
                ' Items: ' . $lookup['items'] . '.' .
                ' Total: ₱' . $lookup['total'] . '.';
        } else {
            $reason = (string)($lookup['reason'] ?? '');
            if ($reason === 'not_found') {
                $answer = 'I couldn\'t find an order for reference #' . $orderIdMaybe . '. Please double-check the number.';
            } elseif ($reason === 'mismatch') {
                $answer = 'That order reference doesn\'t match the phone/email you provided. Please try again with the same phone (or email) used during ordering.';
            } else {
                $answer = 'To check your order status, please confirm your order reference (#' . $orderIdMaybe . ') and share the phone number you used when ordering.';
            }
        }
    }
} elseif (contains_any($normalized, ['hour', 'open', 'close', 'time', 'schedule'])) {
    $answer = 'We are open Monday–Friday 7:00 AM–8:00 PM, and Saturday–Sunday 8:00 AM–9:00 PM.';
} elseif (contains_any($normalized, ['where', 'location', 'address', 'find you'])) {
    $answer = 'You can find us at Cuasi, Loon, Bohol.';
} elseif (contains_any($normalized, ['phone', 'call', 'contact', 'email'])) {
    $answer = 'Call us at 09361679546 or email wlaniba330@gmail.com.';
} elseif (contains_any($normalized, ['order', 'pickup', 'place', 'buy'])) {
    $answer = 'To order for pickup: go to the Order for Pickup section by clicking the "Order for Pickup" at the Home section or "Order Now" button above, choose items, then enter your details (Full Name and Phone are required; Email is optional). Finally, tap "Place My Order".';
} elseif ($product && contains_any($normalized, ['price', 'cost', 'how much'])) {
    $price = number_format((float)$product['price'], 2);
    $answer = $product['name'] . ' is PHP ' . $price . '.';
} elseif ($product) {
    $price = number_format((float)$product['price'], 2);
    $desc = trim((string)($product['description'] ?? ''));
    $desc = $desc !== '' ? (' — ' . $desc) : '';
    $answer = $product['name'] . ' is in ' . $product['category'] . ' for PHP ' . $price . '.' . $desc;
} elseif (contains_any($normalized, ['menu', 'categories', 'what do you have', 'what do you sell'])) {
    if (!empty($categories)) {
        $answer = 'Our menu categories are: ' . implode(', ', $categories) . '. If you tell me an item name (like "Cappuccino"), I can give you the price.';
    } else {
        $answer = 'Our menu is currently being updated. Please check back in a moment.';
    }
} elseif (contains_any($normalized, ['recommend', 'best', 'popular', 'favorite', 'special', 'signature'])) {
    // Pick up to 3 items from the currently-available list.
    $top_names = array_slice(array_map(fn($p) => (string)$p['name'], $products), 0, 3);
    if (!empty($top_names)) {
        $answer = 'Popular picks right now: ' . implode(', ', $top_names) . '. Tell me what you like (coffee, cold drinks, pastries), and I can narrow it down.';
    } else {
        $answer = 'Try our Signature Cappuccino, Cinnamon Roll, or Iced Matcha Latte.';
    }
} elseif (contains_any($normalized, ['allergy', 'allergen', 'vegan', 'vegetarian', 'gluten'])) {
    $answer = 'For allergy or dietary details, please call us at 09361679546 so we can confirm ingredients before you order.';
} elseif (contains_any($normalized, ['contact', 'email', 'support'])) {
    $answer = 'For questions, you can contact us at 09361679546 or wlaniba330@gmail.com.';
} else {
    $answer = 'I can answer questions about menu items and pricing, opening hours, location/address, contact details, and how pickup ordering works. What would you like to know?';
}

mysqli_close($conn);
echo json_encode(['answer' => $answer]);
