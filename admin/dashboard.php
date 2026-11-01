<?php 
declare(strict_types=1);

// ১. ফাইল পাথ ঠিক করা (admin/dashboard.php এর জন্য)
require_once __DIR__ . '/header.php';

// ২. সেফ হেলপার ফাংশন (XSS Protection)
if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// ৩. হেলপার কুয়েরি ফাংশন
function dashboardQuery(mysqli $conn, string $sql): mysqli_result { 
    $result = $conn->query($sql);
    if (!$result) { 
        throw new RuntimeException($conn->error); 
    }
    return $result; 
}

// ৪. ডাটাবেজ থেকে এগ্রিগেট ডাটা আনা (Revenue, Orders, Pending, Products, Customers)
$revenueResult = dashboardQuery($conn, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status = 'Delivered'");
$revenueRow    = $revenueResult->fetch_assoc(); 
$totalRevenue  = (float) ($revenueRow['total'] ?? 0);

$orderResult   = dashboardQuery($conn, "SELECT COUNT(*) AS total FROM orders");
$orderRow      = $orderResult->fetch_assoc(); 
$totalOrders   = (int) ($orderRow['total'] ?? 0);

$pendingResult = dashboardQuery($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'");
$pendingRow    = $pendingResult->fetch_assoc(); 
$pendingOrders = (int) ($pendingRow['total'] ?? 0);

$productResult = dashboardQuery($conn, "SELECT COUNT(*) AS total FROM products");
$productRow    = $productResult->fetch_assoc(); 
$totalProducts = (int) ($productRow['total'] ?? 0);

// নতুন যোগ করা হলো: মোট রেজিস্টার্ড কাস্টমার সংখ্যা
$customerResult = dashboardQuery($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'admin' OR role IS NULL");
$customerRow    = $customerResult->fetch_assoc(); 
$totalCustomers = (int) ($customerRow['total'] ?? 0);

$recentOrders  = dashboardQuery(
    $conn, 
    "SELECT orders.id, orders.total_amount, orders.status, orders.created_at, users.name AS user_name 
     FROM orders 
     LEFT JOIN users ON orders.user_id = users.id 
     ORDER BY orders.id DESC LIMIT 5"
); 
?>

<div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-zinc-800/80"> 
        <div> 
            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-zinc-100">Dashboard Overview</h1>
            <p class="text-xs text-zinc-400 mt-0.5">System metrics, real-time analytics and storefront transactions</p> 
        </div>
        <div class="flex flex-wrap items-center gap-2.5"> 
            <a href="customers.php" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Customers
            </a>
            <a href="products.php" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Product
            </a>
            <a href="orders.php" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-zinc-900 hover:bg-zinc-800 text-zinc-200 border border-zinc-800 transition">Orders List</a> 
        </div> 
    </div>

    <!-- Cards Grid (5 Grid Responsive Layout) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Revenue -->
        <div class="p-4 sm:p-5 rounded-2xl bg-zinc-900/80 border border-zinc-800/80 space-y-2.5 shadow-lg"> 
            <div class="flex items-center justify-between text-zinc-400"> 
                <span class="text-[11px] font-semibold uppercase tracking-wider">Total Revenue</span>
                <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"> 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> 
                </span> 
            </div>
            <div> 
                <p class="text-xl sm:text-2xl font-black text-zinc-100 tracking-tight">৳ <?= number_format($totalRevenue, 2) ?></p>
                <p class="text-[10px] text-zinc-400 mt-0.5">Delivered orders balance</p> 
            </div> 
        </div>

        <!-- Total Customers (নতুন যুক্ত করা কার্ড) -->
        <div class="p-4 sm:p-5 rounded-2xl bg-zinc-900/80 border border-zinc-800/80 space-y-2.5 shadow-lg"> 
            <div class="flex items-center justify-between text-zinc-400"> 
                <span class="text-[11px] font-semibold uppercase tracking-wider">Customers</span>
                <span class="p-2 rounded-xl bg-sky-500/10 text-sky-400 border border-sky-500/20"> 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg> 
                </span> 
            </div>
            <div> 
                <p class="text-xl sm:text-2xl font-black text-zinc-100 tracking-tight"><?= $totalCustomers ?></p>
                <p class="text-[10px] text-zinc-400 mt-0.5">Registered members</p> 
            </div> 
        </div>

        <!-- Orders Count -->
        <div class="p-4 sm:p-5 rounded-2xl bg-zinc-900/80 border border-zinc-800/80 space-y-2.5 shadow-lg"> 
            <div class="flex items-center justify-between text-zinc-400"> 
                <span class="text-[11px] font-semibold uppercase tracking-wider">Total Orders</span>
                <span class="p-2 rounded-xl bg-zinc-800 text-zinc-300 border border-zinc-700/60"> 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg> 
                </span> 
            </div>
            <div> 
                <p class="text-xl sm:text-2xl font-black text-zinc-100 tracking-tight"><?= $totalOrders ?></p>
                <p class="text-[10px] text-zinc-400 mt-0.5">All time orders count</p> 
            </div> 
        </div>

        <!-- Pending Actions -->
        <div class="p-4 sm:p-5 rounded-2xl bg-zinc-900/80 border border-zinc-800/80 space-y-2.5 shadow-lg"> 
            <div class="flex items-center justify-between text-zinc-400"> 
                <span class="text-[11px] font-semibold uppercase tracking-wider">Pending Orders</span>
                <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20"> 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> 
                </span> 
            </div>
            <div> 
                <p class="text-xl sm:text-2xl font-black text-amber-300 tracking-tight"><?= $pendingOrders ?></p>
                <p class="text-[10px] text-zinc-400 mt-0.5">Requires approval/shipment</p> 
            </div> 
        </div>

        <!-- Active Products -->
        <div class="p-4 sm:p-5 rounded-2xl bg-zinc-900/80 border border-zinc-800/80 space-y-2.5 shadow-lg"> 
            <div class="flex items-center justify-between text-zinc-400"> 
                <span class="text-[11px] font-semibold uppercase tracking-wider">Products</span>
                <span class="p-2 rounded-xl bg-zinc-800 text-zinc-300 border border-zinc-700/60"> 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg> 
                </span> 
            </div>
            <div> 
                <p class="text-xl sm:text-2xl font-black text-zinc-100 tracking-tight"><?= $totalProducts ?></p>
                <p class="text-[10px] text-zinc-400 mt-0.5">Listed in inventory</p> 
            </div> 
        </div>
    </div>

    <!-- Table Section -->
    <div class="rounded-2xl bg-zinc-900/80 border border-zinc-800/80 p-5 space-y-4 shadow-xl">
        <div class="flex items-center justify-between"> 
            <div> 
                <h2 class="text-sm sm:text-base font-bold text-zinc-100">Recent Customer Orders</h2>
                <p class="text-[11px] text-zinc-400">Latest 5 sales entries and fulfillment status</p> 
            </div>
            <a href="orders.php" class="text-xs text-emerald-400 hover:text-emerald-300 font-semibold transition flex items-center gap-1">View All Orders →</a> 
        </div>
        <div class="overflow-x-auto"> 
            <table class="w-full text-left text-xs text-zinc-300 whitespace-nowrap">
                <thead class="bg-zinc-950/70 text-[10px] uppercase tracking-wider text-zinc-400 border-b border-zinc-800"> 
                    <tr> 
                        <th class="py-3 px-4">Order ID</th> 
                        <th class="py-3 px-4">Customer</th> 
                        <th class="py-3 px-4">Amount</th> 
                        <th class="py-3 px-4">Status</th> 
                        <th class="py-3 px-4">Date</th> 
                    </tr> 
                </thead>
                <tbody class="divide-y divide-zinc-800/60">
                    <?php if ($recentOrders->num_rows > 0): ?>
                        <?php while ($order = $recentOrders->fetch_assoc()): ?>
                            <?php 
                            $status = (string) ($order['status'] ?? ''); 
                            $normalizedStatus = strtolower($status);
                            
                            $badgeClass = match ($normalizedStatus) { 
                                'delivered'          => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20', 
                                'pending'            => 'bg-amber-500/10 text-amber-400 border-amber-500/20', 
                                'processing'         => 'bg-sky-500/10 text-sky-400 border-sky-500/20', 
                                'cancelled', 'canceled' => 'bg-rose-500/10 text-rose-400 border-rose-500/20', 
                                default              => 'bg-zinc-800 text-zinc-300 border-zinc-700' 
                            }; 
                            ?>
                            <tr class="hover:bg-zinc-800/40 transition">
                                <td class="py-3.5 px-4 font-bold text-zinc-200">#<?= (int) $order['id'] ?></td>
                                <td class="py-3.5 px-4 font-medium text-zinc-300"><?= e($order['user_name'] ?? 'Unknown') ?></td>
                                <td class="py-3.5 px-4 font-bold text-emerald-400">৳ <?= number_format((float) ($order['total_amount'] ?? 0), 2) ?></td>
                                <td class="py-3.5 px-4"> 
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border <?= $badgeClass ?>">
                                        <?= e($status ?: 'Unknown') ?> 
                                    </span> 
                                </td>
                                <td class="py-3.5 px-4 text-zinc-400 text-[11px]"> 
                                    <?php 
                                    $createdAt = $order['created_at'] ?? null; 
                                    echo $createdAt ? e(date('M d, Y, h:i A', strtotime($createdAt))) : 'N/A'; 
                                    ?> 
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr> 
                            <td colspan="5" class="py-10 text-center text-zinc-500 text-xs font-medium">No recent orders found in the system.</td> 
                        </tr>
                    <?php endif; ?>
                </tbody> 
            </table> 
        </div> 
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>