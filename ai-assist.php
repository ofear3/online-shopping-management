<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// 🔑 আপনার আসল OpenAI API Key এখানে বসান
define('OPENAI_API_KEY', 'sk-proj-YOUR_OPENAI_API_KEY_HERE');

$currentLang = $_SESSION['lang'] ?? 'en';
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = trim($_SESSION['user_name'] ?? '');
$isLoggedIn = ($userId > 0 && !empty($userName));
$greetingName = $isLoggedIn ? $userName : "Mohammad Saif";

// ----------------------------------------------------
// Context Builders
// ----------------------------------------------------
function getStoreEcosystemContext(mysqli $conn): string {
    $context = [];
    $res = $conn->query("SELECT p.id, p.name, p.price, p.discount_percent, p.stock, c.name as category FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 25");
    if ($res) {
        $prodList = [];
        while ($row = $res->fetch_assoc()) {
            $price = (float)$row['price'];
            $disc = (float)($row['discount_percent'] ?? 0);
            $finalPrice = $disc > 0 ? ($price - ($price * $disc / 100)) : $price;
            $offerText = $disc > 0 ? " [🔥 {$disc}% OFF]" : "";
            $prodList[] = "ID: {$row['id']} | {$row['name']} ({$row['category']}) | Price: ৳" . round($finalPrice, 2) . " | Stock: {$row['stock']} pcs{$offerText}";
        }
        $context[] = "AVAILABLE PRODUCTS:\n" . implode("\n", $prodList);
    }
    return implode("\n\n", $context);
}

function getSpecificOrderContext(mysqli $conn, string $message): string {
    if (preg_match('/(?:ord-?|order\s*#?\s*)(\d+)/i', $message, $matches) || preg_match('/\b(\d{1,6})\b/', $message, $matches)) {
        $orderIdNum = (int)end($matches);
        if ($orderIdNum > 0) {
            $stmt = $conn->prepare("SELECT id, status, total_amount, payment_method, shipping_address, phone, created_at FROM orders WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $orderIdNum);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $formattedId = '#ORD-' . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT);
                    $stmt->close();
                    return "DIRECT TARGET ORDER FOUND:\nOrder ID: {$formattedId} | Status: {$row['status']} | Amount: ৳{$row['total_amount']} | Payment: " . strtoupper($row['payment_method']) . " | Date: {$row['created_at']} | Phone: {$row['phone']}";
                }
                $stmt->close();
            }
        }
    }
    return "";
}

function getUserPersonalOrders(mysqli $conn, int $userId, array $sessionOrderIds): string {
    $orders = [];
    if ($userId > 0) {
        $res = $conn->query("SELECT id, status, total_amount, payment_method, created_at FROM orders WHERE user_id = {$userId} ORDER BY id DESC LIMIT 5");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $orders[] = "Order #ORD-" . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT) . " | Status: {$row['status']} | Amount: ৳{$row['total_amount']} | Date: {$row['created_at']}";
            }
        }
    } 
    if (empty($orders) && !empty($sessionOrderIds)) {
        $idsStr = implode(',', array_map('intval', array_slice($sessionOrderIds, -5)));
        $res = $conn->query("SELECT id, status, total_amount, payment_method, created_at FROM orders WHERE id IN ({$idsStr}) ORDER BY id DESC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $orders[] = "Order #ORD-" . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT) . " | Status: {$row['status']} | Amount: ৳{$row['total_amount']} | Date: {$row['created_at']}";
            }
        }
    }
    if (empty($orders)) {
        $res = $conn->query("SELECT id, status, total_amount, payment_method, created_at FROM orders ORDER BY id DESC LIMIT 5");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $orders[] = "Order #ORD-" . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT) . " | Status: {$row['status']} | Amount: ৳{$row['total_amount']} | Date: {$row['created_at']}";
            }
        }
    }
    return !empty($orders) ? "CUSTOMER ORDER HISTORY:\n" . implode("\n", $orders) : "CUSTOMER ORDER HISTORY: No orders found.";
}

// ----------------------------------------------------
// AJAX Backend Handler
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'ai_chat') {
        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            echo json_encode(['success' => false, 'reply' => 'Please ask something.']);
            exit;
        }

        $msgLower = strtolower($message);

        // যদি ইউজার ট্র্যাক বা অর্ডার স্ট্যাটাস জানতে চায়, সরাসরি my-orders.php এ রিডাইরেক্ট সিগন্যাল পাঠানো হবে
        if (strpos($msgLower, 'track') !== false || strpos($msgLower, 'status') !== false || strpos($msgLower, 'order') !== false || strpos($msgLower, 'history') !== false) {
            $redirectMsg = "Here are your recent orders and tracking details. Redirecting you to <a href='my-orders.php' class='text-emerald-600 font-bold underline'>My Orders</a> page...";
            echo json_encode([
                'success' => true,
                'reply' => $redirectMsg,
                'products' => [],
                'redirect' => 'my-orders.php'
            ]);
            exit;
        }

        $sessionOrderIds = $_SESSION['my_order_ids'] ?? [];
        $storeContext = getStoreEcosystemContext($conn);
        $specificOrderContext = getSpecificOrderContext($conn, $message);
        $orderContext = getUserPersonalOrders($conn, $userId, $sessionOrderIds);
        
        $currentTime = date('h:i A');
        $currentDay = date('l');

        $systemPrompt = "You are NOVERA Concierge, a professional AI Fashion Stylist and Sales Advisor.
Customer Name: {$greetingName}
Current Time & Day: {$currentTime}, {$currentDay}

{$specificOrderContext}

{$storeContext}
{$orderContext}

RULES:
1. Address the customer naturally as '{$greetingName}'.
2. NEVER repeat a generic welcome greeting if they are asking a specific product search or keyword. Give helpful style advice based on available products.
3. Keep responses concise, helpful, and polite. Match the language of the user (Bangla, English, or Banglish).";

        $messagesPayload = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => $messagesPayload,
            'temperature' => 0.7,
            'max_tokens' => 500
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $replyText = "";
        $productCards = [];

        if ($curlErr) {
            $replyText = "Hello {$greetingName}, here are the matching products from our collection:";
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['choices'][0]['message']['content'])) {
                $replyText = $responseData['choices'][0]['message']['content'];
            } else {
                $replyText = "Here are the best matching items from our collection for your search:";
            }
        }

        // Product Search & Keyword Matching
        $keywords = array_filter(explode(' ', $msgLower), function($w) { return strlen($w) > 2; });
        $searchClauses = [];
        $searchParams = [];
        $typesStr = '';

        foreach ($keywords as $kw) {
            $searchClauses[] = "(LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ? OR LOWER(c.name) LIKE ? OR LOWER(p.keywords) LIKE ?)";
            $val = '%' . $kw . '%';
            array_push($searchParams, $val, $val, $val, $val);
            $typesStr .= 'ssss';
        }

        $pRes = null;
        if (!empty($searchClauses)) {
            $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE " . implode(" OR ", $searchClauses) . " ORDER BY p.id DESC LIMIT 4";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($typesStr, ...$searchParams);
                $stmt->execute();
                $pRes = $stmt->get_result();
                $stmt->close();
            }
        }

        // যদি কিওয়ার্ড দিয়ে সরাসরি না মিলে, তবে যেকোনো পপুলার বা লেটেস্ট প্রোডাক্ট দেখাবে যাতে খালি মেসেজ না আসে
        if (!$pRes || $pRes->num_rows === 0) {
            $pRes = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 4");
        }

        if ($pRes && $pRes->num_rows > 0) {
            while ($p = $pRes->fetch_assoc()) {
                $price = (float)$p['price'];
                $disc = (float)($p['discount_percent'] ?? 0);
                $final = $disc > 0 ? ($price - ($price * $disc / 100)) : $price;
                $productCards[] = [
                    'id' => (int)$p['id'],
                    'name' => $p['name'],
                    'price' => round($final, 2),
                    'regular_price' => round($price, 2),
                    'discount' => $disc,
                    'category' => $p['category_name'] ?? 'Apparel',
                    'image' => !empty($p['image']) ? $p['image'] : ''
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'reply' => $replyText,
            'products' => $productCards
        ]);
        exit;
    }

    if ($action === 'add_to_cart_chat') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $size = trim($_POST['size'] ?? 'M');

        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product selection.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, name, price, discount_percent FROM products WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prod) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $cartKey = $productId . '_' . $size;
        $price = (float)$prod['price'];
        $disc = (float)($prod['discount_percent'] ?? 0);
        $final = $disc > 0 ? ($price - ($price * $disc / 100)) : $price;

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += 1;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'product_id' => $productId,
                'name' => $prod['name'],
                'price' => round($final, 2),
                'size' => $size,
                'quantity' => 1
            ];
        }

        echo json_encode([
            'success' => true,
            'message' => "Successfully added '{$prod['name']}' (Size: {$size}) to your cart!"
        ]);
        exit;
    }

    if ($action === 'place_chat_order') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $size      = trim($_POST['size'] ?? 'M');
        $phone     = trim($_POST['phone'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $payment   = trim($_POST['payment_method'] ?? 'cod');

        if ($productId <= 0 || empty($phone) || empty($address)) {
            echo json_encode(['success' => false, 'message' => 'Please provide phone number and delivery address.']);
            exit;
        }

        $pStmt = $conn->prepare("SELECT price, discount_percent FROM products WHERE id = ? LIMIT 1");
        $pStmt->bind_param("i", $productId);
        $pStmt->execute();
        $prod = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        if (!$prod) {
            echo json_encode(['success' => false, 'message' => 'Selected product not found in database.']);
            exit;
        }

        $price = (float)$prod['price'];
        $disc  = (float)($prod['discount_percent'] ?? 0);
        $finalPrice = $disc > 0 ? ($price - ($price * $disc / 100)) : $price;
        
        $shipping = 100.00;
        $grandTotal = $finalPrice + $shipping;

        try {
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, phone, payment_method, created_at) VALUES (?, ?, 'Pending', ?, ?, ?, NOW())");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
                exit;
            }

            $stmt->bind_param("idsss", $userId, $grandTotal, $address, $phone, $payment);
            
            if ($stmt->execute()) {
                $orderId = $stmt->insert_id;
                $stmt->close();

                if (!isset($_SESSION['my_order_ids'])) {
                    $_SESSION['my_order_ids'] = [];
                }
                $_SESSION['my_order_ids'][] = $orderId;

                $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, 1, ?)");
                if ($itemStmt) {
                    $itemStmt->bind_param("iid", $orderId, $productId, $finalPrice);
                    $itemStmt->execute();
                    $itemStmt->close();
                }

                $formattedOrderId = '#ORD-' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);
                $successMsg = "<span class='text-emerald-600 font-extrabold uppercase'>Congratulation</span> Mohammad Saif! Your order <strong class='text-slate-900'>{$formattedOrderId}</strong> has been successfully placed. Total Amount: ৳" . number_format($grandTotal, 0) . ". You can view or track your orders anytime in <a href='my-orders.php' class='text-emerald-700 font-bold underline'>My Orders</a>.";

                echo json_encode([
                    'success' => true,
                    'order_id' => $orderId,
                    'message' => $successMsg
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
                exit;
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="h-[88vh] py-4 sm:py-6 px-3 sm:px-6 lg:px-8 bg-slate-50/60 flex items-center justify-center overflow-hidden">
    <div class="max-w-4xl w-full bg-white rounded-3xl border border-slate-200/80 shadow-2xl overflow-hidden flex flex-col h-full">
        
        <!-- AI Header -->
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-emerald-950 p-4 sm:p-5 text-white flex items-center justify-between shrink-0 shadow-md">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-gradient-to-tr from-emerald-500 to-amber-400 p-0.5 flex items-center justify-center shadow-lg">
                        <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center text-emerald-400 font-black text-sm">
                            AI
                        </div>
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 border-2 border-slate-950 rounded-full animate-pulse"></span>
                </div>
                <div>
                    <h1 class="text-sm sm:text-base font-black tracking-tight flex items-center gap-2">
                        NOVERA Concierge
                        <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-300 font-bold text-[9px] rounded-full uppercase border border-emerald-400/30">Live AI Assistant</span>
                    </h1>
                    <p class="text-[11px] text-slate-300">Your Personal Fashion Stylist & Sales Advisor</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="cart.php" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition flex items-center gap-1.5">
                    <span>🛒 Cart</span>
                </a>
                <a href="my-orders.php" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                    <span>📦 My Orders</span>
                </a>
            </div>
        </div>

        <!-- Chat Messages Container -->
        <div id="chatStream" class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-4 bg-slate-50/50 text-xs">
            
            <!-- Welcome Message -->
            <div class="flex items-start gap-3 max-w-2xl">
                <div class="w-8 h-8 rounded-xl bg-slate-950 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-md">
                    AI
                </div>
                <div class="bg-white p-4 rounded-2xl rounded-tl-none border border-slate-200/80 shadow-xs space-y-2 text-slate-800 leading-relaxed">
                    <p class="font-bold text-slate-900">
                        Hello Mohammad Saif! Welcome to NOVERA.
                    </p>
                    <p>
                        How can I style you today? Ask me for clothing recommendations, check ongoing sales, track your recent orders, or place a direct order right here through our chat.
                    </p>
                </div>
            </div>

        </div>

        <!-- Suggestion Chips Bar -->
        <div class="px-4 py-2 bg-white border-t border-slate-100 flex items-center gap-2 overflow-x-auto whitespace-nowrap scrollbar-none shrink-0 text-[11px]">
            <button onclick="sendQuickMsg('Show me ongoing sales and clearance offers')" class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                🔥 Clearance Sales
            </button>
            <button onclick="sendQuickMsg('I need a wedding outfit recommendation')" class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                ✨ Wedding Outfits
            </button>
            <button onclick="sendQuickMsg('Show me premium Panjabi collection')" class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                👔 Panjabi Collection
            </button>
            <button onclick="sendQuickMsg('Track my order status')" class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                📦 Track My Orders
            </button>
        </div>

        <!-- Chat Input Bar -->
        <form id="chatForm" onsubmit="handleUserSubmit(event)" class="p-3 sm:p-4 bg-white border-t border-slate-200/80 flex items-center gap-2 shrink-0">
            <input type="text" id="userInput" placeholder="Ask AI about fashion, products, tracking, or orders..." 
                   class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
            
            <button type="submit" class="py-3 px-5 bg-slate-950 hover:bg-emerald-600 active:scale-95 text-white font-black rounded-2xl shadow-lg transition flex items-center gap-1 cursor-pointer shrink-0">
                <span>Send</span>
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </form>

    </div>
</div>

<!-- PRODUCT DETAILS MODAL -->
<div id="productDetailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4 hidden">
    <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl p-6 space-y-4 border border-slate-100 relative animate-in fade-in zoom-in duration-200">
        <button onclick="closeProductModal()" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold flex items-center justify-center cursor-pointer">✕</button>
        <div id="modalProductContent" class="space-y-4"></div>
    </div>
</div>

<!-- IN-CHAT DIRECT CHECKOUT MODAL -->
<div id="chatCheckoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4 hidden">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-6 space-y-5 border border-slate-100 animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                In-Chat Instant Checkout
            </h3>
            <button onclick="closeCheckoutModal()" class="text-slate-400 hover:text-slate-700 font-bold text-base cursor-pointer">✕</button>
        </div>

        <div id="modalCheckoutSummary" class="p-3 bg-slate-50 rounded-2xl border border-slate-100 flex items-center gap-3"></div>

        <form id="directOrderForm" onsubmit="submitDirectOrder(event)" class="space-y-4 text-xs">
            <input type="hidden" id="checkoutProductId" name="product_id">

            <div class="space-y-1">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Select Size</label>
                <select id="checkoutSize" name="size" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 focus:outline-none">
                    <option value="S">Small (S)</option>
                    <option value="M" selected>Medium (M)</option>
                    <option value="L">Large (L)</option>
                    <option value="XL">Extra Large (XL)</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Phone Number *</label>
                <input type="tel" id="checkoutPhone" name="phone" required placeholder="01700000000"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20">
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Delivery Address *</label>
                <textarea id="checkoutAddress" name="address" rows="2" required placeholder="House, Road, Area, District..."
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20"></textarea>
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Payment Method</label>
                <select name="payment_method" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 focus:outline-none">
                    <option value="cod">Cash on Delivery (COD)</option>
                    <option value="bkash">bKash Mobile Payment</option>
                    <option value="rocket">Rocket Mobile Payment</option>
                </select>
            </div>

            <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg transition text-xs cursor-pointer">
                Confirm & Place Order Now
            </button>
        </form>

    </div>
</div>

<script>
    let activeProductsCatalog = [];

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function sendQuickMsg(txt) {
        document.getElementById('userInput').value = txt;
        handleUserSubmit(new Event('submit'));
    }

    function handleUserSubmit(e) {
        e.preventDefault();
        const input = document.getElementById('userInput');
        const msg = input.value.trim();
        if (!msg) return;

        appendUserBubble(msg);
        input.value = '';

        const typingId = appendTypingIndicator();

        const formData = new FormData();
        formData.append('action', 'ai_chat');
        formData.append('message', msg);

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            removeTypingIndicator(typingId);
            if (data.success) {
                appendAIBubble(data.reply, data.products || [], true);
                
                // যদি ইউজার অর্ডার ট্র্যাক করতে চায়, ১.৫ সেকেন্ড পর স্বয়ংক্রিয়ভাবে my-orders.php এ রিডাইরেক্ট হবে
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                }
            } else {
                appendAIBubble('Apologies, I encountered an issue. Please try again.');
            }
        })
        .catch(err => {
            removeTypingIndicator(typingId);
            appendAIBubble('Apologies, connection failed. Please check network.');
        });
    }

    function appendUserBubble(msg) {
        const stream = document.getElementById('chatStream');
        const html = `
            <div class="flex items-start justify-end gap-3">
                <div class="bg-slate-950 text-white p-3.5 rounded-2xl rounded-tr-none shadow-sm max-w-md font-medium">
                    ${escapeHtml(msg)}
                </div>
            </div>
        `;
        stream.insertAdjacentHTML('beforeend', html);
        stream.scrollTop = stream.scrollHeight;
    }

    function appendTypingIndicator() {
        const stream = document.getElementById('chatStream');
        const id = 'typing_' + Date.now();
        const html = `
            <div id="${id}" class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-slate-950 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-md">AI</div>
                <div class="bg-white px-4 py-3 rounded-2xl border border-slate-200 flex items-center gap-1.5 shadow-xs">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-bounce"></span>
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-bounce [animation-delay:0.4s]"></span>
                </div>
            </div>
        `;
        stream.insertAdjacentHTML('beforeend', html);
        stream.scrollTop = stream.scrollHeight;
        return id;
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function appendAIBubble(replyText, products = [], isHtml = false) {
        const stream = document.getElementById('chatStream');
        let productsHTML = '';

        if (products.length > 0) {
            products.forEach(p => {
                activeProductsCatalog[p.id] = p;
                const cleanedImg = p.image ? p.image.replace(/^(\.\.\/)+/, '').replace(/^online-shopping-management\//, '') : '';
                const imgPath = cleanedImg ? '/online-shopping-management/' + cleanedImg : '';
                
                productsHTML += `
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 flex flex-col justify-between space-y-2">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-16 bg-slate-200 rounded-xl overflow-hidden shrink-0 cursor-pointer" onclick="viewProductDetails(${p.id})">
                                ${imgPath ? `<img src="${imgPath}" class="w-full h-full object-cover">` : '<div class="w-full h-full flex items-center justify-center font-bold text-[8px]">NOVERA</div>'}
                            </div>
                            <div class="flex-1">
                                <span class="text-[9px] font-extrabold text-emerald-600 uppercase tracking-widest">${escapeHtml(p.category)}</span>
                                <h4 class="font-bold text-slate-900 line-clamp-1 text-xs cursor-pointer hover:text-emerald-600" onclick="viewProductDetails(${p.id})">${escapeHtml(p.name)}</h4>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-emerald-700 font-black text-xs">৳${Number(p.price).toLocaleString()}</span>
                                    ${p.discount > 0 ? `<span class="text-[10px] text-slate-400 line-through">৳${Number(p.regular_price).toLocaleString()}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-1.5 pt-1">
                            <button onclick="viewProductDetails(${p.id})" class="py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-extrabold text-[10px] rounded-xl transition text-center cursor-pointer">
                                Quick View
                            </button>
                            <button onclick="openChatCheckout(${p.id})" class="py-2 bg-slate-950 hover:bg-emerald-600 text-white font-extrabold text-[10px] rounded-xl transition text-center cursor-pointer">
                                Buy Now
                            </button>
                        </div>
                    </div>
                `;
            });
            productsHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3">${productsHTML}</div>`;
        }

        const formattedReply = isHtml ? replyText : (escapeHtml(replyText).replace(/\n/g, '<br>'));

        const html = `
            <div class="flex items-start gap-3 max-w-2xl w-full">
                <div class="w-8 h-8 rounded-xl bg-slate-950 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-md">AI</div>
                <div class="bg-white p-4 rounded-2xl rounded-tl-none border border-slate-200/80 shadow-xs space-y-2 text-slate-800 leading-relaxed w-full">
                    <p class="font-medium">${formattedReply}</p>
                    ${productsHTML}
                </div>
            </div>
        `;

        stream.insertAdjacentHTML('beforeend', html);
        stream.scrollTop = stream.scrollHeight;
    }

    function viewProductDetails(id) {
        const p = activeProductsCatalog[id];
        if (!p) return;

        const cleanedImg = p.image ? p.image.replace(/^(\.\.\/)+/, '').replace(/^online-shopping-management\//, '') : '';
        const imgPath = cleanedImg ? '/online-shopping-management/' + cleanedImg : '';

        document.getElementById('modalProductContent').innerHTML = `
            <div class="w-full h-64 bg-slate-100 rounded-2xl overflow-hidden shadow-inner">
                ${imgPath ? `<img src="${imgPath}" class="w-full h-full object-cover">` : '<div class="w-full h-full flex items-center justify-center font-bold">NOVERA</div>'}
            </div>
            <div class="space-y-1.5">
                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 font-extrabold text-[10px] rounded-md uppercase">${escapeHtml(p.category)}</span>
                <h3 class="text-base font-black text-slate-900">${escapeHtml(p.name)}</h3>
                <div class="flex items-center gap-3">
                    <span class="text-lg font-black text-emerald-600">৳${Number(p.price).toLocaleString()}</span>
                    ${p.discount > 0 ? `<span class="text-xs text-slate-400 line-through">৳${Number(p.regular_price).toLocaleString()}</span>` : ''}
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button onclick="addToCartFromModal(${p.id})" class="flex-1 py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition cursor-pointer">
                    Add to Cart
                </button>
                <button onclick="closeProductModal(); openChatCheckout(${p.id});" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition cursor-pointer">
                    Instant Checkout
                </button>
            </div>
        `;
        document.getElementById('productDetailModal').classList.remove('hidden');
    }

    function closeProductModal() {
        document.getElementById('productDetailModal').classList.add('hidden');
    }

    function openChatCheckout(id) {
        const p = activeProductsCatalog[id];
        if (!p) return;

        document.getElementById('checkoutProductId').value = p.id;
        document.getElementById('modalCheckoutSummary').innerHTML = `
            <div class="font-bold text-slate-800 text-xs">${escapeHtml(p.name)}</div>
            <div class="text-emerald-600 font-black text-xs">৳${Number(p.price).toLocaleString()}</div>
        `;
        document.getElementById('chatCheckoutModal').classList.remove('hidden');
    }

    function closeCheckoutModal() {
        document.getElementById('chatCheckoutModal').classList.add('hidden');
    }

    function addToCartFromModal(productId) {
        const formData = new FormData();
        formData.append('action', 'add_to_cart_chat');
        formData.append('product_id', productId);
        formData.append('size', 'M');

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            appendAIBubble(data.message);
            closeProductModal();
        });
    }

    function submitDirectOrder(e) {
        e.preventDefault();
        const form = document.getElementById('directOrderForm');
        const formData = new FormData(form);
        formData.append('action', 'place_chat_order');

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeCheckoutModal();
                appendAIBubble(data.message, [], true);
            } else {
                appendAIBubble(data.message);
            }
        });
    }
</script>