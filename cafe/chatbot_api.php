<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['answer' => 'Please send a POST request.']); exit;
}

require_once '../db.php';

/* ── Helpers ────────────────────────────────── */
function norm(string $t): string {
    return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^a-z0-9\s]+/u', ' ', strtolower($t))));
}
function has(string $t, array $words): bool {
    foreach ($words as $w) if (strpos($t, $w) !== false) return true;
    return false;
}
function fmt_price(float $p): string { return '₱' . number_format($p, 2); }
function digits(string $t): string  { return preg_replace('/\D+/u', '', $t); }

function extract_phone(string $t): ?string {
    preg_match_all('/\d{7,15}/u', $t, $m);
    $best = null;
    foreach ($m[0] as $c) { $c = digits($c); if ($best === null || strlen($c) > strlen($best)) $best = $c; }
    return ($best !== null && strlen($best) >= 10) ? $best : null;
}
function extract_email(string $t): ?string {
    return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $t, $m) ? trim($m[0]) : null;
}
function extract_order_id(string $t): ?int {
    if (preg_match('/order\s*(?:#|number)?\s*(\d{1,10})/iu', $t, $m)) return (int)$m[1];
    if (preg_match('/#\s*(\d{1,10})/u', $t, $m)) return (int)$m[1];
    return null;
}
function friendly_status(string $s): string {
    return match(strtolower(trim($s))) {
        'pending'   => '⏳ Pending — we just received it!',
        'preparing' => '👨‍🍳 Being prepared right now',
        'ready'     => '✅ Ready for pickup!',
        'completed' => '✔️ Completed',
        'cancelled' => '❌ Cancelled',
        default     => $s,
    };
}

/* ── Load menu from DB ──────────────────────── */
function get_menu($conn): array {
    $items = []; $cats = [];
    $r = mysqli_query($conn, "SELECT name, category, description, price FROM products WHERE is_available=1 ORDER BY category ASC, name ASC");
    if ($r) while ($row = mysqli_fetch_assoc($r)) { $row['price'] = (float)$row['price']; $items[] = $row; $cats[$row['category']] = true; }
    return ['items' => $items, 'categories' => array_keys($cats)];
}

function order_lookup($conn, int $id, ?string $phone, ?string $email): array {
    $st = mysqli_prepare($conn, 'SELECT id,status,created_at,total_amount,customer_phone,customer_email FROM orders WHERE id=? LIMIT 1');
    mysqli_stmt_bind_param($st, 'i', $id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$row) return ['ok' => false, 'reason' => 'not_found'];

    $sp = digits((string)($row['customer_phone'] ?? ''));
    $se = strtolower(trim((string)($row['customer_email'] ?? '')));
    if ($phone !== null) { if ($sp !== digits($phone)) return ['ok' => false, 'reason' => 'mismatch']; }
    elseif ($email !== null) { if ($se !== strtolower(trim($email))) return ['ok' => false, 'reason' => 'mismatch']; }
    else return ['ok' => false, 'reason' => 'missing_verification'];

    $ist = mysqli_prepare($conn, 'SELECT quantity, product_name FROM order_items WHERE order_id=? ORDER BY id ASC');
    mysqli_stmt_bind_param($ist, 'i', $id);
    mysqli_stmt_execute($ist);
    $ir = mysqli_stmt_get_result($ist);
    $items = [];
    if ($ir) while ($it = mysqli_fetch_assoc($ir)) if ($it['quantity'] > 0 && $it['product_name'] !== '') $items[] = $it['quantity'] . 'x ' . $it['product_name'];
    mysqli_stmt_close($ist);

    return [
        'ok' => true,
        'status' => (string)($row['status'] ?? ''),
        'created_at' => $row['created_at'] ? date('M d, Y g:i A', strtotime((string)$row['created_at'])) : '—',
        'total' => number_format((float)($row['total_amount'] ?? 0), 2),
        'items' => array_slice($items, 0, 5),
    ];
}

/* ── Parse input ────────────────────────────── */
$input   = json_decode(file_get_contents('php://input'), true);
$raw     = trim((string)($input['message'] ?? ''));
$msg     = norm($raw);
if ($msg === '') { echo json_encode(['answer' => 'Hi! Ask me about our menu, hours, location, or your order. ☕']); exit; }

$menu      = get_menu($conn);
$products  = $menu['items'];
$cats      = $menu['categories'];
$answer    = '';

/* ── Intent detection ───────────────────────── */
$isGreet    = has($msg, ['hello','hi ','hey','good morning','good afternoon','good evening','howdy','yo ','sup ']);
$isHours    = has($msg, ['hour','open','close','schedule','time','when','till','until','what time']);
$isLocation = has($msg, ['where','location','address','find you','directions','how to get','map','cuasi','loon','bohol']);
$isContact  = has($msg, ['phone','call','contact','email','number','reach']);
$isMenu     = has($msg, ['menu','what do you have','what do you sell','what you got','categories','list','items','products','food','drinks','eat','drink']);
$isRecommend= has($msg, ['recommend','best','popular','favorite','top','signature','good','suggest','try','what should']);
$isCoffee   = has($msg, ['coffee','espresso','latte','cappuccino','americano','macchiato','mocha','cold brew','flat white']);
$isCold     = has($msg, ['cold','iced','smoothie','juice','milkshake','frappe']);
$isPastry   = has($msg, ['pastry','pastries','croissant','cake','muffin','cookie','baked','bread','dessert','sweet']);
$isFood     = has($msg, ['food','sandwich','meal','snack','lunch','breakfast','eat']);
$isPrice    = has($msg, ['price','cost','how much','how many','pricing','cheap','expensive','afford']);
$isOrder    = has($msg, ['order','pickup','place','buy','purchase','cart','checkout']);
$isTrack    = (has($msg, ['track','status','where is','ready','preparing','pending','check my']) && (has($msg, ['order','pickup']) || extract_order_id($raw) !== null));
$isAllergy  = has($msg, ['allergy','allergen','vegan','vegetarian','gluten','dairy','nut','halal','pork']);
$isWifi     = has($msg, ['wifi','wi-fi','internet','password','connect']);
$isParking  = has($msg, ['park','parking','where to park']);
$isPayment  = has($msg, ['pay','cash','card','gcash','maya','payment','credit','debit']);
$isThanks   = has($msg, ['thank','thanks','ty ','cheers','appreciate']);
$isBye      = has($msg, ['bye','goodbye','see you','ciao','later']);

/* ── Try to match a specific product name ───── */
$matchedProduct = null;
foreach ($products as $p) {
    if (strpos($msg, norm((string)$p['name'])) !== false) { $matchedProduct = $p; break; }
}

/* ── Build answer ───────────────────────────── */
if ($isBye) {
    $answer = "Goodbye! Hope to see you at Cozy Corner Café soon. ☕\nHave a wonderful day! 😊";

} elseif ($isThanks) {
    $answer = "You're welcome! 😊 Is there anything else I can help you with?";

} elseif ($isGreet) {
    $hour = (int)date('H');
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $answer = "$greet! Welcome to Cozy Corner Café! ☕\n\nI can help you with:\n• 📋 Menu & prices\n• 🕐 Opening hours\n• 📍 Location & directions\n• ☕ Coffee recommendations\n• 📦 Order tracking\n\nWhat can I get for you today?";

} elseif ($isTrack) {
    $orderId = extract_order_id($raw);
    $phone   = extract_phone($raw);
    $email   = extract_email($raw);
    if ($orderId === null) {
        $answer = "To check your order status, please share:\n• Your order reference number (e.g. #42)\n• The phone number you used when ordering\n\nExample: \"Track order #42 09361679546\"";
    } else {
        $lookup = order_lookup($conn, $orderId, $phone, $email);
        if ($lookup['ok']) {
            $statusLine = friendly_status($lookup['status']);
            $itemLines  = !empty($lookup['items']) ? "\n• " . implode("\n• ", $lookup['items']) : '';
            $answer = "📦 Order #$orderId\n\nStatus: {$statusLine}\n📅 Placed: {$lookup['created_at']}\n💰 Total: ₱{$lookup['total']}\n\nItems:{$itemLines}";
        } elseif ($lookup['reason'] === 'not_found') {
            $answer = "❌ I couldn't find order #$orderId. Please double-check the number.\n\nIf you need help, call us at 📞 09361679546.";
        } elseif ($lookup['reason'] === 'mismatch') {
            $answer = "❌ The phone/email doesn't match our records for order #$orderId.\n\nPlease try the same phone number you used when placing the order.";
        } else {
            $answer = "To verify order #$orderId, please also share the phone number you used when ordering.";
        }
    }

} elseif ($isHours) {
    $answer = "🕐 Opening Hours:\n\n• Mon – Fri: 7:00 AM – 8:00 PM\n• Saturday: 8:00 AM – 9:00 PM\n• Sunday: 8:00 AM – 9:00 PM\n\nWe're open every day! See you soon ☕";

} elseif ($isLocation) {
    $answer = "📍 Find us at:\n\nCuasi, Loon, Bohol\n\nWe're in the heart of Loon — look for the cozy café with the coffee aroma! ☕\n\nFor directions, call us at 📞 09361679546.";

} elseif ($isContact) {
    $answer = "📞 Contact us:\n\n• Phone: 09361679546\n• Email: wlaniba330@gmail.com\n\nWe're happy to answer any questions!";

} elseif ($isAllergy) {
    $answer = "🌿 For allergy, dietary, or ingredient questions, it's best to call us directly so we can confirm before you order:\n\n📞 09361679546\n✉️ wlaniba330@gmail.com\n\nYour safety matters to us!";

} elseif ($isWifi) {
    $answer = "📶 Yes, we have free Wi-Fi! Ask our staff for the password when you arrive. We love remote workers and students! 💻";

} elseif ($isParking) {
    $answer = "🅿️ There is parking available near the café. For specific details, give us a call at 📞 09361679546.";

} elseif ($isPayment) {
    $answer = "💳 Payment methods:\n\n• Cash\n• GCash\n• PayMaya\n\nWe accept both digital and cash payments for your convenience!";

} elseif ($isOrder) {
    $answer = "🛒 How to order for pickup:\n\n1. Scroll to the **Order for Pickup** section (or tap \"Order Now\" above)\n2. Browse and add items to your cart\n3. Enter your Full Name & Phone number\n4. Tap **Place My Order**\n\nYou'll receive an order number to track your pickup! 📦";

} elseif ($matchedProduct !== null && $isPrice) {
    $p = $matchedProduct;
    $desc = trim((string)$p['description']);
    $answer = "☕ {$p['name']}\n\nPrice: " . fmt_price($p['price']) . "\nCategory: {$p['category']}" . ($desc ? "\n\n$desc" : "") . "\n\nWant to add it to your order? Use the Order for Pickup section! 🛒";

} elseif ($matchedProduct !== null) {
    $p = $matchedProduct;
    $desc = trim((string)$p['description']);
    $answer = "☕ {$p['name']}\n\nPrice: " . fmt_price($p['price']) . "\nCategory: {$p['category']}" . ($desc ? "\n\n$desc" : "") . "\n\nWant to try it? Tap **Order for Pickup** to add it to your cart! 🛒";

} elseif (has($msg, ['show me the menu']) || ($isMenu && !$isCoffee && !$isCold && !$isPastry && !$isFood)) {
    if (!empty($cats)) {
        $catLines = implode(', ', $cats);
        // Show a few items from each category
        $lines = ["📋 Our Menu Categories:\n• $catLines\n"];
        $shown = [];
        foreach ($cats as $cat) {
            $catItems = array_filter($products, fn($p) => $p['category'] === $cat);
            $catItems  = array_slice(array_values($catItems), 0, 3);
            if (empty($catItems)) continue;
            $lines[] = "**{$cat}**";
            foreach ($catItems as $p) {
                $lines[] = "• {$p['name']} — " . fmt_price($p['price']);
            }
            $lines[] = '';
        }
        $lines[] = "Ask me about any specific item for details, or tap **Order for Pickup** to place your order! 🛒";
        $answer = implode("\n", $lines);
    } else {
        $answer = "Our menu is being updated. Please check back soon, or call us at 📞 09361679546!";
    }

} elseif ($isCoffee) {
    $coffeeItems = array_filter($products, fn($p) => stripos($p['category'], 'coffee') !== false || stripos($p['name'], 'espresso') !== false || stripos($p['name'], 'latte') !== false || stripos($p['name'], 'cappuccino') !== false || stripos($p['name'], 'americano') !== false);
    $coffeeItems = array_values($coffeeItems);
    if (!empty($coffeeItems)) {
        $lines = ["☕ Our Coffee Selection:\n"];
        foreach (array_slice($coffeeItems, 0, 6) as $p) {
            $lines[] = "• {$p['name']} — " . fmt_price($p['price']);
        }
        $lines[] = "\nAll crafted with premium beans! Want to order? Use the Order for Pickup section. 🛒";
        $answer = implode("\n", $lines);
    } else {
        $answer = "We have a wonderful coffee selection including espresso, cappuccino, lattes, and more! Visit the menu section to see all options. ☕";
    }

} elseif ($isCold) {
    $coldItems = array_filter($products, fn($p) => stripos($p['category'], 'cold') !== false || stripos($p['name'], 'iced') !== false || stripos($p['name'], 'cold') !== false || stripos($p['name'], 'smoothie') !== false);
    $coldItems = array_values($coldItems);
    if (!empty($coldItems)) {
        $lines = ["🧊 Cool Drinks:\n"];
        foreach (array_slice($coldItems, 0, 6) as $p) {
            $lines[] = "• {$p['name']} — " . fmt_price($p['price']);
        }
        $lines[] = "\nRefreshing and ice-cold! Order via the Order for Pickup section. 🛒";
        $answer = implode("\n", $lines);
    } else {
        $answer = "We have refreshing cold drinks! Check our menu section for the full cold drinks list. 🧊";
    }

} elseif ($isPastry) {
    $pastryItems = array_filter($products, fn($p) => stripos($p['category'], 'pastr') !== false || stripos($p['name'], 'cake') !== false || stripos($p['name'], 'muffin') !== false || stripos($p['name'], 'cookie') !== false || stripos($p['name'], 'croissant') !== false);
    $pastryItems = array_values($pastryItems);
    if (!empty($pastryItems)) {
        $lines = ["�� Baked Goods & Pastries:\n"];
        foreach (array_slice($pastryItems, 0, 6) as $p) {
            $lines[] = "• {$p['name']} — " . fmt_price($p['price']);
        }
        $lines[] = "\nFreshly baked daily! 🥐 Order via the Order for Pickup section. 🛒";
        $answer = implode("\n", $lines);
    } else {
        $answer = "We bake fresh pastries daily! Check our menu for the full selection. 🥐";
    }

} elseif ($isFood) {
    $foodItems = array_filter($products, fn($p) => stripos($p['category'], 'food') !== false || stripos($p['name'], 'sandwich') !== false || stripos($p['name'], 'toast') !== false);
    $foodItems = array_values($foodItems);
    if (!empty($foodItems)) {
        $lines = ["🥪 Food Menu:\n"];
        foreach (array_slice($foodItems, 0, 6) as $p) {
            $lines[] = "• {$p['name']} — " . fmt_price($p['price']);
        }
        $lines[] = "\nPerfect with your coffee! Order via the Order for Pickup section. 🛒";
        $answer = implode("\n", $lines);
    } else {
        $answer = "We have light meals and snacks! Check our menu section for the full food list. 🥪";
    }

} elseif ($isRecommend) {
    $top = array_slice($products, 0, 5);
    if (!empty($top)) {
        $lines = ["⭐ Our Top Picks:\n"];
        foreach ($top as $p) {
            $lines[] = "• {$p['name']} — " . fmt_price($p['price']);
        }
        $lines[] = "\nYou can also tell me what you're in the mood for:\n• ☕ Coffee  • 🧊 Cold drinks  • 🥐 Pastries  • 🥪 Food";
        $answer = implode("\n", $lines);
    } else {
        $answer = "Try our Signature Cappuccino, Cinnamon Roll, or Iced Matcha Latte — customer favorites! ⭐";
    }

} elseif (has($msg, ['recommend a coffee','coffee recommendation','best coffee'])) {
    $coffees = array_filter($products, fn($p) => stripos($p['category'], 'coffee') !== false);
    $coffees = array_values($coffees);
    if (!empty($coffees)) {
        $pick = $coffees[array_rand($coffees)];
        $desc = trim((string)$pick['description']);
        $answer = "☕ I recommend our **{$pick['name']}** — " . fmt_price($pick['price']) . ($desc ? "\n\n$desc" : '') . "\n\nTo order, use the Order for Pickup section! 🛒";
    } else {
        $answer = "I'd recommend our Signature Cappuccino — a customer favorite! ☕ Check the menu for more options.";
    }

} elseif ($isPrice) {
    // General pricing question
    if (!empty($products)) {
        $cheapest = min(array_column($products, 'price'));
        $priciest  = max(array_column($products, 'price'));
        $answer = "💰 Our menu prices range from " . fmt_price($cheapest) . " to " . fmt_price($priciest) . ".\n\nAsk me about a specific item and I'll tell you its exact price! Just type the item name (e.g. \"How much is the Cappuccino?\")";
    } else {
        $answer = "Our prices are very reasonable! Check the menu section for full pricing, or ask me about a specific item.";
    }

} else {
    $answer = "I'm here to help! You can ask me about:\n\n• 📋 Menu items & prices\n• ☕ Coffee recommendations\n• 🕐 Opening hours\n• 📍 Our location\n• 📞 Contact details\n• 📦 Tracking your order\n• 💳 Payment methods\n\nWhat would you like to know?";
}

mysqli_close($conn);
echo json_encode(['answer' => $answer]);
