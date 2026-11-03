<?php
declare(strict_types=1);

$current_page = basename($_SERVER['PHP_SELF']);

// AJAX Request Handling (Database Save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status_ajax') {
    require_once __DIR__ . '/../config/db.php';
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json');

    $order_id = (int)($_POST['order_id'] ?? 0);
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
        $check_stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("i", $order_id);
            $check_stmt->execute();
            $order_info = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($order_info) {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $matched_status, $order_id);
                    if ($stmt->execute()) {
                        echo json_encode([
                            'success' => true,
                            'message' => "Order #$order_id status updated to {$matched_status}",
                            'new_status' => $matched_status
                        ]);
                        $stmt->close();
                        exit;
                    }
                }
            }
        }
    }

    echo json_encode(['success' => false, 'message' => 'Failed to update order status.']);
    exit;
}

require_once __DIR__ . '/header.php';

if (isset($_SESSION['toast_message'])) {
    unset($_SESSION['toast_message']);
}

// Fetch orders data
$ordersResult = $conn->query("
    SELECT o.*, 
           COALESCE(c.name, u.name, 'Guest Customer') AS customer_name,
           COALESCE(c.phone, o.phone, '') AS phone
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    LEFT JOIN customers c ON o.user_id = c.user_id 
    ORDER BY o.id DESC
");

$totalOrdersCount = ($ordersResult) ? $ordersResult->num_rows : 0;
?>

<div class="p-6 md:p-8 space-y-6 max-w-full overflow-hidden flex flex-col h-[calc(100vh-65px)]">

    <!-- Page Header Banner -->
    <div class="bg-gradient-to-r from-zinc-950 via-zinc-900 to-indigo-950 rounded-3xl p-5 md:p-6 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-4 border border-zinc-800/80 flex-shrink-0">
        <div class="space-y-1 text-center md:text-left relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 font-bold text-[10px] rounded-full uppercase tracking-wider">
                Store Fulfillment
            </span>
            <h1 class="text-xl md:text-3xl font-black tracking-tight">Customer Orders Management</h1>
            <p class="text-xs text-zinc-400">Update fulfillment stages in real-time. Changes sync directly with the database.</p>
        </div>
        <div class="px-4 py-2 rounded-2xl bg-zinc-900/90 border border-zinc-800 text-center shadow-inner relative z-10 flex-shrink-0">
            <span class="block text-[9px] text-zinc-400 uppercase tracking-widest font-bold">Total Orders</span>
            <span class="text-xl font-black text-indigo-400"><?= $totalOrdersCount ?></span>
        </div>
    </div>

    <!-- Orders Table Card with Inner Scroll ONLY -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-xl flex-1 flex flex-col min-h-0">
        <div class="p-3.5 border-b border-zinc-800 bg-zinc-950/40 flex items-center justify-between flex-shrink-0">
            <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                Live Orders Directory
            </h3>
            <span class="text-[11px] text-zinc-400">Instant Database Sync</span>
        </div>

        <!-- Inner Scrollable Container ONLY -->
        <div class="flex-1 overflow-y-auto min-h-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="sticky top-0 z-10 bg-zinc-950">
                        <tr class="border-b border-zinc-800 text-zinc-400 uppercase tracking-wider text-[11px] font-bold">
                            <th class="py-3.5 px-4 bg-zinc-950">Order ID</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Customer Name</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Shipping Info</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Amount & Payment</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Current Status</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Date & Time</th>
                            <th class="py-3.5 px-4 bg-zinc-950 text-right">Update Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300 font-medium">
                        <?php if ($ordersResult && $ordersResult->num_rows > 0): ?>
                            <?php while ($ord = $ordersResult->fetch_assoc()): ?>
                                <tr class="hover:bg-zinc-800/40 transition">
                                    <td class="py-3.5 px-4 font-bold text-emerald-400 font-mono">
                                        #ORD-<?= str_pad((string)$ord['id'], 5, '0', STR_PAD_LEFT) ?>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <p class="font-bold text-zinc-100"><?= e($ord['customer_name']) ?></p>
                                        <?php if (!empty($ord['phone'])): ?>
                                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ord['phone'])) ?>" 
                                               class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400 hover:text-emerald-300 hover:underline mt-0.5">
                                                <?= e($ord['phone']) ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <p class="text-zinc-300 truncate max-w-[200px]" title="<?= e($ord['shipping_address'] ?? 'N/A') ?>">
                                            <?= e($ord['shipping_address'] ?? 'N/A') ?>
                                        </p>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <p class="font-bold text-zinc-100">৳<?= number_format((float)$ord['total_amount'], 2) ?></p>
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-zinc-950 text-[9px] uppercase font-bold text-zinc-400 border border-zinc-800 mt-0.5">
                                            <?= e($ord['payment_method'] ?? 'COD') ?>
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <?php 
                                            $curStatus = strtolower(trim((string)($ord['status'] ?? 'pending')));
                                            $statusClass = match($curStatus) {
                                                'delivered', 'completed' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                                'shipped'               => 'bg-purple-500/15 text-purple-400 border-purple-500/30',
                                                'processing'            => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
                                                'cancelled'             => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
                                                default                 => 'bg-amber-500/15 text-amber-400 border-amber-500/30'
                                            };
                                        ?>
                                        <span id="badge-<?= $ord['id'] ?>" class="px-3.5 py-1.5 rounded-xl border font-bold text-[11px] capitalize inline-flex items-center gap-2 shadow-inner <?= $statusClass ?>">
                                            <span class="w-2 h-2 rounded-full bg-current"></span>
                                            <?= e($ord['status'] ?? 'Pending') ?>
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4 text-zinc-400 text-[11px] whitespace-nowrap">
                                        <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                                    </td>

                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="relative inline-block w-36">
                                            <select onchange="handleStatusChange(<?= (int)$ord['id'] ?>, this.value)" 
                                                    class="w-full appearance-none pl-3.5 pr-9 py-2 bg-zinc-950 hover:bg-zinc-900 border border-zinc-700/80 rounded-xl text-xs font-bold text-zinc-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition cursor-pointer shadow-md">
                                                <option value="Pending" <?= strcasecmp($ord['status'] ?? '', 'Pending') === 0 ? 'selected' : '' ?>>Pending</option>
                                                <option value="Processing" <?= strcasecmp($ord['status'] ?? '', 'Processing') === 0 ? 'selected' : '' ?>>Processing</option>
                                                <option value="Shipped" <?= strcasecmp($ord['status'] ?? '', 'Shipped') === 0 ? 'selected' : '' ?>>Shipped</option>
                                                <option value="Delivered" <?= strcasecmp($ord['status'] ?? '', 'Delivered') === 0 ? 'selected' : '' ?>>Delivered</option>
                                                <option value="Cancelled" <?= strcasecmp($ord['status'] ?? '', 'Cancelled') === 0 ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-16 text-center text-zinc-500 text-xs font-medium">
                                    No customer orders placed yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Toast Popup Container -->
<div id="toastNotification" class="fixed bottom-5 right-5 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none hidden">
    <div id="toastBox" class="bg-zinc-900 border border-indigo-500/40 text-indigo-400 px-4 py-3 rounded-2xl shadow-2xl flex items-center gap-3">
        <div>
            <p id="toastMessage" class="text-xs font-bold text-zinc-200">Status Updated & Saved!</p>
        </div>
    </div>
</div>

<script>
    function showToast(message) {
        const toast = document.getElementById('toastNotification');
        const msgElem = document.getElementById('toastMessage');
        
        if (toast && msgElem) {
            msgElem.innerText = message;
            toast.classList.remove('hidden');
            setTimeout(() => { toast.classList.remove('translate-y-20', 'opacity-0'); }, 10);

            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
                setTimeout(() => { toast.classList.add('hidden'); }, 300);
            }, 3000);
        }
    }

    function handleStatusChange(orderId, newStatus) {
        if (!orderId || !newStatus) return;

        const badge = document.getElementById('badge-' + orderId);
        if (badge) {
            badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-current"></span> ${newStatus}`;
            const s = newStatus.toLowerCase().trim();
            let colorClass = 'bg-amber-500/15 text-amber-400 border-amber-500/30';

            if (s === 'delivered' || s === 'completed') {
                colorClass = 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
            } else if (s === 'shipped') {
                colorClass = 'bg-purple-500/15 text-purple-400 border-purple-500/30';
            } else if (s === 'processing') {
                colorClass = 'bg-blue-500/15 text-blue-400 border-blue-500/30';
            } else if (s === 'cancelled') {
                colorClass = 'bg-rose-500/15 text-rose-400 border-rose-500/30';
            }

            badge.className = 'px-3.5 py-1.5 rounded-xl border font-bold text-[11px] capitalize inline-flex items-center gap-2 shadow-inner ' + colorClass;
        }

        const formData = new FormData();
        formData.append('action', 'update_status_ajax');
        formData.append('order_id', orderId);
        formData.append('status', newStatus);

        fetch('<?= $current_page ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
            } else {
                alert(data.message || 'Error saving status to database.');
            }
        })
        .catch(err => {
            console.error('Database Sync Error:', err);
            alert('Network error while updating status.');
        });
    }
</script>

</main>
</body>
</html>