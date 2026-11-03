<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// 🔑 আপনার আসল OpenAI API Key এখানে বসান
define('OPENAI_API_KEY', 'sk-proj-YOUR_OPENAI_API_KEY_HERE');

$currentLang = $_SESSION['lang'] ?? 'en';
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Valued Guest';

// ----------------------------------------------------
// Helper Functions for OpenAI Context
// ----------------------------------------------------

// ১. ডাটাবেজ থেকে সমস্ত প্রোডাক্ট ফেচ করে প্রম্পটে দেওয়া
function getShopProductsContext(mysqli $conn): string {
    $products = [];
    $res = $conn->query("SELECT p.id, p.name, p.price, p.discount_percent, p.description, c.name as category FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 20");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $price = (float)$row['price'];
            $disc = (float)($row['discount_percent'] ?? 0);
            $finalPrice = $disc > 0 ? ($price - ($price * ($disc / 100))) : $price;
            $products[] = "ID: {$row['id']} | Name: {$row['name']} | Category: {$row['category']} | Price: ৳{$finalPrice} | Details: {$row['description']}";
        }
    }
    return !empty($products) ? implode("\n", $products) : "No products available at the moment.";
}

// ২. ইউজারের সাম্প্রতিক অর্ডারের স্ট্যাটাস জানা
function getUserOrdersContext(mysqli $conn, int $userId, array $sessionOrderIds): string {
    $orders = [];
    if ($userId > 0) {
        $res = $conn->query("SELECT id, status, total_amount, created_at FROM orders WHERE user_id = {$userId} ORDER BY id DESC LIMIT 3");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $orders[] = "Order #ORD-" . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT) . " | Status: {$row['status']} | Total: ৳{$row['total_amount']} | Date: {$row['created_at']}";
            }
        }
    } elseif (!empty($sessionOrderIds)) {
        $idsStr = implode(',', array_map('intval', array_slice($sessionOrderIds, -3)));
        $res = $conn->query("SELECT id, status, total_amount, created_at FROM orders WHERE id IN ({$idsStr}) ORDER BY id DESC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $orders[] = "Order #ORD-" . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT) . " | Status: {$row['status']} | Total: ৳{$row['total_amount']} | Date: {$row['created_at']}";
            }
        }
    }
    return !empty($orders) ? implode("\n", $orders) : "No recent orders found for this user.";
}

// ----------------------------------------------------
// AJAX Backend Handler
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // Action A: Real OpenAI Chat Response
    if ($action === 'ai_chat') {
        $message = trim($_POST['message'] ?? '');
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'reply' => 'Please ask something.']);
            exit;
        }

        $sessionOrderIds = $_SESSION['my_order_ids'] ?? [];
        $productCatalog = getShopProductsContext($conn);
        $userOrders = getUserOrdersContext($conn, $userId, $sessionOrderIds);

        // System Prompt Building
        $systemPrompt = "You are NOVERA Concierge, a polite, warm, and professional AI Fashion Stylist and Sales Advisor for the luxury e-commerce brand NOVERA.
Customer Name: {$userName}
Customer Login Status: " . ($userId > 0 ? "Logged In" : "Guest") . "

Here is the LIVE PRODUCT CATALOG currently available in NOVERA store:
{$productCatalog}

Here is the Customer's Recent Order History:
{$userOrders}

CRITICAL GUIDELINES:
1. Always address the customer naturally and warmly.
2. If the user asks for outfit recommendations for any occasion (like wedding, party, office, casual, Eid, Panjabi, Saree, etc.), act like an expert human stylist. Recommend relevant clothing from the catalog provided above based on keywords.
3. If the user asks about their order status, check their order history context provided above and answer accurately.
4. Always respond in the same language as the user (Bangla, English, or Banglish).
5. Keep your tone luxurious, supportive, and natural. Do not use excessive symbols or emojis.";

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
            'max_tokens' => 600
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $type = "text";
        $productCards = [];
        $replyText = "";

        if ($curlErr) {
            $replyText = "Apologies, I am having trouble connecting to OpenAI. Please check your API configuration.";
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['choices'][0]['message']['content'])) {
                $replyText = $responseData['choices'][0]['message']['content'];

                // Keyword Based Intelligent Product Filtering
                $msgLower = strtolower($message);
                $keywords = explode(' ', $msgLower);
                
                $searchClauses = [];
                $searchParams = [];
                $typesStr = '';

                foreach ($keywords as $kw) {
                    if (strlen($kw) > 2) {
                        $searchClauses[] = "(LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ? OR LOWER(c.name) LIKE ?)";
                        $paramVal = '%' . $kw . '%';
                        $searchParams[] = $paramVal;
                        $searchParams[] = $paramVal;
                        $searchParams[] = $paramVal;
                        $typesStr .= 'sss';
                    }
                }

                if (!empty($searchClauses)) {
                    $sqlQuery = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE " . implode(" OR ", $searchClauses) . " ORDER BY p.id DESC LIMIT 4";
                    $stmtSearch = $conn->prepare($sqlQuery);
                    if ($stmtSearch) {
                        $stmtSearch->bind_param($typesStr, ...$searchParams);
                        $stmtSearch->execute();
                        $pRes = $stmtSearch->get_result();
                        while ($p = $pRes->fetch_assoc()) {
                            $price = (float)$p['price'];
                            $disc = (float)($p['discount_percent'] ?? 0);
                            $final = $disc > 0 ? ($price - ($price * ($disc / 100))) : $price;
                            $productCards[] = [
                                'id' => (int)$p['id'],
                                'name' => $p['name'],
                                'price' => round($final, 2),
                                'image' => !empty($p['image']) ? $p['image'] : ''
                            ];
                        }
                        $stmtSearch->close();
                    }
                }

                // যদি কোনো কীওয়ার্ড ডিরেক্ট না মেলে বা সাধারণ প্রশ্ন/হাই গ্রিটিং হয়, তবুও প্রডাক্ট কার্ড শোর জন্য ডিফেন্স লাইন
                if (!empty($productCards)) {
                    $type = "products";
                } else {
                    // সাধারণ প্রশ্নের ক্ষেত্রেও ইউজারের সুবিধার জন্য টপ ৪টি ফিচারড প্রডাক্ট রিটার্ন করবে
                    $type = "products";
                    $pRes = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 4");
                    if ($pRes) {
                        while ($p = $pRes->fetch_assoc()) {
                            $price = (float)$p['price'];
                            $disc = (float)($p['discount_percent'] ?? 0);
                            $final = $disc > 0 ? ($price - ($price * ($disc / 100))) : $price;
                            $productCards[] = [
                                'id' => (int)$p['id'],
                                'name' => $p['name'],
                                'price' => round($final, 2),
                                'image' => !empty($p['image']) ? $p['image'] : ''
                            ];
                        }
                    }
                }

            } else {
                $replyText = "Greetings " . htmlspecialchars($userName) . "! How may I assist you with our luxury collection today?";
                
                // এপিআই কী কোনো কারণে কাজ না করলেও প্রডাক্ট শো করবে
                $type = "products";
                $pRes = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 4");
                if ($pRes) {
                    while ($p = $pRes->fetch_assoc()) {
                        $price = (float)$p['price'];
                        $disc = (float)($p['discount_percent'] ?? 0);
                        $final = $disc > 0 ? ($price - ($price * ($disc / 100))) : $price;
                        $productCards[] = [
                            'id' => (int)$p['id'],
                            'name' => $p['name'],
                            'price' => round($final, 2),
                            'image' => !empty($p['image']) ? $p['image'] : ''
                        ];
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'reply' => $replyText,
            'type' => $type,
            'data' => $productCards
        ]);
        exit;
    }

    // Action B: Direct In-Chat Checkout Order Creation
    if ($action === 'place_chat_order') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $size      = trim($_POST['size'] ?? 'M');
        $phone     = trim($_POST['phone'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $payment   = trim($_POST['payment_method'] ?? 'cod');

        if ($productId <= 0 || empty($phone) || empty($address)) {
            echo json_encode(['success' => false, 'message' => 'Please provide both your phone number and delivery address.']);
            exit;
        }

        $pStmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $pStmt->bind_param("i", $productId);
        $pStmt->execute();
        $prod = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        if (!$prod) {
            echo json_encode(['success' => false, 'message' => 'Selected product not found.']);
            exit;
        }

        $price = (float)$prod['price'];
        $disc  = (float)($prod['discount_percent'] ?? 0);
        $finalPrice = $disc > 0 ? ($price - ($price * ($disc / 100))) : $price;
        
        $shipping = 100.00;
        $vat = $finalPrice * 0.05;
        $grandTotal = $finalPrice + $shipping + $vat;

        try {
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, phone, payment_method, created_at) VALUES (?, ?, 'Pending', ?, ?, ?, NOW())");
            $stmt->bind_param("idsss", $userId, $grandTotal, $address, $phone, $payment);
            
            if ($stmt->execute()) {
                $orderId = $stmt->insert_id;
                $stmt->close();

                if (!isset($_SESSION['my_order_ids'])) {
                    $_SESSION['my_order_ids'] = [];
                }
                $_SESSION['my_order_ids'][] = $orderId;
                $_SESSION['placed_order_id'] = $orderId;

                echo json_encode([
                    'success' => true,
                    'order_id' => $orderId,
                    'message' => "Congratulations " . htmlspecialchars($userName) . "! Your order #ORD-" . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT) . " has been placed successfully. Total Amount: ৳" . number_format($grandTotal, 0) . "."
                ]);
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
        
        <!-- Fixed AI Header -->
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
                        <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-300 font-bold text-[9px] rounded-full uppercase border border-emerald-400/30">OpenAI Powered</span>
                    </h1>
                    <p class="text-[11px] text-slate-300">Professional Fashion Advisor & Instant Checkout</p>
                </div>
            </div>

            <a href="/online-shopping-management/shop.php" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition hidden sm:flex items-center gap-1.5">
                <span>Shop Page</span>
            </a>
        </div>

        <!-- Scrollable Messages Area -->
        <div id="chatStream" class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-4 bg-slate-50/50 text-xs">
            
            <!-- Default Welcome Message -->
            <div class="flex items-start gap-3 max-w-2xl">
                <div class="w-8 h-8 rounded-xl bg-slate-950 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-md">
                    AI
                </div>
                <div class="bg-white p-4 rounded-2xl rounded-tl-none border border-slate-200/80 shadow-xs space-y-2 text-slate-800 leading-relaxed">
                    <p class="font-bold text-slate-900">
                        Greetings <?= htmlspecialchars($userName) ?>.
                    </p>
                    <p>
                        I am your personal AI fashion concierge for NOVERA. Ask me for outfit recommendations based on your preferences, check your order status, or place an order directly inside this chat interface.
                    </p>
                </div>
            </div>

        </div>

        <!-- Suggestion Chips Bar -->
        <div class="px-4 py-2 bg-white border-t border-slate-100 flex items-center gap-2 overflow-x-auto whitespace-nowrap scrollbar-none shrink-0 text-[11px]">
            <button onclick="sendQuickMsg('I need a wedding outfit recommendation')" class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                Wedding Outfits
            </button>
            <button onclick="sendQuickMsg('Show me Panjabi collection')" class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                Panjabi Collection
            </button>
            <button onclick="sendQuickMsg('Where is my order status?')" class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300 border border-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                Track My Order
            </button>
        </div>

        <!-- Fixed Chat Input Bar -->
        <form id="chatForm" onsubmit="handleUserSubmit(event)" class="p-3 sm:p-4 bg-white border-t border-slate-200/80 flex items-center gap-2 shrink-0">
            <input type="text" id="userInput" placeholder="Ask AI about fashion, outfits, or order status..." 
                   class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
            
            <button type="submit" class="py-3 px-5 bg-slate-950 hover:bg-emerald-600 active:scale-95 text-white font-black rounded-2xl shadow-lg transition flex items-center gap-1 cursor-pointer shrink-0">
                <span>Send</span>
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </form>

    </div>
</div>

<!-- IN-CHAT DIRECT CHECKOUT MODAL -->
<div id="chatCheckoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4 hidden">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-6 space-y-5 border border-slate-100 animate-in fade-in zoom-in duration-200">
        
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                In-Chat Direct Checkout
            </h3>
            <button onclick="closeCheckoutModal()" class="text-slate-400 hover:text-slate-700 font-bold text-base cursor-pointer">✕</button>
        </div>

        <div id="modalProductSummary" class="p-3 bg-slate-50 rounded-2xl border border-slate-100 flex items-center gap-3">
            <!-- Dynamic Product Info -->
        </div>

        <form id="directOrderForm" onsubmit="submitDirectOrder(event)" class="space-y-4 text-xs">
            <input type="hidden" id="modalProductId" name="product_id">

            <div class="space-y-1">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Select Size</label>
                <select id="modalSize" name="size" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 focus:outline-none">
                    <option value="S">Small (S)</option>
                    <option value="M" selected>Medium (M)</option>
                    <option value="L">Large (L)</option>
                    <option value="XL">Extra Large (XL)</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Phone Number *</label>
                <input type="tel" id="modalPhone" name="phone" required placeholder="01700000000"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20">
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Delivery Address *</label>
                <textarea id="modalAddress" name="address" rows="2" required placeholder="House, Road, Area, District..."
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

        fetch('ai-assist.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            removeTypingIndicator(typingId);
            if (data.success) {
                appendAIBubble(data.reply, data.type, data.data);
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

    function appendAIBubble(replyText, type = 'text', data = []) {
        const stream = document.getElementById('chatStream');
        let extraContent = '';

        if (type === 'products' && data.length > 0) {
            extraContent = '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">';
            data.forEach(p => {
                const cleanedImg = p.image ? p.image.replace(/^(\.\.\/)+/, '').replace(/^online-shopping-management\//, '') : '';
                const imgPath = cleanedImg ? '/online-shopping-management/' + cleanedImg : '';
                
                extraContent += `
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 space-y-2 flex flex-col justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-14 bg-slate-200 rounded-xl overflow-hidden shrink-0">
                                ${imgPath ? `<img src="${imgPath}" class="w-full h-full object-cover">` : '<div class="w-full h-full flex items-center justify-center font-bold text-[8px]">NOVERA</div>'}
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 line-clamp-1 text-xs">${escapeHtml(p.name)}</h4>
                                <span class="text-emerald-600 font-black text-xs">৳${Number(p.price).toLocaleString()}</span>
                            </div>
                        </div>
                        <button onclick="openChatCheckout(${p.id}, '${escapeHtml(p.name)}', ${p.price}, '${imgPath}')" 
                                class="w-full py-2 bg-slate-950 hover:bg-emerald-600 text-white font-extrabold text-[11px] rounded-xl transition text-center cursor-pointer">
                            Order Now (In Chat)
                        </button>
                    </div>
                `;
            });
            extraContent += '</div>';
        }

        const formattedReply = escapeHtml(replyText).replace(/\n/g, '<br>');

        const html = `
            <div class="flex items-start gap-3 max-w-2xl">
                <div class="w-8 h-8 rounded-xl bg-slate-950 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-md">AI</div>
                <div class="bg-white p-4 rounded-2xl rounded-tl-none border border-slate-200/80 shadow-xs space-y-2 text-slate-800 leading-relaxed w-full">
                    <p class="font-medium">${formattedReply}</p>
                    ${extraContent}
                </div>
            </div>
        `;

        stream.insertAdjacentHTML('beforeend', html);
        stream.scrollTop = stream.scrollHeight;
    }

    function openChatCheckout(id, name, price, img) {
        document.getElementById('modalProductId').value = id;
        document.getElementById('modalProductSummary').innerHTML = `
            <div class="w-12 h-14 bg-slate-200 rounded-xl overflow-hidden shrink-0">
                ${img ? `<img src="${img}" class="w-full h-full object-cover">` : '<div class="w-full h-full flex items-center justify-center font-bold text-[8px]">NOVERA</div>'}
            </div>
            <div>
                <h4 class="font-bold text-slate-900 text-xs">${name}</h4>
                <span class="text-rose-600 font-black text-xs">৳${Number(price).toLocaleString()}</span>
            </div>
        `;
        document.getElementById('chatCheckoutModal').classList.remove('hidden');
    }

    function closeCheckoutModal() {
        document.getElementById('chatCheckoutModal').classList.add('hidden');
    }

    function submitDirectOrder(e) {
        e.preventDefault();
        const formData = new FormData(document.getElementById('directOrderForm'));
        formData.append('action', 'place_chat_order');

        fetch('ai-assist.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            closeCheckoutModal();
            if (data.success) {
                appendAIBubble(data.message);
            } else {
                appendAIBubble('Order failed: ' + data.message);
            }
        })
        .catch(err => {
            closeCheckoutModal();
            appendAIBubble('Failed to submit order. Please check network connection.');
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>