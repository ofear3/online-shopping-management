
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">

<header class="bg-white shadow-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex items-center justify-between h-16">

            <a href="index.php" class="flex items-center gap-2 text-2xl font-bold text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.5 7h13L17 13M9 20a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                </svg>
                <span>Smart<span class="text-gray-800">Cart</span></span>
            </a>

            <nav class="hidden lg:flex items-center gap-7">
                <a href="index.php" class="text-gray-700 hover:text-blue-600 font-medium">Home</a>
                <a href="shop.php" class="text-gray-700 hover:text-blue-600 font-medium">Shop</a>
                <a href="categories.php" class="text-gray-700 hover:text-blue-600 font-medium">Categories</a>
                <a href="offers.php" class="text-gray-700 hover:text-blue-600 font-medium">Offers</a>
            </nav>

            <div class="hidden md:flex items-center gap-4">

                <form action="search.php" method="GET" class="flex">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search products..."
                        class="w-48 lg:w-60 px-4 py-2 border border-gray-300 rounded-l-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >

                    <button
                        type="submit"
                        class="px-4 bg-blue-600 text-white rounded-r-full hover:bg-blue-700">
                        Search
                    </button>
                </form>

                <a href="wishlist.php" class="text-gray-700 hover:text-red-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364 4.318 12.682a4.5 4.5 0 010-6.364z"/>
                    </svg>
                </a>

                <a href="cart.php" class="relative text-gray-700 hover:text-blue-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.5 7h13L17 13M9 20a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                    </svg>

                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">
                        0
                    </span>
                </a>

                <a href="login.php"
                   class="flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-full font-medium hover:bg-blue-700 transition">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/>
                    </svg>

                    Login
                </a>

            </div>

            <button id="menuBtn" class="lg:hidden text-gray-700">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

        </div>

        <div id="mobileMenu" class="hidden lg:hidden pb-5">

            <form action="search.php" method="GET" class="flex mt-3 mb-4">
                <input
                    type="text"
                    name="search"
                    placeholder="Search products..."
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-l-full focus:outline-none"
                >

                <button type="submit"
                        class="px-4 bg-blue-600 text-white rounded-r-full">
                    Search
                </button>
            </form>

            <div class="flex flex-col gap-2">

                <a href="index.php" class="py-2 text-gray-700 hover:text-blue-600">
                    Home
                </a>

                <a href="shop.php" class="py-2 text-gray-700 hover:text-blue-600">
                    Shop
                </a>

                <a href="categories.php" class="py-2 text-gray-700 hover:text-blue-600">
                    Categories
                </a>

                <a href="offers.php" class="py-2 text-gray-700 hover:text-blue-600">
                    Offers
                </a>

                <a href="wishlist.php" class="py-2 text-gray-700 hover:text-red-500">
                    Wishlist
                </a>

                <a href="cart.php" class="py-2 text-gray-700 hover:text-blue-600">
                    Cart
                </a>

                <a href="login.php"
                   class="mt-2 flex justify-center items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-full font-medium hover:bg-blue-700">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/>
                    </svg>

                    Login
                </a>

            </div>

        </div>

    </div>
</header>

<script>
    document.getElementById('menuBtn').addEventListener('click', function () {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    });
</script>

