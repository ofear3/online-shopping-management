<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';

// ১. সেশন থেকে পপআপ সাকসেস ফ্ল্যাগ চেক
$showSuccessPopup = false;
$placedOrderId = 0;

if (isset($_SESSION['order_placed_success'])) {
    $showSuccessPopup = true;
    $placedOrderId = (int) ($_SESSION['placed_order_id'] ?? 0);
    unset($_SESSION['order_placed_success']);
    unset($_SESSION['placed_order_id']);
}

// কার্ট খালি থাকলে এবং সাকসেস পপআপ না থাকলে কার্ট পেজে রিডাইরেক্ট
if (!$showSuccessPopup && (empty($_SESSION['cart']) || !is_array($_SESSION['cart']))) {
    header("Location: /online-shopping-management/cart.php");
    exit;
}

// ২. হিসাব-নিকাশ (ভ্যাট বাদ দিয়ে সঠিক সাবটোটাল ও শিপিং)
$subtotal = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
}

$shipping   = ($subtotal > 0) ? 100.00 : 0.00;
$grandTotal = $subtotal + $shipping;

$errors        = [];
$phone         = '';
$address       = '';
$paymentMethod = 'cod';

// ৩. অর্ডার সাবমিশন হ্যান্ডলিং (POST Response)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone         = trim($_POST['phone'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'cod');

    if (empty($phone)) {
        $errors[] = ($currentLang === 'bn') ? 'আপনার মোবাইল নম্বর প্রদান করুন।' : 'Please enter your phone number.';
    }
    if (empty($address)) {
        $errors[] = ($currentLang === 'bn') ? 'আপনার ডেলিভারি ঠিকানা প্রদান করুন।' : 'Please enter your delivery address.';
    }

    if (empty($errors)) {
        // ফরেইন কি এরর এড়াতে ইউজার আইডি ভ্যালিডেশন
        $userId = null;
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            $chkUser = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $chkUser->bind_param("i", $_SESSION['user_id']);
            $chkUser->execute();
            $resUser = $chkUser->get_result();
            if ($resUser->num_rows > 0) {
                $userId = (int) $_SESSION['user_id'];
            }
            $chkUser->close();
        }

        $status = 'pending';
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, phone, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");

        if ($stmt) {
            // যদি user_id null হয় তবে bind_param এ integer বা null হ্যান্ডেল করার জন্য
            if ($userId === null) {
                $nullVar = null;
                // ডাইনামিক টাইপ বাইন্ডিংয়ের বিকল্প হিসেবে সরাসরি এক্সিকিউট বা সেফ স্টেটমেন্ট
                $stmt->bind_param("idssss", $nullVar, $grandTotal, $status, $address, $phone, $paymentMethod);
            } else {
                $stmt->bind_param("idssss", $userId, $grandTotal, $status, $address, $phone, $paymentMethod);
            }

            if ($stmt->execute()) {
                $newOrderId = $stmt->insert_id;
                $stmt->close();

                $_SESSION['order_placed_success'] = true;
                $_SESSION['placed_order_id']    = $newOrderId;

                if (!isset($_SESSION['my_order_ids'])) {
                    $_SESSION['my_order_ids'] = [];
                }
                $_SESSION['my_order_ids'][] = $newOrderId;

                unset($_SESSION['cart']);

                header("Location: /online-shopping-management/checkout.php");
                exit;
            } else {
                $errors[] = "Database Insert Error: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[85vh] py-6 sm:py-12 px-3 sm:px-6 lg:px-8 bg-slate-50/60">
    <div class="max-w-7xl mx-auto space-y-6 sm:space-y-8">

        <!-- Header -->
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-emerald-950 rounded-2xl sm:rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="space-y-2 text-center sm:text-left">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 font-bold text-[10px] sm:text-[11px] rounded-full uppercase tracking-wider">
                    🔒 Secure Checkout
                </span>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight">
                    <?= $currentLang === 'bn' ? 'অর্ডার চেকআউট' : 'Checkout & Payment' ?>
                </h1>
            </div>
            <a href="/online-shopping-management/cart.php" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-2xl border border-white/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span><?= $currentLang === 'bn' ? 'কার্টে ফিরে যান' : 'Back to Cart' ?></span>
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                <?php foreach ($errors as $err): ?>
                    <p class="flex items-center gap-2 font-medium">
                        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?= e($err) ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="checkoutForm" action="/online-shopping-management/checkout.php" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="cod">

            <!-- Left Column: Delivery & Payment Details -->
            <div class="lg:col-span-7 space-y-6">

                <!-- Shipping Info Card -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-8 border border-slate-200/80 shadow-xl space-y-5">
                    <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2 border-b border-slate-100 pb-4">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <?= $currentLang === 'bn' ? 'ডেলিভারি তথ্য' : 'Shipping Information' ?>
                    </h2>

                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                <?= $currentLang === 'bn' ? 'মোবাইল নম্বর' : 'Phone Number' ?> *
                            </label>
                            <input type="tel" id="shippingPhone" name="phone" required value="" placeholder="01700000000" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                <?= $currentLang === 'bn' ? 'সম্পূর্ণ ডেলিভারি ঠিকানা' : 'Shipping Address' ?> *
                            </label>
                            <textarea id="shippingAddress" name="address" rows="3" required placeholder="<?= $currentLang === 'bn' ? 'হাউজ নম্বর, রোড নম্বর, এলাকা, জেলা...' : 'House number, road name, area, district...' ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Card -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-8 border border-slate-200/80 shadow-xl space-y-4">
                    <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2 border-b border-slate-100 pb-4">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <?= $currentLang === 'bn' ? 'পেমেন্ট পদ্ধতি' : 'Payment Method' ?>
                    </h2>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <!-- Cash on Delivery -->
                        <label class="cursor-pointer payment-option-label" data-method="cod">
                            <input type="radio" name="pm_radio" value="cod" checked class="peer sr-only">
                            <div class="p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50/50 transition flex flex-col items-center justify-center gap-2 text-center h-full">
                                <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <span class="text-xs font-black text-slate-900 leading-tight">Cash on Delivery</span>
                            </div>
                        </label>

                        <!-- bKash -->
                        <label class="cursor-pointer payment-option-label" data-method="bkash">
                            <input type="radio" name="pm_radio" value="bkash" class="peer sr-only">
                            <div class="p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-[#E2136E] peer-checked:bg-pink-50/50 transition flex flex-col items-center justify-center gap-2 text-center h-full">
                                <div class="w-8 h-8 rounded-xl bg-white shadow-sm border border-slate-100 flex items-center justify-center overflow-hidden p-1">
                                    <img src="/online-shopping-management/includes/bkash.png" alt="bKash" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-black text-slate-900 leading-tight">bKash</span>
                            </div>
                        </label>

                        <!-- Rocket -->
                        <label class="cursor-pointer payment-option-label" data-method="rocket">
                            <input type="radio" name="pm_radio" value="rocket" class="peer sr-only">
                            <div class="p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-[#8C3494] peer-checked:bg-purple-50/50 transition flex flex-col items-center justify-center gap-2 text-center h-full">
                                <div class="w-8 h-8 rounded-xl bg-white shadow-sm border border-slate-100 flex items-center justify-center overflow-hidden p-1">
                                    <img src="/online-shopping-management/includes/rocket.png" alt="Rocket" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-black text-slate-900 leading-tight">Rocket</span>
                            </div>
                        </label>

                        <!-- Card Payment -->
                        <label class="cursor-pointer payment-option-label" data-method="card">
                            <input type="radio" name="pm_radio" value="card" class="peer sr-only">
                            <div class="p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-indigo-600 peer-checked:bg-indigo-50/50 transition flex flex-col items-center justify-center gap-2 text-center h-full">
                                <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs font-black text-slate-900 leading-tight">Card Payment</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Items Summary & Total -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-8 border border-slate-200/80 shadow-xl space-y-6">
                    <h2 class="text-base font-black text-slate-900 uppercase tracking-tight border-b border-slate-100 pb-4">
                        <?= $currentLang === 'bn' ? 'অর্ডার আইটেমসমূহ' : 'Order Items' ?>
                    </h2>

                    <!-- Items List -->
                    <div class="space-y-3 max-h-60 overflow-y-auto pr-1 divide-y divide-slate-100">
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <div class="pt-3 first:pt-0 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-12 h-14 bg-slate-100 rounded-xl overflow-hidden shrink-0 border border-slate-100">
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="/online-shopping-management/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-[9px] font-bold text-slate-400">NOVERA</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="text-xs font-bold text-slate-900 truncate"><?= e($item['name']) ?></h4>
                                            <span class="text-[10px] text-slate-500 font-medium block">Size: <?= e($item['size']) ?> | Qty: <?= $item['quantity'] ?></span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-black text-slate-900 shrink-0">৳<?= number_format($item['price'] * $item['quantity'], 0) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Price Calculation -->
                    <div class="space-y-3 pt-4 border-t border-slate-100 text-xs">
                        <div class="flex items-center justify-between text-slate-600">
                            <span><?= $currentLang === 'bn' ? 'সাবটোটাল' : 'Subtotal' ?></span>
                            <span class="font-bold text-slate-900">৳<?= number_format($subtotal, 0) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span><?= $currentLang === 'bn' ? 'ডেলিভারি চার্জ' : 'Shipping Fee' ?></span>
                            <span class="font-bold text-slate-900">৳<?= number_format($shipping, 0) ?></span>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-sm font-black text-slate-900"><?= $currentLang === 'bn' ? 'সর্বমোট প্রদেয়' : 'Total Payable' ?></span>
                            <span id="grandTotalText" class="text-xl font-black text-rose-600" data-amount="<?= $grandTotal ?>">৳<?= number_format($grandTotal, 0) ?></span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="button" id="placeOrderBtn" class="w-full py-4 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white text-xs font-black rounded-2xl shadow-xl transition flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-5 h-5 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span id="submitBtnText"><?= $currentLang === 'bn' ? 'অর্ডার নিশ্চিত করুন' : 'Confirm & Place Order' ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- REALISTIC bKash / MOBILE BANKING GATEWAY PROTOTYPE MODAL -->
<div id="paymentGatewayModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4">
    <div id="gatewayCard" class="bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden border border-slate-100 transform transition-all scale-95 opacity-0 duration-300">
        
        <!-- Gateway Header -->
        <div id="gatewayHeaderBg" class="bg-[#E2136E] text-white p-5 text-center relative">
            <button type="button" id="closeGateway" class="absolute top-4 right-4 text-white/80 hover:text-white text-sm font-bold bg-white/10 w-8 h-8 rounded-full flex items-center justify-center">✕</button>
            <div id="gatewayLogoBox" class="w-12 h-12 bg-white rounded-2xl mx-auto flex items-center justify-center shadow-md mb-2 overflow-hidden p-1.5">
                <img id="gatewayHeaderImg" src="/online-shopping-management/includes/bkash.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <h3 id="gatewayTitle" class="text-sm font-bold tracking-wide">bKash Payment Gateway</h3>
            <p class="text-[11px] text-white/80 mt-0.5">Novera Online Shop</p>
        </div>

        <!-- Inline Error Message Box -->
        <div id="gatewayErrorBox" class="hidden mx-6 mt-4 p-2.5 bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-bold rounded-xl text-center"></div>

        <!-- Step 1: Phone Number Input -->
        <div id="gatewayStep1" class="p-6 space-y-4">
            <div class="bg-slate-50 p-3 rounded-2xl flex items-center justify-between text-xs border border-slate-100">
                <span class="text-slate-500 font-medium">Total Amount</span>
                <span class="font-black text-slate-900" id="gatewayDisplayAmount">৳0</span>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider" id="gatewayNumberLabel">Your bKash Account Number</label>
                <input type="text" id="gatewayPhoneInput" placeholder="017XXXXXXXX" maxlength="11" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-900 focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#E2136E]/25 transition">
            </div>

            <div class="text-[11px] text-slate-500 leading-relaxed bg-pink-50/50 p-3 rounded-xl border border-pink-100/50">
                By clicking proceed, you agree to the terms & conditions of mobile financial services.
            </div>

            <button type="button" id="gatewayNextBtn" class="w-full py-3.5 bg-[#E2136E] hover:opacity-90 text-white text-xs font-black rounded-2xl shadow-lg transition">PROCEED</button>
        </div>

        <!-- Step 2: OTP Verification -->
        <div id="gatewayStep2" class="p-6 space-y-4 hidden">
            <div class="text-center space-y-1">
                <p class="text-xs text-slate-600 font-medium">Enter Verification Code sent to</p>
                <p class="text-xs font-black text-slate-900" id="otpSentPhone">017XXXXXXXX</p>
                <span class="inline-block text-[10px] bg-emerald-50 text-emerald-600 font-bold px-2 py-0.5 rounded-full mt-1">Placeholder OTP (e.g. 1234)</span>
            </div>

            <div class="space-y-1.5">
                <input type="text" id="gatewayOtpInput" placeholder="Enter 4-digit OTP" maxlength="4" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-center text-sm font-black tracking-widest text-slate-900 focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#E2136E]/25 transition">
            </div>

            <button type="button" id="gatewayVerifyOtpBtn" class="w-full py-3.5 bg-[#E2136E] hover:opacity-90 text-white text-xs font-black rounded-2xl shadow-lg transition">VERIFY OTP</button>
        </div>

        <!-- Step 3: PIN / Password Confirmation -->
        <div id="gatewayStep3" class="p-6 space-y-4 hidden">
            <div class="text-center space-y-1">
                <p class="text-xs text-slate-600 font-medium">Enter your PIN to confirm payment</p>
                <p class="text-xs font-black text-slate-900" id="pinDisplayAmount">৳0</p>
                <span class="inline-block text-[10px] bg-emerald-50 text-emerald-600 font-bold px-2 py-0.5 rounded-full mt-1">Placeholder PIN (e.g. 12345)</span>
            </div>

            <div class="space-y-1.5">
                <input type="password" id="gatewayPinInput" placeholder="Enter PIN" maxlength="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-center text-sm font-black tracking-widest text-slate-900 focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#E2136E]/25 transition">
            </div>

            <button type="button" id="gatewayConfirmBtn" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-2xl shadow-lg transition flex items-center justify-center gap-2">
                <span>CONFIRM PAYMENT</span>
            </button>
        </div>

    </div>
</div>

<!-- SUCCESS ORDER POPUP MODAL -->
<?php if ($showSuccessPopup): ?>
    <div id="orderSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm p-4">
        <div class="bg-white w-full max-w-md p-6 sm:p-8 rounded-3xl shadow-2xl border border-slate-100 text-center space-y-5 animate-in fade-in zoom-in duration-300">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="space-y-2">
                <?php if ($placedOrderId > 0): ?>
                    <span class="px-3 py-1 bg-emerald-50 text-emerald-700 font-extrabold text-[11px] rounded-full uppercase tracking-wider">
                        Order ID: #<?= $placedOrderId ?>
                    </span>
                <?php endif; ?>
                <h3 class="text-xl font-black text-slate-900">
                    <?= $currentLang === 'bn' ? 'অর্ডার সফলভাবে সম্পন্ন হয়েছে!' : 'Order Successfully Placed!' ?>
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    <?= $currentLang === 'bn' ? 'ধন্যবাদ! আপনার অর্ডারটি সিস্টেমে যুক্ত হয়েছে এবং অ্যাডমিন ড্যাশবোর্ডে পাঠানো হয়েছে।' : 'Thank you for shopping with NOVERA. Your order has been placed and sent to the admin dashboard.' ?>
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-2">
                <a href="/online-shopping-management/shop.php" class="py-3.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-2xl transition text-center flex items-center justify-center">
                    <?= $currentLang === 'bn' ? 'কেনাকাটা চালিয়ে যান' : 'Back to Shop' ?>
                </a>
                <a href="/online-shopping-management/my-orders.php" class="py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-2xl shadow-lg transition text-center flex items-center justify-center">
                    <?= $currentLang === 'bn' ? 'অর্ডার ট্র্যাকিং' : 'View Orders' ?>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Interactive Payment Prototype Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentLabels = document.querySelectorAll('.payment-option-label');
    const selectedPaymentMethodInput = document.getElementById('selectedPaymentMethod');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    
    // Gateway Elements
    const gatewayModal = document.getElementById('paymentGatewayModal');
    const gatewayCard = document.getElementById('gatewayCard');
    const closeGateway = document.getElementById('closeGateway');
    const gatewayHeaderBg = document.getElementById('gatewayHeaderBg');
    const gatewayHeaderImg = document.getElementById('gatewayHeaderImg');
    const gatewayTitle = document.getElementById('gatewayTitle');
    const gatewayDisplayAmount = document.getElementById('gatewayDisplayAmount');
    const gatewayNumberLabel = document.getElementById('gatewayNumberLabel');
    const gatewayPhoneInput = document.getElementById('gatewayPhoneInput');
    const gatewayNextBtn = document.getElementById('gatewayNextBtn');
    const gatewayErrorBox = document.getElementById('gatewayErrorBox');
    
    // Steps inside modal
    const gatewayStep1 = document.getElementById('gatewayStep1');
    const gatewayStep2 = document.getElementById('gatewayStep2');
    const otpSentPhone = document.getElementById('otpSentPhone');
    const gatewayOtpInput = document.getElementById('gatewayOtpInput');
    const gatewayVerifyOtpBtn = document.getElementById('gatewayVerifyOtpBtn');
    
    const gatewayStep3 = document.getElementById('gatewayStep3');
    const pinDisplayAmount = document.getElementById('pinDisplayAmount');
    const gatewayPinInput = document.getElementById('gatewayPinInput');
    const gatewayConfirmBtn = document.getElementById('gatewayConfirmBtn');

    function showModalError(msg) {
        gatewayErrorBox.innerText = msg;
        gatewayErrorBox.classList.remove('hidden');
        setTimeout(() => {
            gatewayErrorBox.classList.add('hidden');
        }, 3000);
    }

    // Handle Payment Selection Styling
    paymentLabels.forEach(label => {
        label.addEventListener('click', function() {
            const method = this.getAttribute('data-method');
            selectedPaymentMethodInput.value = method;
            
            const radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });

    // Handle Place Order Click
    placeOrderBtn.addEventListener('click', function(e) {
        const phone = document.getElementById('shippingPhone').value.trim();
        const address = document.getElementById('shippingAddress').value.trim();
        
        if (!phone || !address) {
            alert('Please fill in your Phone Number and Delivery Address first.');
            document.getElementById('shippingPhone').focus();
            return;
        }

        const method = selectedPaymentMethodInput.value;
        const grandTotal = document.getElementById('grandTotalText').getAttribute('data-amount');
        const formattedAmount = '৳' + Number(grandTotal).toLocaleString();

        // If Cash on Delivery, submit form directly
        if (method === 'cod') {
            document.getElementById('checkoutForm').submit();
            return;
        }

        // Configure Gateway theme based on method (সঠিক অ্যামাউন্ট পাস করা হচ্ছে)
        gatewayDisplayAmount.innerText = formattedAmount;
        pinDisplayAmount.innerText = formattedAmount;
        gatewayPhoneInput.value = '';

        if (method === 'bkash') {
            gatewayHeaderBg.className = "bg-[#E2136E] text-white p-5 text-center relative";
            gatewayHeaderImg.src = "/online-shopping-management/includes/bkash.png";
            gatewayTitle.innerText = "bKash Payment Gateway";
            gatewayNumberLabel.innerText = "Your bKash Account Number";
            gatewayNextBtn.style.backgroundColor = "#E2136E";
            gatewayVerifyOtpBtn.style.backgroundColor = "#E2136E";
        } else if (method === 'rocket') {
            gatewayHeaderBg.className = "bg-[#8C3494] text-white p-5 text-center relative";
            gatewayHeaderImg.src = "/online-shopping-management/includes/rocket.png";
            gatewayTitle.innerText = "Rocket Payment Gateway";
            gatewayNumberLabel.innerText = "Your Rocket Account Number";
            gatewayNextBtn.style.backgroundColor = "#8C3494";
            gatewayVerifyOtpBtn.style.backgroundColor = "#8C3494";
        } else if (method === 'card') {
            gatewayHeaderBg.className = "bg-indigo-600 text-white p-5 text-center relative";
            gatewayHeaderImg.src = "/online-shopping-management/includes/bkash.png";
            gatewayTitle.innerText = "Secure Card Gateway";
            gatewayNumberLabel.innerText = "Card Mobile / Account Number";
            gatewayNextBtn.style.backgroundColor = "#4F46E5";
            gatewayVerifyOtpBtn.style.backgroundColor = "#4F46E5";
        }

        // Reset steps & clear inputs
        gatewayErrorBox.classList.add('hidden');
        gatewayStep1.classList.remove('hidden');
        gatewayStep2.classList.add('hidden');
        gatewayStep3.classList.add('hidden');
        gatewayOtpInput.value = '';
        gatewayPinInput.value = '';

        // Show Modal with Animation
        gatewayModal.classList.remove('hidden');
        setTimeout(() => {
            gatewayCard.classList.remove('scale-95', 'opacity-0');
            gatewayCard.classList.add('scale-100', 'opacity-100');
        }, 10);
    });

    // Close Modal
    closeGateway.addEventListener('click', function() {
        gatewayCard.classList.remove('scale-100', 'opacity-100');
        gatewayCard.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            gatewayModal.classList.add('hidden');
        }, 200);
    });

    // Step 1 -> Step 2 (Phone to OTP)
    gatewayNextBtn.addEventListener('click', function() {
        const val = gatewayPhoneInput.value.trim();
        if (val.length < 11) {
            showModalError('Please enter a valid 11-digit mobile number.');
            gatewayPhoneInput.focus();
            return;
        }
        otpSentPhone.innerText = val;
        gatewayStep1.classList.add('hidden');
        gatewayStep2.classList.remove('hidden');
        gatewayOtpInput.focus();
    });

    // Step 2 -> Step 3 (OTP to PIN)
    gatewayVerifyOtpBtn.addEventListener('click', function() {
        const otp = gatewayOtpInput.value.trim();
        if (otp.length < 4) {
            showModalError('Please enter a valid 4-digit OTP.');
            gatewayOtpInput.focus();
            return;
        }
        gatewayStep2.classList.add('hidden');
        gatewayStep3.classList.remove('hidden');
        gatewayPinInput.focus();
    });

    // Step 3 -> Final Form Submit on Confirm PIN
    gatewayConfirmBtn.addEventListener('click', function() {
        const pin = gatewayPinInput.value.trim();
        if (pin.length < 4) {
            showModalError('Please enter your valid PIN.');
            gatewayPinInput.focus();
            return;
        }

        // Close modal and submit main form
        gatewayModal.classList.add('hidden');
        document.getElementById('checkoutForm').submit();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>