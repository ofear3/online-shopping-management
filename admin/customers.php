<?php
declare(strict_types=1);

// হেডার এবং কমন কনফিগারেশন ফাইল ইনক্লুড করা
require_once __DIR__ . '/header.php';

// ট্যাব বা সর্টিং ফিল্টার রিসিভ করা (ডিফল্ট হলো 'highest_spent')
$sortBy = $_GET['sort'] ?? 'highest_spent';

// সর্টিং অনুযায়ী কুয়েরির ORDER BY অংশ নির্ধারণ করা
$orderByClause = "ORDER BY total_spent DESC, total_orders DESC"; // ডিফল্ট

if ($sortBy === 'highest_orders') {
    $orderByClause = "ORDER BY total_orders DESC, total_spent DESC";
} elseif ($sortBy === 'newest') {
    $orderByClause = "ORDER BY u.created_at DESC";
} elseif ($sortBy === 'oldest') {
    $orderByClause = "ORDER BY u.created_at ASC";
}

// ডাটাবেজ থেকে কাস্টমার ও তাদের অর্ডারের হিসাব ফেচ করা
$query = "
    SELECT 
        c.*, 
        u.email, 
        u.created_at as user_created_at,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_spent
    FROM customers c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN orders o ON c.user_id = o.user_id
    GROUP BY c.id
    {$orderByClause}
";

$result = $conn->query($query);
$customers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// সার্বিক টপ কাস্টমার বের করার জন্য (সর্বোচ্চ টাকার ভিত্তিতে)
$topQuery = "
    SELECT 
        c.*, 
        u.email, 
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_spent
    FROM customers c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN orders o ON c.user_id = o.user_id
    GROUP BY c.id
    ORDER BY total_spent DESC, total_orders DESC
    LIMIT 1
";
$topResult = $conn->query($topQuery);
$topCustomer = ($topResult && $topResult->num_rows > 0) ? $topResult->fetch_assoc() : null;

// মোট কাস্টমার কাউন্ট
$totalCustomersCount = count($customers);
?>

<div class="p-6 md:p-8 space-y-8">
    
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-zinc-100 tracking-tight">Customer Management</h1>
            <p class="text-xs text-zinc-400 mt-0.5">View registered customer profiles, order analytics, and top buyers.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="px-4 py-2 rounded-xl bg-zinc-900 border border-zinc-800/85 text-xs font-semibold text-zinc-300 shadow-sm flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Total Customers: <span class="text-emerald-400 font-bold"><?= $totalCustomersCount ?></span>
            </div>
        </div>
    </div>

    <!-- Professional Modern Tab Filter Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
        <a href="?sort=highest_spent" 
           class="px-4 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 whitespace-nowrap border <?= $sortBy === 'highest_spent' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 shadow-lg shadow-emerald-950/20' : 'bg-zinc-900 text-zinc-400 border-zinc-800/80 hover:bg-zinc-800/70 hover:text-zinc-200' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Highest Spent (টাকার পরিমাণ)
        </a>

        <a href="?sort=highest_orders" 
           class="px-4 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 whitespace-nowrap border <?= $sortBy === 'highest_orders' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 shadow-lg shadow-emerald-950/20' : 'bg-zinc-900 text-zinc-400 border-zinc-800/80 hover:bg-zinc-800/70 hover:text-zinc-200' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            Highest Orders (অর্ডারের সংখ্যা)
        </a>

        <a href="?sort=newest" 
           class="px-4 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 whitespace-nowrap border <?= $sortBy === 'newest' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 shadow-lg shadow-emerald-950/20' : 'bg-zinc-900 text-zinc-400 border-zinc-800/80 hover:bg-zinc-800/70 hover:text-zinc-200' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            Newest Customers
        </a>

        <a href="?sort=oldest" 
           class="px-4 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 whitespace-nowrap border <?= $sortBy === 'oldest' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 shadow-lg shadow-emerald-950/20' : 'bg-zinc-900 text-zinc-400 border-zinc-800/80 hover:bg-zinc-800/70 hover:text-zinc-200' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Oldest Customers
        </a>
    </div>

    <!-- Top Customer Spotlight Banner -->
    <?php if ($topCustomer && $topCustomer['total_orders'] > 0): ?>
    <div class="relative overflow-hidden bg-gradient-to-r from-emerald-950/40 via-zinc-900 to-zinc-900 border border-emerald-500/30 rounded-3xl p-6 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative z-10">
            <div class="flex items-center gap-5">
                <div class="relative w-16 h-16 rounded-2xl bg-zinc-800 border-2 border-emerald-500/40 overflow-hidden flex items-center justify-center font-bold text-zinc-200 text-xl shadow-lg flex-shrink-0">
                    <?php if (!empty($topCustomer['profile_image'])): ?>
                        <img src="../<?= e($topCustomer['profile_image']) ?>" alt="Top Customer" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($topCustomer['name'] ?? 'C', 0, 1)) ?>
                    <?php endif; ?>
                    <span class="absolute bottom-0 right-0 w-4 h-4 bg-emerald-500 border-2 border-zinc-900 rounded-full"></span>
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold tracking-wide uppercase border border-emerald-500/30">
                            👑 Top Buyer Spotlight
                        </span>
                    </div>
                    <h2 class="text-lg font-black text-zinc-100"><?= e($topCustomer['name']) ?> <span class="text-xs font-normal text-zinc-400">(<?= e($topCustomer['nickname']) ?>)</span></h2>
                    <p class="text-xs text-zinc-400"><?= e($topCustomer['email']) ?> • <?= e($topCustomer['phone'] ?? 'No Phone') ?></p>
                </div>
            </div>

            <div class="flex items-center gap-6 bg-zinc-950/60 border border-zinc-800/80 px-5 py-3.5 rounded-2xl">
                <div class="text-center">
                    <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Total Orders</p>
                    <p class="text-base font-black text-zinc-200"><?= $topCustomer['total_orders'] ?></p>
                </div>
                <div class="w-px h-8 bg-zinc-800"></div>
                <div class="text-center">
                    <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Total Spent</p>
                    <p class="text-base font-black text-emerald-400">৳<?= number_format((float)$topCustomer['total_spent'], 2) ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Customers Table Section -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-zinc-800 bg-zinc-950/40 flex items-center justify-between">
            <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider">All Customers Directory</h3>
            <span class="text-[11px] text-zinc-400">Active View: <strong class="text-emerald-400"><?= ucwords(str_replace('_', ' ', $sortBy)) ?></strong></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/60 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3.5 px-4">Customer Profile</th>
                        <th class="py-3.5 px-4">Contact Info</th>
                        <th class="py-3.5 px-4">Gender & Address</th>
                        <th class="py-3.5 px-4">Orders Placed</th>
                        <th class="py-3.5 px-4">Total Spent</th>
                        <th class="py-3.5 px-4">Registered Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-xs text-zinc-300">
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $index => $customer): ?>
                            <tr class="hover:bg-zinc-800/40 transition">
                                <!-- Profile & Name -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="relative w-10 h-10 rounded-xl bg-zinc-800 border border-zinc-700 overflow-hidden flex items-center justify-center font-bold text-zinc-200 flex-shrink-0">
                                            <?php if (!empty($customer['profile_image'])): ?>
                                                <img src="../<?= e($customer['profile_image']) ?>" alt="Avatar" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <?= strtoupper(substr($customer['name'] ?? 'U', 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <p class="font-bold text-zinc-100"><?= e($customer['name']) ?></p>
                                                <?php if ($sortBy === 'highest_spent' && $index === 0 && $customer['total_spent'] > 0): ?>
                                                    <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 text-[9px] font-bold border border-amber-500/20">Top</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-[10px] text-zinc-400">Nick: <?= e($customer['nickname'] ?? 'N/A') ?></p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Email & Phone -->
                                <td class="py-3.5 px-4">
                                    <p class="font-medium text-zinc-200"><?= e($customer['email']) ?></p>
                                    <p class="text-[11px] text-zinc-400"><?= e($customer['phone'] ?? 'Phone not added') ?></p>
                                </td>

                                <!-- Gender & Address -->
                                <td class="py-3.5 px-4">
                                    <p class="capitalize font-medium text-zinc-300"><?= e($customer['gender'] ?? 'N/A') ?></p>
                                    <p class="text-[11px] text-zinc-400 max-w-xs truncate" title="<?= e($customer['address']) ?>"><?= e($customer['address'] ?? 'No address provided') ?></p>
                                </td>

                                <!-- Total Orders -->
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700/60 font-bold text-zinc-200 text-xs">
                                        🛒 <?= $customer['total_orders'] ?> Orders
                                    </span>
                                </td>

                                <!-- Total Spent -->
                                <td class="py-3.5 px-4 font-bold text-emerald-400">
                                    ৳<?= number_format((float)$customer['total_spent'], 2) ?>
                                </td>

                                <!-- Registration Date -->
                                <td class="py-3.5 px-4 text-zinc-400">
                                    <?= date('d M Y, h:i A', strtotime($customer['user_created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-zinc-500 text-xs">No registered customers found in database.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</main>
</body>
</html>