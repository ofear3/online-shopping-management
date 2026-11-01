<?php declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auth Protection Check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Database Connection Check
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is unavailable. Please check config/db.php.');
}

if ($conn->connect_errno) {
    http_response_code(500);
    exit('Database connection failed: ' . $conn->connect_error);
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$userName = $_SESSION['username'] ?? 'Admin';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OnlineShop Admin Management Console">
    <title>Admin Portal - OnlineShop</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: #09090b;
        }

        ::-webkit-scrollbar-thumb {
            background: #27272a;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #3f3f46;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: #27272a #09090b;
        }
    </style>
</head>

<body class="min-h-full bg-zinc-950 text-zinc-200 font-sans antialiased flex flex-col md:flex-row pb-16 md:pb-0">

    <!-- Top Right Toast Popup Notification -->
    <?php if (isset($_SESSION['toast_message'])): ?>
        <div id="toastNotification"
            class="fixed top-5 right-5 z-50 flex items-center gap-3 bg-zinc-900 border border-emerald-500/40 text-zinc-100 px-4 py-3 rounded-2xl shadow-2xl backdrop-blur-md transform transition-all duration-300 translate-y-[-20px] opacity-0">
            <div
                class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div>
                <p class="text-base font-bold text-amber-400">System Alert</p>
                <p class="text-[16px] text-emerald-400 font-semibold"><?= e($_SESSION['toast_message']) ?></p>
            </div>
            <button type="button" onclick="hideToast()"
                class="ml-2 text-zinc-500 hover:text-zinc-300 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <?php unset($_SESSION['toast_message']); ?>
    <?php endif; ?>

    <!-- Custom Logout Modal -->
    <div id="logoutModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden transition-all duration-200">
        <div class="bg-zinc-900 border border-zinc-800/80 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4 transform transition-all scale-95 opacity-0"
            id="logoutModalCard">
            <div class="flex items-center gap-3.5">
                <div
                    class="w-11 h-11 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-zinc-100 tracking-tight">Confirm Sign Out</h3>
                    <p class="text-xs text-zinc-400 mt-0.5">Are you sure you want to log out!</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-zinc-800/60">
                <button type="button" onclick="closeLogoutModal()"
                    class="px-4 py-2 text-xs font-medium text-zinc-300 bg-zinc-800 hover:bg-zinc-700/80 rounded-xl transition border border-zinc-700/60 cursor-pointer">
                    Cancel
                </button>
                <a href="logout.php"
                    class="px-4 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-500 rounded-xl transition shadow-lg shadow-rose-600/20">
                    Yes, Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Header -->
    <header class="md:hidden sticky top-0 z-40 bg-zinc-900/90 backdrop-blur-md border-b border-zinc-800/80 px-4 py-3">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2.5 min-w-0">
                <span
                    class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold flex items-center justify-center text-xs flex-shrink-0 shadow-sm">
                    OS </span>
                <span class="font-bold text-zinc-100 text-sm tracking-tight"> Admin </span>
            </div>

            <div class="flex items-center gap-3 min-w-0">
                <span class="text-xs text-zinc-400 truncate"> Hi, <strong
                        class="text-zinc-200 font-semibold"><?= e($userName) ?></strong> </span>
                <button type="button" onclick="openLogoutModal()"
                    class="text-[11px] text-rose-400 font-semibold px-2.5 py-1 rounded-lg bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 transition cursor-pointer">
                    Logout
                </button>
            </div>
        </div>
    </header>

    <!-- Desktop Sidebar -->
    <aside
        class="hidden md:flex flex-col w-64 bg-zinc-900/90 border-r border-zinc-800/80 h-screen sticky top-0 z-30 flex-shrink-0">

        <div class="p-5 border-b border-zinc-800/80 flex items-center gap-3">
            <div
                class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold flex items-center justify-center text-sm shadow-sm">
                OS </div>
            <div>
                <h2 class="text-sm font-bold text-zinc-100 tracking-tight"> OnlineShop </h2>
                <p class="text-[11px] text-zinc-400 font-medium"> Management Console </p>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1 overflow-y-auto" aria-label="Admin navigation">
            <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500"> Main Menu </div>

            <a href="dashboard.php"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition duration-150 <?= $currentPage === 'dashboard.php' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm' : 'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200' ?>"
                <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2v-2z" />
                </svg>
                <span>Dashboard Overview</span>
            </a>

            <div class="px-3 pt-4 pb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500"> Management </div>

            <!-- Products Management Link -->
            <a href="products.php"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition duration-150 <?= $currentPage === 'products.php' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm' : 'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200' ?>"
                <?= $currentPage === 'products.php' ? 'aria-current="page"' : '' ?>>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span>Product Manage</span>
            </a>

            <!-- Stock & Inventory Link -->
            <a href="inventory.php"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition duration-150 <?= $currentPage === 'inventory.php' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm' : 'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200' ?>"
                <?= $currentPage === 'inventory.php' ? 'aria-current="page"' : '' ?>>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span>Inventory & Stock</span>
            </a>

            <a href="orders.php"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition duration-150 <?= $currentPage === 'orders.php' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm' : 'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200' ?>"
                <?= $currentPage === 'orders.php' ? 'aria-current="page"' : '' ?>>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span>Customer Orders</span>
            </a>

            <!-- Customers Management Link (নতুন যুক্ত করা হয়েছে) -->
            <a href="customers.php"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition duration-150 <?= $currentPage === 'customers.php' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm' : 'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200' ?>"
                <?= $currentPage === 'customers.php' ? 'aria-current="page"' : '' ?>>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Customer List</span>
            </a>

            <div class="px-3 pt-4 pb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500"> Shortcuts </div>

            <a href="../index.php" target="_blank" rel="noopener noreferrer"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200 transition duration-150">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                <span>Visit Main Site ↗</span>
            </a>
        </nav>

        <div class="p-3 border-t border-zinc-800/80 bg-zinc-900/40">
            <div class="flex items-center justify-between p-2 rounded-xl bg-zinc-950 border border-zinc-800/80">
                <div class="flex items-center gap-2.5 overflow-hidden">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0 animate-pulse"></span>
                    <div class="truncate">
                        <p class="text-xs font-bold text-zinc-200 truncate"><?= e($userName) ?></p>
                        <p class="text-[10px] text-zinc-400 truncate">System Admin</p>
                    </div>
                </div>

                <button type="button" onclick="openLogoutModal()" title="Sign Out" aria-label="Sign Out"
                    class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </div>
        </div>
    </aside>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-zinc-900/95 backdrop-blur-md border-t border-zinc-800 z-50 flex items-center justify-around py-2 px-1"
        aria-label="Mobile navigation">
        <a href="dashboard.php"
            class="flex flex-col items-center gap-1 text-[10px] font-medium <?= $currentPage === 'dashboard.php' ? 'text-emerald-400 font-semibold' : 'text-zinc-400 hover:text-zinc-200' ?>"
            <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2v-2z" />
            </svg>
            <span>Overview</span>
        </a>

        <a href="products.php"
            class="flex flex-col items-center gap-1 text-[10px] font-medium <?= $currentPage === 'products.php' ? 'text-emerald-400 font-semibold' : 'text-zinc-400 hover:text-zinc-200' ?>"
            <?= $currentPage === 'products.php' ? 'aria-current="page"' : '' ?>>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span>Products</span>
        </a>

        <a href="orders.php"
            class="flex flex-col items-center gap-1 text-[10px] font-medium <?= $currentPage === 'orders.php' ? 'text-emerald-400 font-semibold' : 'text-zinc-400 hover:text-zinc-200' ?>"
            <?= $currentPage === 'orders.php' ? 'aria-current="page"' : '' ?>>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            <span>Orders</span>
        </a>

        <a href="customers.php"
            class="flex flex-col items-center gap-1 text-[10px] font-medium <?= $currentPage === 'customers.php' ? 'text-emerald-400 font-semibold' : 'text-zinc-400 hover:text-zinc-200' ?>"
            <?= $currentPage === 'customers.php' ? 'aria-current="page"' : '' ?>>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span>Customers</span>
        </a>
    </nav>

    <!-- JavaScript Handlers -->
    <script>
        // Toast popup auto-hide animation logic
        document.addEventListener('DOMContentLoaded', () => {
            const toast = document.getElementById('toastNotification');
            if (toast) {
                setTimeout(() => {
                    toast.classList.remove('translate-y-[-20px]', 'opacity-0');
                    toast.classList.add('translate-y-0', 'opacity-100');
                }, 100);

                setTimeout(() => {
                    hideToast();
                }, 3500);
            }
        });

        function hideToast() {
            const toast = document.getElementById('toastNotification');
            if (toast) {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-[-20px]', 'opacity-0');
                setTimeout(() => toast.remove(), 3000);
            }
        }

        // Modal popup logic
        function openLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const card = document.getElementById('logoutModalCard');

            modal.classList.remove('hidden');
            setTimeout(() => {
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const card = document.getElementById('logoutModalCard');

            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
            }, 150);
        }

        document.getElementById('logoutModal')?.addEventListener('click', function (e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeLogoutModal();
            }
        });
    </script>

    <main class="flex-1 min-w-0 overflow-y-auto min-h-screen">