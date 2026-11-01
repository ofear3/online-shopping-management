<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';

// এডমিন সেশন আলাদা কি না চেক করা (এডমিন হলে কাস্টমার ইউজার আইডি হিসেবে ১ বা ডামি আইডি ধরবে না)
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// ইউজার এডমিন হলে კাস্টমার ইউজার আইডি হিসেবে ০ ধরা হবে, অন্যথায় কাস্টমার আইডি নেওয়া হবে
$userId = (!$isAdmin && isset($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : 0;
$myOrderIds = $_SESSION['my_order_ids'] ?? [];

// কারেন্ট সেশনে লাস্ট অর্ডারের আইডি থাকলে তাও লিস্টে যুক্ত করা
if (isset($_SESSION['placed_order_id']) && !in_array($_SESSION['placed_order_id'], $myOrderIds)) {
    $myOrderIds[] = (int)$_SESSION['placed_order_id'];
}

$orders = [];

// ১. যদি সাধারণ ইউজার (নন-এডমিন) লগইন করা থাকে
if ($userId > 0) {
    $stmt = $conn->prepare("SELECT o.*, u.name AS user_fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.user_id = ? ORDER BY o.id DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    }
} 
// ২. যদি লগইন না থাকে বা এডমিন হিসেবে লগইন থাকে, তবে শুধুমাত্র সেশনে বর্তমান ব্রাউজিংয়ে প্লেস করা অর্ডারগুলো ফেচ করবে
elseif (!empty($myOrderIds)) {
    $inClause = implode(',', array_map('intval', $myOrderIds));
    $result = $conn->query("SELECT o.*, u.name AS user_fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id IN ($inClause) ORDER BY o.id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Print-Only Style Sheet for Invoice -->
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #printableInvoice, #printableInvoice * {
            visibility: visible;
        }
        #printableInvoice {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 20px;
            background: white !important;
            color: black !important;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

<div class="min-h-[85vh] py-8 sm:py-12 px-3 sm:px-6 lg:px-8 bg-slate-50/60">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Top Hero Header -->
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-emerald-950 rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-6 border border-slate-800">
            <div class="space-y-2 text-center sm:text-left z-10">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 font-bold text-[10px] sm:text-[11px] rounded-full uppercase tracking-wider">
                    ✨ Real-Time Order Concierge
                </span>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight">
                    <?= $currentLang === 'bn' ? 'আমার অর্ডারসমূহ' : 'My Orders & Live Tracking' ?>
                </h1>
                <p class="text-xs text-slate-300 max-w-md">
                    <?= $currentLang === 'bn' ? 'আপনার অর্ডারের লাইভ ডেলিভারি আপডেট দেখুন এবং ইনভয়েস ডাউনলোড করুন।' : 'Track live status of your purchases and download official NOVERA receipts.' ?>
                </p>
            </div>

            <a href="/online-shopping-management/shop.php" class="z-10 px-6 py-3 bg-white/10 hover:bg-white/20 text-white text-xs font-extrabold rounded-2xl border border-white/20 transition flex items-center gap-2 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span><?= $currentLang === 'bn' ? 'শপ পেজে যান' : 'Continue Shopping' ?></span>
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <!-- Empty Order State -->
            <div class="bg-white rounded-3xl p-10 sm:p-16 border border-slate-200/80 shadow-xl text-center space-y-5 max-w-xl mx-auto">
                <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto shadow-inner">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div class="space-y-2">
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900">
                        <?= $currentLang === 'bn' ? 'কোনো অর্ডার পাওয়া যায়নি!' : 'No Orders Found!' ?>
                    </h2>
                    <p class="text-xs text-slate-500 leading-relaxed max-w-sm mx-auto">
                        <?= $currentLang === 'bn' ? 'আপনি এখনো কোনো পণ্য অর্ডার করেননি।' : 'You have not placed any orders yet. Explore our luxury collection.' ?>
                    </p>
                </div>
                <div class="pt-2">
                    <a href="/online-shopping-management/shop.php" class="inline-flex py-3.5 px-8 bg-slate-950 hover:bg-slate-850 text-white text-xs font-bold rounded-2xl shadow-lg transition">
                        <?= $currentLang === 'bn' ? 'শপ ঘুরে দেখুন' : 'Explore Catalog' ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            
            <!-- User's Private Orders List -->
            <div class="space-y-6">
                <?php foreach ($orders as $ord): 
                    $statusRaw = strtolower(trim($ord['status'] ?? 'pending'));
                    
                    // ৪-স্টেপ ট্র্যাকিং
                    $stepProgress = 1;
                    if ($statusRaw === 'processing') {
                        $stepProgress = 2;
                    } elseif ($statusRaw === 'shipped' || $statusRaw === 'delivering') {
                        $stepProgress = 3;
                    } elseif ($statusRaw === 'delivered' || $statusRaw === 'completed') {
                        $stepProgress = 4;
                    }
                    
                    $isCancelled = ($statusRaw === 'cancelled');
                ?>
                    <div class="bg-white rounded-3xl p-5 sm:p-8 border border-slate-200/80 shadow-lg space-y-6 hover:border-emerald-500/40 transition duration-300">
                        
                        <!-- Order Top Bar -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-slate-400 uppercase tracking-wider">Order ID:</span>
                                    <span class="text-sm font-black text-slate-900 font-mono">#ORD-<?= str_pad((string)$ord['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                </div>
                                <p class="text-[11px] font-bold text-slate-400">
                                    Placed on: <?= date('M d, Y - h:i A', strtotime($ord['created_at'])) ?>
                                </p>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-4">
                                <div class="text-left sm:text-right">
                                    <span class="text-[10px] font-extrabold text-slate-400 uppercase block">Total Payable</span>
                                    <span class="text-base sm:text-lg font-black text-rose-600">৳<?= number_format((float)$ord['total_amount'], 0) ?></span>
                                </div>

                                <button onclick="openInvoiceModal(<?= htmlspecialchars(json_encode($ord), ENT_QUOTES, 'UTF-8') ?>)" 
                                        class="px-4 py-2.5 bg-slate-950 hover:bg-emerald-600 text-white text-xs font-black rounded-2xl transition shadow-md flex items-center gap-1.5 cursor-pointer shrink-0">
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span><?= $currentLang === 'bn' ? 'ইনভয়েস' : 'Invoice' ?></span>
                                </button>
                            </div>
                        </div>

                        <!-- LIVE TRACKING STEPPER UI -->
                        <?php if ($isCancelled): ?>
                            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-center text-rose-700 text-xs font-bold">
                                ❌ This order has been cancelled.
                            </div>
                        <?php else: ?>
                            <div class="py-2">
                                <span class="text-[11px] font-black uppercase text-slate-400 tracking-wider block mb-4">Live Tracking Status:</span>
                                
                                <div class="relative flex items-center justify-between max-w-3xl mx-auto px-2">
                                    
                                    <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-100 -translate-y-1/2 z-0"></div>
                                    <div class="absolute top-1/2 left-0 h-1 bg-emerald-500 -translate-y-1/2 z-0 transition-all duration-500" 
                                          style="width: <?= (($stepProgress - 1) / 3) * 100 ?>%;"></div>

                                    <!-- Step 1 -->
                                    <div class="relative z-10 flex flex-col items-center gap-1.5">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-extrabold text-xs transition duration-300 <?= $stepProgress >= 1 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-500' ?>">
                                            ✓
                                        </div>
                                        <span class="text-[10px] sm:text-xs font-extrabold <?= $stepProgress >= 1 ? 'text-slate-900' : 'text-slate-400' ?>">Placed</span>
                                    </div>

                                    <!-- Step 2 -->
                                    <div class="relative z-10 flex flex-col items-center gap-1.5">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-extrabold text-xs transition duration-300 <?= $stepProgress >= 2 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-500' ?>">
                                            <?= $stepProgress >= 2 ? '✓' : '2' ?>
                                        </div>
                                        <span class="text-[10px] sm:text-xs font-extrabold <?= $stepProgress >= 2 ? 'text-slate-900' : 'text-slate-400' ?>">Processing</span>
                                    </div>

                                    <!-- Step 3 -->
                                    <div class="relative z-10 flex flex-col items-center gap-1.5">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-extrabold text-xs transition duration-300 <?= $stepProgress >= 3 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-500' ?>">
                                            <?= $stepProgress >= 3 ? '✓' : '3' ?>
                                        </div>
                                        <span class="text-[10px] sm:text-xs font-extrabold <?= $stepProgress >= 3 ? 'text-slate-900' : 'text-slate-400' ?>">Delivering</span>
                                    </div>

                                    <!-- Step 4 -->
                                    <div class="relative z-10 flex flex-col items-center gap-1.5">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-extrabold text-xs transition duration-300 <?= $stepProgress >= 4 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-500' ?>">
                                            <?= $stepProgress >= 4 ? '✓' : '4' ?>
                                        </div>
                                        <span class="text-[10px] sm:text-xs font-extrabold <?= $stepProgress >= 4 ? 'text-slate-900' : 'text-slate-400' ?>">Delivered</span>
                                    </div>

                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Details Footer -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100 text-xs text-slate-600">
                            <div>
                                <span class="font-bold text-slate-900 block">Shipping Address:</span>
                                <p class="text-slate-500 mt-0.5"><?= e($ord['shipping_address']) ?></p>
                            </div>
                            <div class="sm:text-right">
                                <span class="font-bold text-slate-900 block">Payment Method:</span>
                                <p class="text-slate-500 uppercase font-extrabold mt-0.5"><?= e($ord['payment_method'] ?? 'COD') ?></p>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- INVOICE MODAL -->
<div id="invoiceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-3 sm:p-4 hidden">
    <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        
        <div class="p-4 bg-slate-950 text-white flex items-center justify-between shrink-0 no-print">
            <span class="text-xs font-black uppercase tracking-wider text-emerald-400">NOVERA Official Billing Invoice</span>
            <div class="flex items-center gap-2">
                <button onclick="printInvoice()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Print / Download PDF</span>
                </button>
                <button onclick="closeInvoiceModal()" class="w-8 h-8 bg-white/10 hover:bg-white/20 text-white rounded-xl flex items-center justify-center font-bold cursor-pointer">✕</button>
            </div>
        </div>

        <div id="printableInvoice" class="p-6 sm:p-10 space-y-6 overflow-y-auto bg-white text-slate-800 text-xs">
            
            <div class="flex justify-between items-start border-b border-slate-200 pb-6">
                <div class="space-y-1">
                    <h2 class="text-3xl font-black text-slate-900 tracking-tight">NOVERA</h2>
                    <p class="text-[11px] font-bold text-emerald-600">LUXURY APPAREL & FASHION</p>
                    <p class="text-[11px] text-slate-500">Level 4, Gulshan 2, Dhaka-1212, Bangladesh</p>
                    <p class="text-[11px] text-slate-500">Support: support@novera.com</p>
                </div>
                <div class="text-right space-y-1">
                    <span class="inline-block px-3 py-1 bg-slate-950 text-white font-black text-[10px] uppercase rounded-lg">OFFICIAL RECEIPT</span>
                    <p class="font-mono font-black text-slate-900 text-sm mt-1" id="invOrderId">#ORD-00000</p>
                    <p class="text-slate-500 text-[11px]" id="invDate">Date: Oct 10, 2026</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div class="space-y-1">
                    <span class="font-black text-[10px] text-emerald-600 uppercase tracking-widest block">Billed To:</span>
                    <p class="font-bold text-slate-900 text-sm" id="invCustomerName">Valued Customer</p>
                    <p class="text-slate-700" id="invAddress">House 12, Road 4, Dhaka</p>
                    <p class="text-slate-600" id="invPhone">Phone: 01700000000</p>
                </div>
                <div class="text-right space-y-1">
                    <span class="font-black text-[10px] text-emerald-600 uppercase tracking-widest block">Payment Info:</span>
                    <p class="font-bold text-slate-900 uppercase" id="invPayment">Cash on Delivery</p>
                    <p class="text-slate-600 capitalize" id="invStatus">Status: Pending</p>
                </div>
            </div>

            <div class="space-y-2">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b-2 border-slate-900 text-[10px] font-black uppercase text-slate-900">
                            <th class="py-2.5">Item Description</th>
                            <th class="py-2.5 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="py-3 font-semibold text-slate-800">
                                NOVERA Apparel Order Purchase
                                <span class="block text-[10px] text-slate-400 font-normal">Includes Subtotal + Vat (5%) + Express Shipping</span>
                            </td>
                            <td class="py-3 text-right font-black text-slate-900" id="invAmount">৳0.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-between items-center pt-4 border-t-2 border-slate-900">
                <div>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Authorized Stamp</span>
                    <div class="w-20 h-10 border border-emerald-500/40 rounded-xl flex items-center justify-center text-[10px] font-black text-emerald-600 uppercase mt-1">
                        VERIFIED
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold text-slate-500">Total Payable:</span>
                    <p class="text-2xl font-black text-rose-600" id="invGrandTotal">৳0.00</p>
                </div>
            </div>

            <div class="pt-6 text-center text-slate-400 text-[10px] border-t border-slate-100 space-y-0.5">
                <p class="font-bold text-slate-600">Thank you for choosing NOVERA Concierge.</p>
                <p>This is an authentic computer-generated billing document.</p>
            </div>

        </div>

    </div>
</div>

<script>
    function openInvoiceModal(ord) {
        document.getElementById('invOrderId').innerText = '#ORD-' + String(ord.id).padStart(5, '0');
        document.getElementById('invDate').innerText = 'Date: ' + ord.created_at;
        
        const sessionUserName = "<?= e($_SESSION['user_name'] ?? '') ?>";
        const customerName = ord.fullname || ord.user_fullname || ord.customer_name || sessionUserName || 'Valued Customer';
        document.getElementById('invCustomerName').innerText = customerName;

        document.getElementById('invAddress').innerText = ord.shipping_address;
        document.getElementById('invPhone').innerText = 'Phone: ' + (ord.phone || 'N/A');
        document.getElementById('invPayment').innerText = (ord.payment_method || 'COD').toUpperCase();
        document.getElementById('invStatus').innerText = 'Status: ' + (ord.status || 'Pending');
        
        const formatted = '৳' + Number(ord.total_amount).toLocaleString();
        document.getElementById('invAmount').innerText = formatted;
        document.getElementById('invGrandTotal').innerText = formatted;

        document.getElementById('invoiceModal').classList.remove('hidden');
    }

    function closeInvoiceModal() {
        document.getElementById('invoiceModal').classList.add('hidden');
    }

    function printInvoice() {
        window.print();
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>