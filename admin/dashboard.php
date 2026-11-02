<?php
declare(strict_types=1);

require_once __DIR__ . '/header.php';

// Fetch quick dashboard metrics
$totalProducts = 0;
$res = $conn->query("SELECT COUNT(*) as count FROM products");
if ($res) {
    $totalProducts = (int)$res->fetch_assoc()['count'];
}

$totalOrders = 0;
$res = $conn->query("SELECT COUNT(*) as count FROM orders");
if ($res) {
    $totalOrders = (int)$res->fetch_assoc()['count'];
}

$totalCustomers = 0;
$res = $conn->query("SELECT COUNT(*) as count FROM customers");
if ($res) {
    $totalCustomers = (int)$res->fetch_assoc()['count'];
}

$totalRevenue = 0.00;
$res = $conn->query("SELECT SUM(total_amount) as revenue FROM orders");
if ($res && $row = $res->fetch_assoc()) {
    $totalRevenue = (float)($row['revenue'] ?? 0);
}
?>

<div class="p-6 md:p-8 space-y-8">
    
    <!-- Page Header Banner -->
    <div class="bg-gradient-to-r from-zinc-950 via-zinc-900 to-emerald-950 rounded-3xl p-6 md:p-10 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6 border border-zinc-800/80">
        <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="space-y-2 text-center md:text-left relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 font-bold text-[11px] rounded-full uppercase tracking-wider">
                ⚡ Admin Overview Console
            </span>
            <h1 class="text-2xl md:text-4xl font-black tracking-tight">Welcome back, <?= e($userName) ?>!</h1>
            <p class="text-xs md:text-sm text-zinc-400">Monitor overall storefront metrics, customer orders, and store inventory in real-time.</p>
        </div>
        <div class="flex items-center gap-3 relative z-10">
            <a href="products.php" class="px-5 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold uppercase tracking-wider transition shadow-lg shadow-emerald-600/20">
                + Manage Products
            </a>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Revenue Card -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-5 shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Total Revenue</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold">৳</div>
            </div>
            <div>
                <h3 class="text-2xl font-black text-white">৳<?= number_format($totalRevenue, 2) ?></h3>
                <p class="text-[11px] text-emerald-400 mt-1 font-semibold">Store earnings to date</p>
            </div>
        </div>

        <!-- Orders Card -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-5 shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Total Orders</span>
                <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
            </div>
            <div>
                <h3 class="text-2xl font-black text-white"><?= $totalOrders ?></h3>
                <p class="text-[11px] text-indigo-400 mt-1 font-semibold">Customer purchases placed</p>
            </div>
        </div>

        <!-- Products Card -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-5 shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Total Products</span>
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <div>
                <h3 class="text-2xl font-black text-white"><?= $totalProducts ?></h3>
                <p class="text-[11px] text-amber-400 mt-1 font-semibold">Active catalog items</p>
            </div>
        </div>

        <!-- Customers Card -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-5 shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Total Customers</span>
                <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-400 border border-sky-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>
            <div>
                <h3 class="text-2xl font-black text-white"><?= $totalCustomers ?></h3>
                <p class="text-[11px] text-sky-400 mt-1 font-semibold">Registered user profiles</p>
            </div>
        </div>
    </div>

    <!-- Quick Management Shortcuts -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-6 shadow-xl space-y-4">
        <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            Quick Navigation Links
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a href="products.php" class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 hover:border-emerald-500/40 transition group space-y-2">
                <h4 class="text-xs font-bold text-zinc-200 group-hover:text-emerald-400 flex items-center justify-between">
                    <span>Manage Products</span> <span>→</span>
                </h4>
                <p class="text-[11px] text-zinc-400">Add, edit, or delete items from your e-commerce catalog.</p>
            </a>
            <a href="orders.php" class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 hover:border-emerald-500/40 transition group space-y-2">
                <h4 class="text-xs font-bold text-zinc-200 group-hover:text-emerald-400 flex items-center justify-between">
                    <span>Customer Orders</span> <span>→</span>
                </h4>
                <p class="text-[11px] text-zinc-400">Review incoming customer purchases and fulfill shipments.</p>
            </a>
            <a href="customer_msg.php" class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 hover:border-emerald-500/40 transition group space-y-2">
                <h4 class="text-xs font-bold text-zinc-200 group-hover:text-emerald-400 flex items-center justify-between">
                    <span>Customer Messages</span> <span>→</span>
                </h4>
                <p class="text-[11px] text-zinc-400">Check support queries and feedback sent by users.</p>
            </a>
        </div>
    </div>

</div>

</main>
</body>
</html>