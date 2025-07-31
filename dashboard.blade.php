<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Student Dashboard - Canteen Online Ordering System</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Iconify for icons -->
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#10B981',
                        accent: '#F59E0B',
                        light: '#F3F4F6',
                        dark: '#1F2937',
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        .message-popup {
            transition: opacity 0.5s ease-in-out, transform 0.3s ease-out;
            opacity: 1;
            transform: translateY(0);
        }
        .message-popup.opacity-0 {
            opacity: 0;
            transform: translateY(10px);
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        /* Ensure ratings modal displays properly */
        #ratings-modal {
            display: none;
        }
        #ratings-modal.active {
            display: flex !important;
            z-index: 9999 !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex">
        <!-- Sidebar -->
        @include('includes.student-sidebar')
        </div>

            <div class="absolute bottom-4 w-full px-5">
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                    <button type="submit" class="flex items-center gap-3 px-4 py-3 w-full rounded-lg hover:bg-white/10 transition-all duration-200">
                        <iconify-icon icon="mdi:logout" width="24" height="24"></iconify-icon>
                        <span>Log Out</span>
                    </button>
            </form>
        </div>
    </div>

        <!-- Main Content -->
        <div class="ml-0 md:ml-64 w-full min-h-screen transition-all duration-300">
            <!-- Error Message (if any) -->
            @if(isset($error))
            <div class="p-5">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl shadow-sm" role="alert">
                    <p class="font-bold">Error</p>
                    <p>{{ $error }}</p>
                </div>
            </div>
            @endif
            
            <!-- Top Navigation Bar -->
            <div class="p-5">
                <div class="bg-white rounded-xl shadow-sm p-4 flex justify-between items-center">
                    <button id="sidebar-toggle" class="md:hidden text-primary text-2xl">
                        <iconify-icon icon="mdi:menu" width="28" height="28"></iconify-icon>
                    </button>
                    
                    <div class="relative flex-1 mx-4 max-w-2xl">
                        <input type="text" id="search-input" placeholder="What you want to eat?" class="w-full bg-gray-100 rounded-full py-2 px-5 focus:outline-none focus:ring-2 focus:ring-primary">
                        <button class="absolute right-1 top-1 bg-primary hover:bg-blue-600 text-white rounded-full px-5 py-1 transition duration-200" onclick="searchItems()">
                            Search
                        </button>
            </div>

                    <div class="flex items-center gap-3">
                        <!-- Notification bell -->
                        <div class="relative">
                            <a href="#" onclick="toggleNotificationPopup()" class="relative p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition duration-200 hover:scale-110 group">
                                <iconify-icon icon="mdi:bell" class="text-primary group-hover:text-accent" width="24" height="24"></iconify-icon>
                                @php
                                    $notificationCount = DB::table('notifications')
                                        ->where('notifiable_id', auth()->user()->user_id)
                                        ->where('notifiable_type', 'App\\Models\\User')
                                        ->whereNull('read_at')
                                        ->count();
                                @endphp
                                @if($notificationCount > 0)
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-semibold shadow-sm">{{ $notificationCount }}</span>
                                @endif
                            </a>
                            
                            <!-- Notification popup -->
                            <div id="notification-popup" class="hidden fixed top-24 right-16 bg-white rounded-xl shadow-xl p-4 z-20 w-[90%] max-w-sm sm:w-96 max-h-[80vh] overflow-y-auto scrollbar-hide">
                                <div class="flex justify-between items-center border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-lg">Notifications</h4>
                                    <div class="flex gap-2">
                                        <a href="{{ route('notifications.clearTest') }}" class="text-xs text-red-500 hover:underline">Clear test</a>
                                        <a href="{{ route('notifications.markAllAsRead') }}" class="text-xs text-primary hover:underline">Mark all as read</a>
                                    </div>
                                </div>
                                
                                @php
                                    $notifications = DB::table('notifications')
                                        ->where('notifiable_id', auth()->user()->user_id)
                                        ->where('notifiable_type', 'App\\Models\\User')
                                        ->orderBy('created_at', 'desc')
                                        ->get();
                                @endphp
                                
                                @if(count($notifications) > 0)
                                    <div class="divide-y">
                                        @foreach($notifications as $notification)
                                            @php 
                                                $data = json_decode($notification->data);
                                            @endphp
                                            <div class="py-3 px-1 {{ $notification->read_at ? 'opacity-60' : '' }}">
                                                <div class="flex items-start gap-3">
                                                    <div class="{{ $data->type == 'ready_for_pickup' ? 'text-blue-500' : ($data->type == 'order_pending' ? 'text-yellow-500' : ($data->type == 'order_cancelled' ? 'text-red-500' : 'text-red-500')) }} mt-1">
                                                        <iconify-icon icon="{{ $data->type == 'ready_for_pickup' ? 'mdi:food-takeout-box' : ($data->type == 'order_pending' ? 'mdi:alert-circle' : ($data->type == 'order_cancelled' ? 'mdi:close-circle' : 'mdi:alert-circle')) }}" width="24" height="24"></iconify-icon>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-sm mb-1">{{ $data->message }}</p>
                                                        <div class="flex justify-between items-center">
                                                            <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</p>
                                                            @if(!$notification->read_at)
                                                                <a href="{{ route('notifications.markAsRead', $notification->id) }}" class="text-xs text-primary hover:underline">Mark as read</a>
                                                            @endif
                                                        </div>
                                                        <div class="mt-2">
                                                            <a href="{{ route('orders.show', $data->order_id) }}" class="text-xs text-primary hover:underline">View Order Details</a>
                                                        </div>
                                                        @if($data->type == 'ready_for_pickup')
                                                            <div class="mt-2 bg-blue-100 rounded-lg p-2">
                                                                <p class="text-xs text-blue-800 font-semibold">Your order is ready for pickup!</p>
                                                                <p class="text-xs text-blue-800">Please proceed to the counter to collect your order.</p>
                                                            </div>
                                                        @endif
                                                        @if($data->type == 'order_pending')
                                                            <div class="mt-2 bg-yellow-100 rounded-lg p-2">
                                                                <p class="text-xs text-yellow-800 font-semibold">Pay at counter: <span class="pending-countdown" data-order-id="{{ $data->order_id }}" data-created-at="{{ $notification->created_at }}">
                                                                    @php
                                                                        // Calculate seconds remaining for pending orders (15 minutes)
                                                                        $startTime = \Carbon\Carbon::parse($notification->created_at);
                                                                        $now = now();
                                                                        
                                                                        $startTimestamp = $startTime->timestamp;
                                                                        $nowTimestamp = $now->timestamp;
                                                                        $elapsedSeconds = $nowTimestamp - $startTimestamp;
                                                                        
                                                                        $remainingSeconds = max(0, 900 - $elapsedSeconds); // 15 minutes = 900 seconds
                                                                        
                                                                        $minutes = floor($remainingSeconds / 60);
                                                                        $seconds = $remainingSeconds % 60;
                                                                        
                                                                        echo sprintf('%02d:%02d', $minutes, $seconds);
                                                                    @endphp
                                                                </span></p>
                                                                <p class="text-xs text-yellow-800">Unpaid orders will be cancelled.</p>
                                                            </div>
                                                        @endif
                                                        @if($data->type == 'order_cancelled')
                                                            <div class="mt-2 bg-red-100 rounded-lg p-2">
                                                                <p class="text-xs text-red-800 font-semibold">Order Cancelled</p>
                                                                <p class="text-xs text-red-800">Your order has been cancelled</p>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="py-6 text-center text-gray-500">
                                        <p>No notifications yet</p>
                                    </div>
                                @endif
                                
                                <a class="absolute top-2 right-2 cursor-pointer text-gray-500 hover:text-gray-700" onclick="closeNotifications()">
                                    <iconify-icon icon="mdi:close" width="20" height="20"></iconify-icon>
                                </a>
                            </div>
                        </div>
                        
                      
                        
                        <!-- Cart icon -->
                        <a href="#" onclick="toggleCartPopup()" class="relative p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition duration-200 hover:scale-110 group">
                            <iconify-icon icon="mdi:cart" class="text-primary group-hover:text-accent" width="24" height="24"></iconify-icon>
                            <span id="cart-count" class="absolute -top-1 -right-1 bg-accent text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-semibold shadow-sm">0</span>
                        </a>
                       
                    </div>
            </div>
        </div>

        <!-- Shopping cart section -->
            <div id="cart-popup" class="hidden fixed top-24 right-5 bg-white rounded-xl shadow-xl p-5 z-20 w-[90%] max-w-xs sm:w-80 max-h-[80vh] overflow-y-auto scrollbar-hide">
                <h4 class="font-semibold text-lg mb-3">Shopping Cart</h4>
                <table id="cart-items" class="w-full text-sm">
                <thead>
                        <tr class="bg-primary text-white">
                            <th class="py-2 px-2 text-left rounded-tl-lg">Item</th>
                            <th class="py-2 px-2 text-center">#</th>
                            <th class="py-2 px-2 text-right">Price(₱)</th>
                            <th class="py-2 px-2 text-right rounded-tr-lg">Total(₱)</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
                <div id="empty-cart-message" class="text-center py-4 text-gray-500">
                    Your cart is empty
                </div>
                <div class="flex justify-between items-center mt-3 font-semibold border-t pt-2">
                    <span>Total(₱)</span>
                    <span id="cart-total">0.00</span>
                </div>
                <div id="cart-actions" class="hidden">
                    <button onclick="clearCart()" class="mt-3 w-full bg-red-500 hover:bg-red-600 text-white py-2 rounded-lg transition duration-200 mb-2">
                        Clear Cart
                    </button>
                    <button onclick="checkout()" class="w-full bg-primary hover:bg-blue-600 text-white py-2 rounded-lg transition duration-200">
                        Checkout
                    </button>
                </div>
                <a class="absolute top-4 right-4 cursor-pointer text-gray-500 hover:text-gray-700" onclick="closeCart()">
                    <iconify-icon icon="mdi:close-circle" width="24" height="24"></iconify-icon>
            </a>
        </div>

            <!-- Recommendations Section -->
            <div class="px-3 sm:px-5 py-2">
                <div class="bg-gradient-to-r from-primary to-blue-500 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-white">Recommendations</h2>
                        <div class="text-white text-2xl">
                            <iconify-icon icon="mdi:chevron-left-circle" class="back cursor-pointer hover:text-amber-200 transition" width="28" height="28"></iconify-icon>
                            <iconify-icon icon="mdi:chevron-right-circle" class="next cursor-pointer hover:text-amber-200 transition" width="28" height="28"></iconify-icon>
                </div>
            </div>

                    <div class="highlight-wrapper flex gap-5 overflow-x-auto pb-2 scrollbar-hide">
                        <div class="bg-white rounded-xl p-3 flex items-center gap-3 min-w-56 transform hover:scale-105 transition duration-300 hover:shadow-md cursor-pointer">
                            <img class="w-16 h-16 rounded-lg object-cover" src="https://source.unsplash.com/100x100/?appetizer" alt="Appetizer">
                            <div>
                                <h4 class="font-medium text-primary">Appetizer</h4>
                                <p class="text-sm text-gray-600">₱50</p>
                    </div>
                </div>
                        <div class="bg-white rounded-xl p-3 flex items-center gap-3 min-w-56 transform hover:scale-105 transition duration-300 hover:shadow-md cursor-pointer">
                            <img class="w-16 h-16 rounded-lg object-cover" src="https://source.unsplash.com/100x100/?drinks" alt="Drinks">
                            <div>
                                <h4 class="font-medium text-primary">Drinks</h4>
                                <p class="text-sm text-gray-600">₱50</p>
                    </div>
                </div>
                        <div class="bg-white rounded-xl p-3 flex items-center gap-3 min-w-56 transform hover:scale-105 transition duration-300 hover:shadow-md cursor-pointer">
                            <img class="w-16 h-16 rounded-lg object-cover" src="https://source.unsplash.com/100x100/?dessert" alt="Sweets">
                            <div>
                                <h4 class="font-medium text-primary">Sweets</h4>
                                <p class="text-sm text-gray-600">₱50</p>
                    </div>
                </div>
                        <div class="bg-white rounded-xl p-3 flex items-center gap-3 min-w-56 transform hover:scale-105 transition duration-300 hover:shadow-md cursor-pointer">
                            <img class="w-16 h-16 rounded-lg object-cover" src="https://source.unsplash.com/100x100/?meal" alt="Meal">
                            <div>
                                <h4 class="font-medium text-primary">Meal</h4>
                                <p class="text-sm text-gray-600">₱50</p>
                    </div>
                    </div>
                </div>
            </div>
        </div>

     
            <div class="px-3 sm:px-5 py-3">
                <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-dark">Available Stalls</h2>
                        <a href="#" class="text-primary hover:text-accent text-sm flex items-center gap-1">
                            View All <iconify-icon icon="mdi:chevron-right" width="16" height="16"></iconify-icon>
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @if(isset($sellers) && $sellers->count() > 0)
                            @foreach($sellers as $seller)
                            <div class="bg-gray-50 rounded-xl p-4 hover:shadow-md transition duration-300 border border-gray-100">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="bg-primary bg-opacity-10 p-3 rounded-lg">
                                        <iconify-icon icon="mdi:store" class="text-primary" width="24" height="24"></iconify-icon>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-lg">{{ $seller->stall_name }}</h3>
                                        <p class="text-sm text-gray-500">Stall #{{ $seller->seller_id }}</p>
                                    </div>
                                </div>
                                <div class="border-t border-gray-100 pt-3">
                                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                                        <iconify-icon icon="mdi:map-marker" class="text-primary" width="18" height="18"></iconify-icon>
                                        <span>{{ $seller->stall_location }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-500">
                                        <iconify-icon icon="mdi:phone" class="text-primary" width="18" height="18"></iconify-icon>
                                        <span>{{ $seller->contact_number }}</span>
                                    </div>
                                    <button onclick="filterMenuBySeller({{ $seller->seller_id }})" class="mt-3 inline-block text-primary hover:text-accent text-sm">View Menu →</button>
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="col-span-full text-center py-6">
                                <p class="text-gray-500">No stalls available at the moment.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Main menu / order -->
            <div class="p-3 sm:p-5">
                <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm">
                    <!-- Category Filter -->
                    <div class="flex items-center gap-4 mb-6">
                        <div>
                            <h2 class="text-lg font-semibold text-dark">Menu<br>Category</h2>
                            <div class="text-primary text-2xl">
                                <iconify-icon icon="mdi:chevron-left-circle" class="back-menu cursor-pointer hover:text-accent transition" width="28" height="28"></iconify-icon>
                                <iconify-icon icon="mdi:chevron-right-circle" class="next-menu cursor-pointer hover:text-accent transition" width="28" height="28"></iconify-icon>
                            </div>
                        </div>
                        
                        <div class="filter-wrapper flex overflow-x-auto gap-4 pb-2 scrollbar-hide">
                            <div class="group flex flex-col items-center min-w-[85px] cursor-pointer" data-category="all">
                                <div class="bg-accent text-white group-hover:bg-accent w-16 h-16 rounded-xl flex items-center justify-center mb-2 transition duration-300 shadow-md group-hover:shadow-lg">
                                    <iconify-icon icon="fluent-emoji:fork-and-knife-with-plate" width="32" height="32"></iconify-icon>
                    </div>
                                <p class="text-sm font-medium">All Menu</p>
                            </div>
                            
                            @if(isset($categories) && $categories->count() > 0)
                                @foreach($categories as $category)
                                <div class="group flex flex-col items-center min-w-[85px] cursor-pointer" data-category="{{ strtolower($category->name) }}">
                                    <div class="bg-primary text-white group-hover:bg-accent w-16 h-16 rounded-xl flex items-center justify-center mb-2 transition duration-300 shadow-md group-hover:shadow-lg">
                                        <iconify-icon icon="noto:fork-and-knife" width="32" height="32"></iconify-icon>
                        </div>
                                    <p class="text-sm font-medium">{{ $category->name }}</p>
                    </div>
                                @endforeach
                            @endif
                            
                            <!-- Uncategorized menu items -->
                            <div class="group flex flex-col items-center min-w-[85px] cursor-pointer" data-category="uncategorized">
                                <div class="bg-primary text-white group-hover:bg-accent w-16 h-16 rounded-xl flex items-center justify-center mb-2 transition duration-300 shadow-md group-hover:shadow-lg">
                                    <iconify-icon icon="noto:card-file-box" width="32" height="32"></iconify-icon>
                            </div>
                                <p class="text-sm font-medium">Uncategorized</p>
                        </div>
                    </div>
                            </div>
                    
                    <hr class="border-gray-200 mb-6">

                    <!-- Food Items -->
                    <div>
                        <h2 class="text-lg font-semibold text-dark mb-4">Choose Order</h2>
                        <div id="current-seller-info" class="hidden mb-4 p-3 bg-primary bg-opacity-10 rounded-lg">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <iconify-icon icon="mdi:store" class="text-primary" width="24" height="24"></iconify-icon>
                                    <span id="current-seller-name" class="font-medium">All Stalls</span>
                                </div>
                                <button onclick="showAllMenuItems()" class="text-primary hover:text-accent text-sm">
                                    Show All Stalls
                                </button>
                            </div>
                        </div>
                        <div id="no-items-message" class="hidden p-4 bg-gray-100 rounded-lg mb-4 text-center">
                            <p class="text-gray-500">No items available for this category.</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                            @if(isset($menuItems) && $menuItems->count() > 0)
                                @foreach($menuItems as $item)
                                <div class="food-item bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition duration-300 transform hover:-translate-y-1" 
                                     data-category="{{ strtolower($item->category_name ?? 'uncategorized') }}"
                                     data-seller-id="{{ $item->seller_id }}">
                                    <img class="h-48 w-full object-cover transition duration-500 hover:scale-105" 
                                         src="{{ $item->image_url ?? 'https://source.unsplash.com/300x200/?food' }}" 
                                         alt="{{ $item->item_name }}">
                                    <div class="p-4">
                                        <div class="flex justify-between">
                                            <div>
                                                <h4 class="font-medium text-lg">{{ $item->item_name }}</h4>
                                                <p class="text-sm text-gray-500 mt-1">{{ $item->description }}</p>
                                                <p class="text-accent font-semibold text-lg mt-2">₱{{ number_format($item->price, 2) }}</p>
                        </div>
                                            <div class="flex flex-col items-end">
                                                <button type="button" class="review-btn text-xl text-amber-400 hover:text-amber-500 transition-colors cursor-pointer" data-item-id="{{ $item->item_id }}" data-item-name="{{ addslashes($item->item_name) }}">
                                                    <iconify-icon icon="mdi:star" width="24" height="24"></iconify-icon>
                                                </button>
                                                <span class="text-sm text-gray-500 mt-1">Reviews</span>
                                            </div>
                    </div>
                                        <button onclick="addToCart('{{ $item->item_name }}', {{ $item->price }}, {{ $item->item_id }})" 
                                                class="w-full mt-3 py-2 bg-primary hover:bg-blue-600 text-white rounded-lg transition duration-200 flex items-center justify-center gap-2 {{ $item->is_available ? '' : 'opacity-50 cursor-not-allowed' }}"
                                                {{ $item->is_available ? '' : 'disabled' }}>
                                            <span class="flex items-center gap-2">
                                                <iconify-icon icon="mdi:cart" width="20" height="20"></iconify-icon> 
                                                <span class="add-to-cart-text">
                                                    @if($item->is_available)
                                                        @if($item->available_stock <= 3 && $item->available_stock > 0)
                                                        Add To Cart
                                                        @elseif($item->available_stock <= 0)
                                                            Sold Out
                                                        @else
                                                            Add To Cart
                                                        @endif
                                                    @else
                                                        Sold Out
                                                    @endif
                                                </span>
                                                <img src="https://media.tenor.com/On7kvXhzml4AAAAj/loading-gif.gif" class="add-cart-loading w-5 h-5 hidden" alt="loading">
                                            </span>
                                        </button>
                            </div>
                        </div>
                                @endforeach
                            @else
                                <div class="col-span-3 text-center py-10">
                                    <p class="text-gray-500">No menu items available at the moment.</p>
                    </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Ratings Modal -->
    <div id="ratings-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[100] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl p-5 max-w-md w-full max-h-[80vh] overflow-y-auto relative">
            <div class="flex justify-between items-center mb-4">
                <h3 id="ratings-modal-title" class="text-lg font-semibold">Product Ratings</h3>
                <button onclick="closeRatingsModal()" class="text-gray-500 hover:text-gray-700">
                    <iconify-icon icon="mdi:close" width="24" height="24"></iconify-icon>
                </button>
            </div>
            
            <div id="ratings-modal-content" class="divide-y">
                <!-- Ratings will be loaded here -->
                <div class="py-4 text-center text-gray-500">
                    <div class="animate-spin inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full mb-2"></div>
                    <p>Loading ratings...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add CSRF token to all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Handle reviews icon click to scroll to reviews section
        document.addEventListener('DOMContentLoaded', function() {
            const reviewsLink = document.querySelector('a[href="#reviews-section"]');
            if (reviewsLink) {
                reviewsLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    const reviewsSection = document.getElementById('reviews-section');
                    if (reviewsSection) {
                        reviewsSection.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            }
            
            // Add event listeners to all review buttons on products
            document.querySelectorAll('.review-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    const itemName = this.getAttribute('data-item-name');
                    viewProductRatings(itemId, itemName);
                });
            });
        });

        // Check for orders to auto-cancel every 10 seconds
        $(document).ready(function() {
            // Start the auto-cancellation check after the page loads
            loadCart();
            checkPendingOrders();
            
            // Initialize countdown timers for any pickup notifications
            initializeCountdownTimers();
            
            // Add event listeners for cancel buttons
            addCancelButtonListeners();

            // Log to console for verification
            console.log('Dashboard initialized');
        });

        // Initialize countdown timers
        function initializeCountdownTimers() {
            // Handle pending countdowns (15 minutes)
            const pendingCountdownElements = document.querySelectorAll('.pending-countdown');
            pendingCountdownElements.forEach(element => {
                const orderId = element.getAttribute('data-order-id');
                const createdAt = element.getAttribute('data-created-at');

                // Calculate the time difference in seconds
                const createdAtDate = new Date(createdAt);
                const now = new Date();
                const diffTime = Math.abs(now - createdAtDate);
                const diffSeconds = Math.floor(diffTime / 1000);

                // Set the initial remaining time (15 minutes = 900 seconds)
                let remainingSeconds = Math.max(0, 900 - diffSeconds);

                // Update the timer display
                element.textContent = formatTime(remainingSeconds);

                // Apply styling based on remaining time
                if (remainingSeconds <= 60) { // Less than 1 minute remaining
                    element.classList.add('text-red-600', 'font-bold');
                } else if (remainingSeconds <= 300) { // Less than 5 minutes remaining
                    element.classList.add('text-yellow-600', 'font-semibold');
                }

                // Update the timer every second
                const timerId = setInterval(() => {
                    try {
                        // Check if order has been cancelled every 5 seconds
                        if (remainingSeconds % 5 === 0) {
                            checkOrderStatus(orderId, element).then(isCancelled => {
                                if (isCancelled) {
                                    clearInterval(timerId);
                                    return;
                                }
                            });
                        }
                        
                        remainingSeconds--;
                        
                        // Ensure remaining seconds is not negative
                        if (remainingSeconds < 0) {
                            remainingSeconds = 0;
                        }
                        
                        element.textContent = formatTime(remainingSeconds);

                        // Apply styling based on remaining time
                        if (remainingSeconds <= 60) {
                            element.classList.add('text-red-600', 'font-bold');
                        } else if (remainingSeconds <= 300) {
                            element.classList.add('text-yellow-600', 'font-semibold');
                        }

                        // Clear the interval when time is up
                        if (remainingSeconds <= 0) {
                            clearInterval(timerId);
                            element.textContent = 'Expired!';
                            element.classList.add('text-red-600', 'font-bold');
                            
                            // Get the parent notification and add a strikethrough class
                            const parentNotification = element.closest('.py-3');
                            if (parentNotification) {
                                parentNotification.classList.add('opacity-50');
                                const messageElement = parentNotification.querySelector('.text-sm');
                                if (messageElement) {
                                    messageElement.innerHTML = '<span class="line-through">' + messageElement.innerHTML + '</span> <span class="text-red-500">(Auto-cancelled)</span>';
                                }
                            }
                            
                            // Send auto-cancel request
                            $.ajax({
                                url: "{{ route('orders.auto-cancel') }}",
                                type: "POST",
                                data: {
                                    order_id: orderId,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    console.log('Auto-cancel response from pending notification timer expiry:', response);
                                },
                                error: function(error) {
                                    console.error('Error auto-cancelling pending order from notification timer expiry:', error);
                                }
                            });
                        }
                    } catch (error) {
                        console.error('Error updating pending countdown:', error);
                        clearInterval(timerId);
                    }
                }, 1000);
            });
        }

        // Search functionality
        function searchItems() {
            const searchInput = document.getElementById('search-input');
            const searchTerm = searchInput.value.toLowerCase().trim();
            
            // If search is empty, show all items (current filter)
            if (searchTerm === '') {
                // Find the active category
                const activeCategory = document.querySelector('.filter-wrapper .bg-accent');
                if (activeCategory) {
                    const categoryCard = activeCategory.closest('.group');
                    if (categoryCard) {
                        const category = categoryCard.dataset.category || 'all';
                        filterItems(category);
                    } else {
                        filterItems('all');
                    }
                } else {
                    filterItems('all');
                }
                return;
            }
            
            const foodItems = document.querySelectorAll('.food-item');
            const currentSellerId = document.body.getAttribute('data-current-seller-id') || 'all';
            
            // Count items that match the search
            let matchCount = 0;
            
            foodItems.forEach(item => {
                const itemName = item.querySelector('h4').innerText.toLowerCase();
                const itemDescription = item.querySelector('p.text-sm').innerText.toLowerCase();
                
                let shouldShow = itemName.includes(searchTerm) || itemDescription.includes(searchTerm);
                
                // Additional filter for seller if one is selected
                if (currentSellerId !== 'all') {
                    shouldShow = shouldShow && (item.dataset.sellerId === currentSellerId);
                }
                
                if (shouldShow) {
                    item.classList.remove('hidden');
                    matchCount++;
                } else {
                    item.classList.add('hidden');
                }
            });
            
            // Show message if no items match the search
            const noItemsMessage = document.getElementById('no-items-message');
            if (noItemsMessage) {
                if (matchCount === 0) {
                    noItemsMessage.innerHTML = `<p class="text-gray-500">No items found matching "${searchTerm}".</p>`;
                    noItemsMessage.classList.remove('hidden');
                } else {
                    noItemsMessage.classList.add('hidden');
                }
            }
        }
        
        // Add event listener for Enter key on search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchItems();
                    }
                });
            }
        });

        // Mobile sidebar toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const closeSidebar = document.getElementById('close-sidebar');
        const sidebar = document.getElementById('sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('transform-none');
                sidebar.classList.toggle('-translate-x-full');
            });
        }
        
        if (closeSidebar && sidebar) {
            closeSidebar.addEventListener('click', function() {
                sidebar.classList.remove('transform-none');
                sidebar.classList.add('-translate-x-full');
            });
        }
        
        // Check if we're on mobile and hide sidebar by default
        function checkMobileView() {
            if (window.innerWidth < 768 && sidebar) {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('transform-none');
            } else if (sidebar) {
                sidebar.classList.remove('-translate-x-full');
            }
        }
        
        // Run on page load
        window.addEventListener('DOMContentLoaded', checkMobileView);
        
        // Run on resize
        window.addEventListener('resize', checkMobileView);

        // Cart functionality
        let cart = [];
        
        // Load cart from database on page load
        $(document).ready(function() {
            loadCart();
            checkPendingOrders();
        });
        
        // Check if user has pending orders
        function checkPendingOrders() {
            $.ajax({
                url: "{{ route('orders.check-pending') }}",
                type: "GET",
                success: function(response) {
                    if (response.has_pending_orders) {
                        showMessage('You have pending orders. Please complete your current orders before placing new ones.', 'warning');
                    }
                },
                error: function(error) {
                    console.error("Error checking pending orders:", error);
                }
            });
        }
        
        function loadCart() {
            $.ajax({
                url: "{{ route('cart.get') }}",
                type: "GET",
                success: function(response) {
                    if (response.success) {
                        // Setup cart array from database
                        cart = [];
                        if (response.cart.items && response.cart.items.length > 0) {
                            response.cart.items.forEach(item => {
                cart.push({
                                    id: item.item_id,
                                    name: item.item_name, 
                                    price: parseFloat(item.price),
                                    quantity: item.quantity,
                                    total: parseFloat(item.price) * item.quantity
                                });
                            });
                        }
                        updateCartDisplay();
                    } else {
                        console.error("Failed to load cart:", response.message);
                    }
                },
                error: function(error) {
                    console.error("Error loading cart:", error);
                }
            });
        }
        
        function addToCart(itemName, price, itemId) {
            console.log('Adding to cart:', itemId, itemName, price);
            
            // Check existing cart item count
            let currentTotalItems = 0;
            cart.forEach(item => {
                currentTotalItems += item.quantity;
            });
            
            if (currentTotalItems >= 3) {
                showMessage('You can only order up to a maximum of 3 items per transaction.', 'warning');
                return;
            }
            
            // Show loading spinner on the clicked button
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                if (button.onclick && button.onclick.toString().includes(itemId)) {
                    const loadingImg = button.querySelector('.add-cart-loading');
                    const textSpan = button.querySelector('.add-to-cart-text');
                    if (loadingImg && textSpan) {
                        loadingImg.classList.remove('hidden');
                        textSpan.classList.add('hidden');
                    }
                }
            });
            
            const requestData = {
                item_id: itemId,
                item_name: itemName,
                price: price,
                quantity: 1
            };
            
            console.log('Sending request data:', requestData);
            
            // Send AJAX request to add item to cart
            $.ajax({
                url: "{{ route('cart.add') }}",
                type: "POST",
                data: requestData,
                success: function(response) {
                    console.log('Success response:', response);
                    // Hide all loading spinners
                    document.querySelectorAll('.add-cart-loading').forEach(img => img.classList.add('hidden'));
                    document.querySelectorAll('.add-to-cart-text').forEach(span => span.classList.remove('hidden'));
                    
                    if (response.success) {
                        // Refresh the cart display
                        loadCart();
                        // Show success message
                        showMessage('Item added to cart!', 'success');
                    } else {
                        console.error("Failed to add item to cart:", response.message);
                        showMessage(response.message || 'Failed to add item to cart.', 'error');
                    }
                },
                error: function(error) {
                    console.error("Error adding item to cart:", error);
                    console.log('Error response:', error.responseJSON);
                    
                    // Hide all loading spinners
                    document.querySelectorAll('.add-cart-loading').forEach(img => img.classList.add('hidden'));
                    document.querySelectorAll('.add-to-cart-text').forEach(span => span.classList.remove('hidden'));
                    
                    // Display the specific error message if available
                    let errorMessage = 'Error adding item to cart.';
                    if (error.responseJSON && error.responseJSON.message) {
                        errorMessage = error.responseJSON.message;
                    }
                    showMessage(errorMessage, 'error');
                }
            });
        }
        
        function removeFromCart(itemId) {
            $.ajax({
                url: "{{ route('cart.remove') }}",
                type: "POST",
                data: {
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh the cart display
                        loadCart();
                        showMessage('Item removed from cart!', 'success');
                    } else {
                        console.error("Failed to remove item from cart:", response.message);
                        showMessage(response.message || 'Failed to remove item.', 'error');
                    }
                },
                error: function(error) {
                    console.error("Error removing item from cart:", error);
                    let errorMessage = 'Error removing item.';
                    if (error.responseJSON && error.responseJSON.message) {
                        errorMessage = error.responseJSON.message;
                    }
                    showMessage(errorMessage, 'error');
                }
            });
        }
        
        function updateQuantity(itemId, quantity) {
            $.ajax({
                url: "{{ route('cart.update') }}",
                type: "POST",
                data: {
                    item_id: itemId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh the cart display
                        loadCart();
                    } else {
                        console.error("Failed to update quantity:", response.message);
                        showMessage(response.message || 'Failed to update quantity.', 'error');
                    }
                },
                error: function(error) {
                    console.error("Error updating quantity:", error);
                    
                    // Display the specific error message if available
                    let errorMessage = 'Error updating quantity.';
                    if (error.responseJSON && error.responseJSON.message) {
                        errorMessage = error.responseJSON.message;
                    }
                    showMessage(errorMessage, 'error');
                }
            });
        }
        
        function updateCartDisplay() {
            const cartCount = document.getElementById('cart-count');
            const cartItems = document.getElementById('cart-items').getElementsByTagName('tbody')[0];
            const cartTotal = document.getElementById('cart-total');
            
            // Update cart count
            let totalItems = 0;
            cart.forEach(item => {
                totalItems += item.quantity;
            });
            cartCount.textContent = totalItems;
            
            // Clear existing cart items
            cartItems.innerHTML = '';
            
            // Add items to cart
            let total = 0;
            cart.forEach(item => {
                const row = cartItems.insertRow();
                row.innerHTML = `
                    <td class="py-2 px-2">${item.name}</td>
                    <td class="py-2 px-2 text-center">
                        <div class="flex items-center justify-center">
                            <button onclick="updateQuantity(${item.id}, ${Math.max(1, item.quantity - 1)})" class="text-gray-500 hover:text-primary">-</button>
                            <span class="mx-2">${item.quantity}</span>
                            <button onclick="updateQuantity(${item.id}, ${item.quantity + 1})" class="text-gray-500 hover:text-primary">+</button>
                        </div>
                    </td>
                    <td class="py-2 px-2 text-right">${item.price.toFixed(2)}</td>
                    <td class="py-2 px-2 text-right">
                        <div class="flex items-center justify-end">
                            <span class="mr-2">${(item.price * item.quantity).toFixed(2)}</span>
                            <button onclick="removeFromCart(${item.id})" class="text-red-500 hover:text-red-700">
                                <iconify-icon icon="mdi:delete" width="18" height="18"></iconify-icon>
                            </button>
                        </div>
                    </td>
                `;
                total += item.price * item.quantity;
            });
            
            // Update total
            cartTotal.textContent = total.toFixed(2);
            
            // Show/hide cart elements
            const emptyCartMessage = document.getElementById('empty-cart-message');
            const cartActions = document.getElementById('cart-actions');
            
            if (cart.length > 0) {
                if (emptyCartMessage) emptyCartMessage.classList.add('hidden');
                if (cartActions) cartActions.classList.remove('hidden');
            } else {
                if (emptyCartMessage) emptyCartMessage.classList.remove('hidden');
                if (cartActions) cartActions.classList.add('hidden');
            }
        }
        
        function clearCart() {
            $.ajax({
                url: "{{ route('cart.clear') }}",
                type: "POST",
                success: function(response) {
                    if (response.success) {
                        // Refresh the cart display
                        loadCart();
                        showMessage('Cart has been cleared!', 'success');
                    } else {
                        console.error("Failed to clear cart:", response.message);
                        showMessage('Failed to clear cart.', 'error');
                    }
                },
                error: function(error) {
                    console.error("Error clearing cart:", error);
                    showMessage('Error clearing cart.', 'error');
                }
            });
        }
        
        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            
            // Create a more prominent message box with an icon
            messageDiv.className = `fixed bottom-4 right-4 px-5 py-3 rounded-lg text-white ${type === 'success' ? 'bg-green-600' : type === 'warning' ? 'bg-yellow-400' : 'bg-red-600'} shadow-lg z-50 message-popup flex items-center`;
            
            // Add appropriate icon
            const icon = document.createElement('span');
            icon.className = 'mr-2';
            icon.innerHTML = type === 'success' ? 
                '<iconify-icon icon="mdi:check-circle" width="20" height="20"></iconify-icon>' : 
                type === 'warning' ? '<iconify-icon icon="mdi:alert-circle" width="20" height="20"></iconify-icon>' : 
                '<iconify-icon icon="mdi:alert-circle" width="20" height="20"></iconify-icon>';
            messageDiv.appendChild(icon);
            
            // Add message text
            const text = document.createElement('span');
            text.innerText = message;
            messageDiv.appendChild(text);
            
            document.body.appendChild(messageDiv);
            
            // Calculate timeout based on message length and type
            // Error messages stay longer on screen than success messages
            const timeoutDuration = type === 'success' ? 3000 : type === 'warning' ? 5000 : 5000;
            
            setTimeout(() => {
                messageDiv.classList.add('opacity-0');
                setTimeout(() => {
                    document.body.removeChild(messageDiv);
                }, 500);
            }, timeoutDuration);
        }
        
        function toggleCartPopup() {
            const cartPopup = document.getElementById('cart-popup');
            cartPopup.classList.toggle('hidden');
        }
        
        function closeCart() {
            const cartPopup = document.getElementById('cart-popup');
            cartPopup.classList.add('hidden');
        }

        // Notification popup functions
        function toggleNotificationPopup() {
            const notificationPopup = document.getElementById('notification-popup');
            notificationPopup.classList.toggle('hidden');
        }
        
        function closeNotifications() {
            const notificationPopup = document.getElementById('notification-popup');
            notificationPopup.classList.add('hidden');
        }

        // Category filtering functionality
        function filterItems(category) {
            const foodItems = document.querySelectorAll('.food-item');
            const currentSellerId = document.body.getAttribute('data-current-seller-id') || 'all';
            
            // Count items that match the filter
            let matchCount = 0;
            
            foodItems.forEach(item => {
                if (!item.dataset.category) {
                    item.dataset.category = 'uncategorized';
                }
                
                const itemCategory = item.dataset.category.toLowerCase();
                
                let shouldShow = (category === 'all' || category.toLowerCase() === itemCategory);
                
                // Additional filter for seller
                if (currentSellerId !== 'all') {
                    shouldShow = shouldShow && (item.dataset.sellerId === currentSellerId);
                }
                
                if (shouldShow) {
                    item.classList.remove('hidden');
                    matchCount++;
                } else {
                    item.classList.add('hidden');
                }
            });
            
            // Show message if no items match the filter
            const noItemsMessage = document.getElementById('no-items-message');
            if (noItemsMessage) {
                if (matchCount === 0) {
                    noItemsMessage.classList.remove('hidden');
                } else {
                    noItemsMessage.classList.add('hidden');
                }
            }
            
            console.log('Filtering by category:', category, 'Matches:', matchCount);
        }
        
        // Horizontal scroll for highlight and filter sections
        const highlightNext = document.querySelector('.next');
        const highlightBack = document.querySelector('.back');
        const highlightWrapper = document.querySelector('.highlight-wrapper');
        
        if (highlightNext && highlightWrapper) {
            highlightNext.addEventListener('click', function() {
                highlightWrapper.scrollBy({
                    left: 300,
                    behavior: 'smooth'
                });
            });
        }
        
        if (highlightBack && highlightWrapper) {
            highlightBack.addEventListener('click', function() {
                highlightWrapper.scrollBy({
                    left: -300,
                    behavior: 'smooth'
                });
            });
        }
        
        const filterNext = document.querySelector('.next-menu');
        const filterBack = document.querySelector('.back-menu');
        const filterWrapper = document.querySelector('.filter-wrapper');
        
        if (filterNext && filterWrapper) {
            filterNext.addEventListener('click', function() {
                filterWrapper.scrollBy({
                    left: 300,
                    behavior: 'smooth'
                });
            });
        }
        
        if (filterBack && filterWrapper) {
            filterBack.addEventListener('click', function() {
                filterWrapper.scrollBy({
                    left: -300,
                    behavior: 'smooth'
                });
            });
        }
        
        // Add active class for filters and implement filtering
        document.addEventListener('DOMContentLoaded', function() {
            const filterCards = document.querySelectorAll('.filter-wrapper .group');
            
            // Debug - console log any existing notifications 
            console.log('User notifications count:', {{ auth()->user()->unreadNotifications->count() }});
            console.log('User ID:', {{ auth()->id() }});
            @if(auth()->user()->notifications->count() > 0)
                console.log('Has notifications');
            @else
                console.log('No notifications');
            @endif

            // Rest of your existing code
            if (filterCards && filterCards.length > 0) {
                // Set up click handlers for category filters
                filterCards.forEach(card => {
                    card.addEventListener('click', function() {
                        // Remove active class from all cards
                        filterCards.forEach(c => {
                            c.querySelector('div').classList.remove('bg-accent');
                            c.querySelector('div').classList.add('bg-primary');
                        });
                        
                        // Add active class to clicked card
                        this.querySelector('div').classList.remove('bg-primary');
                        this.querySelector('div').classList.add('bg-accent');
                        
                        // Get category from data attribute
                        const category = this.dataset.category || 'all';
                        
                        // Filter food items based on category
                        filterItems(category);
                    });
                });
                
                // Initialize with "All Menu" selected
                filterItems('all');
            }
        });
        
        // Add function to filter by seller
        function filterMenuBySeller(sellerId) {
            const foodItems = document.querySelectorAll('.food-item');
            const sellerInfoDiv = document.getElementById('current-seller-info');
            
            // Find seller name
            let sellerName = '';
            const sellerElements = document.querySelectorAll('.font-medium.text-lg');
            sellerElements.forEach(el => {
                const parent = el.closest('[onclick*="filterMenuBySeller(' + sellerId + ')"]');
                if (parent) {
                    sellerName = el.textContent;
                }
            });
            
            // Update current seller info
            document.getElementById('current-seller-name').textContent = sellerName || 'Stall #' + sellerId;
            sellerInfoDiv.classList.remove('hidden');
            
            // Set the current seller ID as a data attribute on the body
            document.body.setAttribute('data-current-seller-id', sellerId.toString());
            
            // Find the active category
            const activeCategory = document.querySelector('.filter-wrapper .bg-accent');
            if (activeCategory) {
                const categoryCard = activeCategory.closest('.group');
                if (categoryCard) {
                    const category = categoryCard.dataset.category || 'all';
                    filterItems(category);
                } else {
                    filterItems('all');
                }
            } else {
                filterItems('all');
            }
            
            // Scroll to the menu section
            document.querySelector('.font-semibold.text-dark.mb-4').scrollIntoView({ behavior: 'smooth' });
        }
        
        function showAllMenuItems() {
            // Reset the current seller filter
            document.body.setAttribute('data-current-seller-id', 'all');
            
            // Hide the current seller info box
            document.getElementById('current-seller-info').classList.add('hidden');
            
            // Find the active category
            const activeCategory = document.querySelector('.filter-wrapper .bg-accent');
            if (activeCategory) {
                const categoryCard = activeCategory.closest('.group');
                if (categoryCard) {
                    const category = categoryCard.dataset.category || 'all';
                    filterItems(category);
                } else {
                    filterItems('all');
                }
            } else {
                filterItems('all');
            }
        }

        function checkout() {
            // Check if there are more than 3 items in the cart
            let totalItems = 0;
            cart.forEach(item => {
                totalItems += item.quantity;
            });
            
            if (totalItems > 3) {
                showMessage('You can only order up to a maximum of 3 items per transaction.', 'warning');
                return;
            }
            
            // Check if cart is empty
            if (totalItems === 0) {
                showMessage('Your cart is empty. Please add items before checkout.', 'warning');
                return;
            }
            
            // Show loading state
            const checkoutBtn = document.querySelector('#cart-actions button:last-child');
            const originalText = checkoutBtn.innerHTML;
            checkoutBtn.innerHTML = `<span class="flex items-center justify-center gap-2">
                <img src="https://media.tenor.com/On7kvXhzml4AAAAj/loading-gif.gif" class="w-5 h-5" alt="loading">
                Processing...
            </span>`;
            checkoutBtn.disabled = true;
            
            // Generate a spot number automatically (combination of stall name and order number)
            const spotNumber = 'Table-' + Math.floor(Math.random() * 100);
            
            // Send checkout request
            $.ajax({
                url: "{{ route('orders.store') }}",
                type: "POST",
                data: {
                    spot_number: spotNumber
                },
                success: function(response) {
                    // Reset button state
                    checkoutBtn.innerHTML = originalText;
                    checkoutBtn.disabled = false;
                    
                    if (response.success) {
                        // Clear cart
                        cart = [];
                        updateCartDisplay();
                        
                        // Close cart popup
                        closeCart();
                        
                        // Show success message
                        showMessage(`Order placed successfully! Order #${response.order_number}`, 'success');
                        
                        // Optionally redirect to orders page
                        setTimeout(() => {
                            if (confirm("Your order has been placed! View your order details?")) {
                                window.location.href = "{{ route('orders.index') }}";
                            }
                        }, 1000);
                    } else {
                        showMessage(response.message || 'Failed to place order.', 'error');
                    }
                },
                error: function(xhr) {
                    // Reset button state
                    checkoutBtn.innerHTML = originalText;
                    checkoutBtn.disabled = false;
                    
                    let errorMessage = 'Error processing your order.';
                    
                    // Try to extract error message from response
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        errorMessage = Object.values(errors)[0][0] || errorMessage;
                    }
                    
                    showMessage(errorMessage, 'error');
                }
            });
        }

        function viewProductRatings(itemId, itemName) {
            // Debug statement
            console.log('Opening ratings modal for item:', itemId, itemName);
            
            // Show the modal with active class
            const modal = document.getElementById('ratings-modal');
            modal.classList.remove('hidden');
            modal.classList.add('active');
            
            // Add body class to prevent scrolling
            document.body.classList.add('overflow-hidden');
            
            // Debugging - check if modal is visible
            console.log('Modal active?', modal.classList.contains('active'));
            console.log('Modal hidden?', modal.classList.contains('hidden'));
            
            // Set the title
            document.getElementById('ratings-modal-title').textContent = `Ratings for ${itemName}`;
            
            // Show loading state
            document.getElementById('ratings-modal-content').innerHTML = `
                <div class="py-4 text-center text-gray-500">
                    <div class="animate-spin inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full mb-2"></div>
                    <p>Loading ratings...</p>
                </div>
            `;
            
            // Log the URL we're fetching from
            const apiUrl = `{{ route('get-product-ratings', ['itemId' => '__ITEM_ID__']) }}`.replace('__ITEM_ID__', itemId);
            console.log('Fetching ratings from:', apiUrl);
            
            // Fetch ratings for this item
            $.ajax({
                url: apiUrl,
                type: "GET",
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    // First make sure the modal is still open
                    if (modal.classList.contains('hidden') || !modal.classList.contains('active')) {
                        console.log('Modal was closed before AJAX response arrived');
                        return;
                    }
                    
                    console.log('Rating API response:', response);
                    let content = '';
                    
                    if (response.success && response.ratings && response.ratings.length > 0) {
                        // Calculate average rating
                        let totalRating = 0;
                        response.ratings.forEach(rating => {
                            totalRating += parseInt(rating.rating);
                        });
                        const avgRating = (totalRating / response.ratings.length).toFixed(1);
                        
                        // Add average rating display
                        content += `
                            <div class="pb-4 mb-2">
                                <div class="flex items-center gap-4">
                                    <div>
                                        <div class="text-3xl font-bold text-dark">${avgRating}</div>
                                        <div class="flex text-yellow-400">
                                            ${generateStars(avgRating)}
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Based on ${response.ratings.length} ratings
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Add individual ratings
                        response.ratings.forEach(rating => {
                            content += `
                                <div class="py-3">
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 mr-2">
                                                ${rating.user_name ? rating.user_name.charAt(0) : 'U'}
                                            </div>
                                            <div>
                                                <div class="font-medium">${rating.user_name || 'Unknown User'}</div>
                                                <div class="text-xs text-gray-500">${formatDate(rating.created_at)}</div>
                                            </div>
                                        </div>
                                        <div class="flex text-yellow-400">
                                            ${generateStars(rating.rating)}
                                        </div>
                                    </div>
                                    ${rating.review ? `<p class="mt-2 text-sm text-gray-700">${rating.review}</p>` : ''}
                                </div>
                            `;
                        });
                    } else {
                        content = `
                            <div class="py-6 text-center">
                                <div class="text-gray-400 mb-2">
                                    <iconify-icon icon="mdi:star-off" width="48" height="48"></iconify-icon>
                                </div>
                                <p class="text-gray-500">No ratings yet for this product</p>
                            </div>
                        `;
                    }
                    
                    // Double check the modal is still open before updating content
                    if (!modal.classList.contains('hidden') && modal.classList.contains('active')) {
                        console.log('Updating modal content with ratings');
                        document.getElementById('ratings-modal-content').innerHTML = content;
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching ratings:", error);
                    console.error("Status:", status);
                    console.error("Response:", xhr.responseText);
                    
                    // First make sure the modal is still open
                    if (modal.classList.contains('hidden') || !modal.classList.contains('active')) {
                        console.log('Modal was closed before AJAX error handling');
                        return;
                    }
                    
                    document.getElementById('ratings-modal-content').innerHTML = `
                        <div class="py-6 text-center">
                            <div class="text-red-400 mb-2">
                                <iconify-icon icon="mdi:alert-circle" width="48" height="48"></iconify-icon>
                            </div>
                            <p class="text-gray-500">Failed to load ratings. Please try again.</p>
                            <p class="text-sm text-gray-400 mt-2">Error: ${error || 'Unknown error'}</p>
                        </div>
                    `;
                }
            });
        }
        
        function closeRatingsModal() {
            console.log('Closing modal (explicit call)');
            const modal = document.getElementById('ratings-modal');
            modal.classList.add('hidden');
            modal.classList.remove('active');
            document.body.classList.remove('overflow-hidden');
        }
        
        // Helper function to generate star icons based on rating
        function generateStars(rating) {
            let stars = '';
            const fullStars = Math.floor(rating);
            const hasHalfStar = rating % 1 >= 0.5;
            
            for (let i = 1; i <= 5; i++) {
                if (i <= fullStars) {
                    stars += '<iconify-icon icon="mdi:star" width="16" height="16"></iconify-icon>';
                } else if (i === fullStars + 1 && hasHalfStar) {
                    stars += '<iconify-icon icon="mdi:star-half" width="16" height="16"></iconify-icon>';
                } else {
                    stars += '<iconify-icon icon="mdi:star-outline" width="16" height="16"></iconify-icon>';
                }
            }
            
            return stars;
        }
        
        // Helper to format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays < 1) {
                return 'Today';
            } else if (diffDays === 1) {
                return 'Yesterday';
            } else if (diffDays < 7) {
                return `${diffDays} days ago`;
            } else {
                return date.toLocaleDateString();
            }
        }

        // Helper to format time (minutes:seconds)
        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }
        
        // Check if order is cancelled and stop countdown
        async function checkOrderStatus(orderId, element) {
            try {
                const response = await $.ajax({
                    url: "{{ route('orders.check-status') }}",
                    type: "POST",
                    data: {
                        order_id: orderId,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                // Check if order is cancelled
                if (response.success && response.status === 'cancelled') {
                    element.textContent = 'Cancelled';
                    element.classList.add('text-red-600', 'font-bold');
                    
                    // Get the parent notification and add a strikethrough class
                    const parentNotification = element.closest('.py-3');
                    if (parentNotification) {
                        parentNotification.classList.add('opacity-50');
                        const messageElement = parentNotification.querySelector('.text-sm');
                        if (messageElement) {
                            messageElement.innerHTML = '<span class="line-through">' + messageElement.innerHTML + '</span> <span class="text-red-500">(Cancelled)</span>';
                        }
                    }
                    return true; // Order is cancelled
                }
                return false; // Order is not cancelled
            } catch (error) {
                console.error('Error checking order status:', error);
                return false; // Assume not cancelled on error
            }
        }
        
        // Stop countdown for a specific order
        function stopCountdownForOrder(orderId) {
            const countdownElements = document.querySelectorAll('.pending-countdown[data-order-id="' + orderId + '"]');
            countdownElements.forEach(element => {
                element.textContent = 'Cancelled';
                element.classList.add('text-red-600', 'font-bold');
                
                // Get the parent notification and add a strikethrough class
                const parentNotification = element.closest('.py-3');
                if (parentNotification) {
                    parentNotification.classList.add('opacity-50');
                    const messageElement = parentNotification.querySelector('.text-sm');
                    if (messageElement) {
                        messageElement.innerHTML = '<span class="line-through">' + messageElement.innerHTML + '</span> <span class="text-red-500">(Cancelled)</span>';
                    }
                }
            });
        }
        
        // Add event listeners for cancel buttons
        function addCancelButtonListeners() {
            document.querySelectorAll('.cancel-order-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    // Stop countdown immediately when cancel button is clicked
                    stopCountdownForOrder(orderId);
                });
            });
        }
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeRatingsModal();
            }
        });
        
        // Close modal when clicking outside, but stop propagation for clicks inside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('ratings-modal');
            const modalContent = modal.querySelector('.bg-white');
            
            // Prevent clicks inside the modal from closing it
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Close when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeRatingsModal();
                }
            });
        });
    </script>
</body>
</html>
