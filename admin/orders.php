<?php
declare(strict_types=1);

// ১. AJAX Request Handling (সবার উপরে রাখা হয়েছে)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status_ajax') {
    // Database Config & Session
    require_once __DIR__ . '/../config/db.php';
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json');

    $order_id   = (int)($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    $allowed_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    
    $matched_status = '';
    foreach ($allowed_statuses as $status_option) {
        if (strcasecmp($status_option, $new_status) === 0) {
            $matched_status = $status_option;
            break;
        }
    }

    if ($order_id > 0 && !empty($matched_status)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $matched_status, $order_id);
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => "Status Updated to {$matched_status}",
                    'new_status' => $matched_status
                ]);
                $stmt->close();
                exit;
            }
        }
    }

    echo json_encode(['success' => false, 'message' => 'Failed to update order status.']);
    exit;
}

// ২. সাধারণ পেজের জন্য হেডার ইনক্লুড
require_once __DIR__ . '/header.php';

// সেশন মেসেজ রিমুভ করা যাতে রিলোডে পপআপ না আসে
if (isset($_SESSION['toast_message'])) {
    unset($_SESSION['toast_message']);
}

// ৩. ডাটাবেজ থেকে অর্ডার ডাটা ফেচ
$ordersResult = $conn->query("
    SELECT o.*, u.name AS customer_name 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.id DESC
");
?>

<div class="p-6 md:p-8 space-y-6 bg-zinc-950 min-h-screen">

    <!-- Header -->
    <div>
        <h1 class="text-xl font-bold text-zinc-100 tracking-tight">Customer Orders</h1>
        <p class="text-xs text-zinc-400 mt-1">View customer purchases, payment methods, and manage order statuses</p>
    </div>

    <!-- Orders Table -->
    <div class="bg-zinc-900 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-zinc-950/60 border-b border-zinc-800/80 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3.5 px-4">Order ID</th>
                        <th class="py-3.5 px-4">Customer Name</th>
                        <th class="py-3.5 px-4">Shipping Info</th>
                        <th class="py-3.5 px-4">Amount & Payment</th>
                        <th class="py-3.5 px-4">Current Status</th>
                        <th class="py-3.5 px-4">Date & Time</th>
                        <th class="py-3.5 px-4 text-right">Update Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/50 text-xs">
                    <?php if ($ordersResult && $ordersResult->num_rows > 0): ?>
                        <?php while ($ord = $ordersResult->fetch_assoc()): ?>
                            <tr class="hover:bg-zinc-800/30 transition">
                                <!-- Order ID -->
                                <td class="py-3.5 px-4 font-bold text-emerald-400 font-mono">
                                    #ORD-<?= str_pad((string)$ord['id'], 5, '0', STR_PAD_LEFT) ?>
                                </td>

                                <!-- Customer Name & Phone -->
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-zinc-200"><?= e($ord['customer_name'] ?? 'Guest Customer') ?></p>
                                    <?php if (!empty($ord['phone'])): ?>
                                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ord['phone'])) ?>" 
                                           class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400 hover:text-emerald-300 hover:underline mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            <?= e($ord['phone']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <!-- Shipping Address -->
                                <td class="py-3.5 px-4">
                                    <p class="text-zinc-300 truncate max-w-[200px]" title="<?= e($ord['shipping_address']) ?>">
                                        <?= e($ord['shipping_address']) ?>
                                    </p>
                                </td>

                                <!-- Amount & Payment Method -->
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-zinc-100">$<?= number_format((float)$ord['total_amount'], 2) ?></p>
                                    <span class="inline-block px-1.5 py-0.5 rounded bg-zinc-950 text-[9px] uppercase font-bold text-zinc-400 border border-zinc-800 mt-0.5">
                                        <?= e($ord['payment_method'] ?? 'COD') ?>
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4">
                                    <?php 
                                        $curStatus = strtolower(trim((string)$ord['status']));
                                        $statusClass = match($curStatus) {
                                            'delivered', 'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                            'shipped'               => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                            'processing'            => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                            'cancelled'             => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                            default                 => 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                                        };
                                    ?>
                                    <span id="badge-<?= $ord['id'] ?>" class="px-2.5 py-1 rounded-lg border font-bold text-[10px] capitalize transition-all duration-300 <?= $statusClass ?>">
                                        <?= e($ord['status']) ?>
                                    </span>
                                </td>

                                <!-- Date & Time -->
                                <td class="py-3.5 px-4 text-zinc-400 text-[11px]">
                                    <?= date('M d, Y - h:i A', strtotime($ord['created_at'])) ?>
                                </td>

                                <!-- Select Dropdown -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="relative inline-block">
                                        <select onchange="handleStatusChange(<?= (int)$ord['id'] ?>, this.value)" 
                                                class="appearance-none pl-3 pr-7 py-1.5 bg-zinc-950 hover:bg-zinc-800 border border-zinc-800 rounded-xl text-xs font-medium text-zinc-200 focus:outline-none focus:border-emerald-500 transition cursor-pointer">
                                            <option value="Pending" <?= strcasecmp($ord['status'], 'Pending') === 0 ? 'selected' : '' ?>>Pending</option>
                                            <option value="Processing" <?= strcasecmp($ord['status'], 'Processing') === 0 ? 'selected' : '' ?>>Processing</option>
                                            <option value="Shipped" <?= strcasecmp($ord['status'], 'Shipped') === 0 ? 'selected' : '' ?>>Shipped</option>
                                            <option value="Delivered" <?= strcasecmp($ord['status'], 'Delivered') === 0 ? 'selected' : '' ?>>Delivered</option>
                                            <option value="Cancelled" <?= strcasecmp($ord['status'], 'Cancelled') === 0 ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-zinc-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-zinc-500 text-xs font-medium">
                                No customer orders placed yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Custom Toast Popup Container -->
<div id="toastNotification" class="fixed bottom-5 right-5 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none hidden">
    <div id="toastBox" class="bg-zinc-900 border border-emerald-500/40 text-emerald-400 px-4 py-3 rounded-2xl shadow-2xl flex items-center gap-3">
        <div id="toastIcon" class="w-7 h-7 rounded-full bg-emerald-500/20 flex items-center justify-center font-bold text-xs">✓</div>
        <div>
            <p id="toastMessage" class="text-xs font-bold text-zinc-200">Status Updated!</p>
        </div>
    </div>
</div>

<script>
    function handleStatusChange(orderId, newStatus) {
        if (!orderId || !newStatus) return;

        // ১. জিরো-ডিলেতে ইনস্ট্যান্ট ভিজ্যুয়াল ব্যাজ আপডেট (Text & Color)
        const badge = document.getElementById('badge-' + orderId);
        if (badge) {
            badge.innerText = newStatus;
            
            const s = newStatus.toLowerCase().trim();
            let colorClass = 'bg-amber-500/10 text-amber-400 border-amber-500/20'; // Default: Pending

            if (s === 'delivered' || s === 'completed') {
                colorClass = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
            } else if (s === 'shipped') {
                colorClass = 'bg-purple-500/10 text-purple-400 border-purple-500/20';
            } else if (s === 'processing') {
                colorClass = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
            } else if (s === 'cancelled') {
                colorClass = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
            }

            badge.className = 'px-2.5 py-1 rounded-lg border font-bold text-[10px] capitalize transition-all duration-300 ' + colorClass;
        }

        // ২. ব্যাকগ্রাউন্ডে সাইレント ডাটাবেজ আপডেট (AJAX Request)
        const formData = new FormData();
        formData.append('action', 'update_status_ajax');
        formData.append('order_id', orderId);
        formData.append('status', newStatus);

        fetch('orders.php', {
            method: 'POST',
            body: formData
        }).catch(err => console.error('Database Sync Error:', err));
    }
</script>

</main>
</body>
</html>