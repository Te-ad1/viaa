<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>My Orders - Canteen Online Ordering System</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Iconify for icons -->
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- html2pdf library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
            transition: opacity 0.5s ease-in-out;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* Timer animation styles */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }
        
        .animate-pulse {
            animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .pickup-countdown {
            font-weight: bold;
        }
        
        /* Modal animation styles */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-modalIn {
            animation: modalFadeIn 0.3s ease-out forwards;
        }
        
        #order-details-modal {
            transition: opacity 0.2s ease-out;
        }
        
        /* Rating dialog styling */
        .swal-on-top {
            z-index: 11000 !important;
        }
        
        .swal2-container {
            z-index: 11000 !important;
        }
        
        .swal2-popup {
            position: relative;
            z-index: 11001 !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex">
       @include('includes.student-sidebar')

        <!-- Main Content -->
        <div class="ml-0 md:ml-64 w-full min-h-screen transition-all duration-300">
            <!-- Error Message (if any) -->
            @if(session('error'))
            <div class="p-5 hidden">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl shadow-sm" role="alert">
                    <p class="font-bold">Error</p>
                    <p>{{ session('error') }}</p>
                </div>
            </div>
            <script>
                // Show Sweet Alert for error messages
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: "{{ session('error') }}",
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3B82F6'
                    });
                });
            </script>
            @endif
            
            <!-- Success Message (if any) -->
            @if(session('success'))
            <div class="p-5">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl shadow-sm" role="alert">
                    <p class="font-bold">Success</p>
                    <p>{{ session('success') }}</p>
                </div>
            </div>
            @endif
            
            <!-- Top Navigation Bar -->
            <div class="p-5">
                <div class="bg-white rounded-xl shadow-sm p-4 flex justify-between items-center">
                    <button id="sidebar-toggle" class="md:hidden text-primary text-2xl">
                        <iconify-icon icon="mdi:menu" width="28" height="28"></iconify-icon>
                    </button>
                    
                    <h1 class="text-xl font-semibold text-gray-800 mx-4">My Orders</h1>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('dashboard') }}" class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition duration-200 hover:scale-110 group">
                            <iconify-icon icon="mdi:home" class="text-primary group-hover:text-accent" width="24" height="24"></iconify-icon>
                        </a>
                        <a href="#" class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition duration-200 hover:scale-110 group">
                            <iconify-icon icon="mdi:account" class="text-primary group-hover:text-accent" width="24" height="24"></iconify-icon>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="px-5 py-3">
                <!-- Order Filters -->
                <div class="mb-6 flex flex-col md:flex-row md:justify-between items-start md:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <button id="all-orders-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition active">All Orders</button>
                        <button id="active-orders-btn" class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Active</button>
                        <button id="completed-orders-btn" class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Completed</button>
                        <button id="cancelled-orders-btn" class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Cancelled</button>
                    </div>
                    
                    <div class="relative w-full md:w-64">
                        <input type="text" id="search-orders" placeholder="Search orders..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <iconify-icon icon="mdi:magnify" class="text-gray-400" width="20" height="20"></iconify-icon>
                        </div>
                    </div>
                </div>
                
                <!-- Orders List -->
                <div class="space-y-5 order-list">
                    @if(count($orders) > 0)
                        @foreach($orders as $order)
                            <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100 order-item" 
                                data-status="{{ $order->status }}"
                                data-order-number="{{ $order->order_number }}"
                                data-seller="{{ $order->seller->stall_name ?? 'Seller #' . $order->seller_id }}">
                                <div class="flex flex-col md:flex-row">
                                    <!-- Order Basic Info -->
                                    <div class="p-5 flex-grow">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <div class="flex items-center gap-3">
                                                    <h3 class="text-lg font-semibold">Order #{{ $order->order_number }}</h3>
                                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                                        @if($order->status == 'cancelled') bg-red-100 text-red-800
                                                        @elseif($order->status == 'completed') bg-green-100 text-green-800
                                                        @elseif($order->status == 'ready') bg-blue-100 text-blue-800
                                                        @elseif($order->status == 'confirm') bg-purple-100 text-purple-800
                                                        @else bg-yellow-100 text-yellow-800
                                                        @endif">
                                                        {{ ucfirst($order->status) }}
                                                    </span>
                                                    
                                                    @if($order->status == 'pending')
                                                    <span class="ml-2 px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold pickup-timer-badge">
                                                        Pay at counter: <span class="pickup-countdown" data-order-id="{{ $order->order_id }}" data-created-at="{{ date('c', strtotime($order->created_at)) }}">
                                                            @php
                                                                // Calculate seconds remaining
                                                                $orderCreated = \Carbon\Carbon::parse($order->created_at);
                                                                $now = now();
                                                                
                                                                // More explicit calculation with seconds since epoch
                                                                $orderCreatedTimestamp = $orderCreated->timestamp;
                                                                $nowTimestamp = $now->timestamp;
                                                                $elapsedSeconds = $nowTimestamp - $orderCreatedTimestamp;
                                                                
                                                                $remainingSeconds = max(0, 900 - $elapsedSeconds); // 15 minutes = 900 seconds
                                                                $remainingMinutes = floor($remainingSeconds / 60);
                                                                $remainingSeconds = $remainingSeconds % 60;
                                                                
                                                                // Debug information
                                                                \Log::info('Order timer for Order #'.$order->order_number, [
                                                                    'order_id' => $order->order_id,
                                                                    'created_at' => $orderCreated->toDateTimeString(),
                                                                    'now' => $now->toDateTimeString(),
                                                                    'elapsed' => $elapsedSeconds,
                                                                    'remaining' => $remainingMinutes * 60 + $remainingSeconds,
                                                                ]);
                                                                
                                                                // Format the time display
                                                                echo sprintf('%02d:%02d', $remainingMinutes, $remainingSeconds);
                                                            @endphp
                                                        </span>
                                                    </span>
                                                    @endif
                                                </div>
                                                <p class="text-gray-600 text-sm mt-1">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                                            </div>
                                            <p class="font-medium text-primary">₱{{ number_format($order->total_amount, 2) }}</p>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <p class="text-gray-700 font-medium">{{ $order->seller->stall_name ?? 'Seller #' . $order->seller_id }}</p>
                                            <p class="text-gray-600 text-sm">{{ $order->seller->stall_location ?? 'No location specified' }}</p>
                                            <p class="text-gray-600 text-sm mt-1"><strong>Pickup:</strong> New Building of College</p>
                                        </div>
                                        
                                        <!-- Order Items Preview -->
                                        <div class="mb-4">
                                            <p class="text-gray-700 font-medium mb-2">Items:</p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($order->orderItems as $item)
                                                    <div class="bg-gray-100 rounded-full px-3 py-1 text-sm text-gray-800">
                                                        {{ $item->quantity }}x {{ $item->menuItem->item_name ?? 'Item #'.$item->item_id }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        
                                        <div class="flex gap-3">
                                            <a href="/orders/{{ $order->order_id }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition text-sm">
                                                View Details
                                            </a>
                                            
                                            @if($order->status == 'pending')
                                                <form action="{{ route('orders.cancel', $order->order_id) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit" class="bg-red-50 text-red-700 px-4 py-2 rounded-lg hover:bg-red-100 transition text-sm cancel-order-btn" data-order-id="{{ $order->order_id }}">
                                                        Cancel Order
                                                    </button>
                                                </form>
                                            @endif
                                            
                                            @if($order->status == 'completed' || $order->status == 'ready' || $order->status == 'confirm')
                                                <button type="button" class="bg-green-50 text-green-700 px-4 py-2 rounded-lg hover:bg-green-100 transition text-sm print-receipt-btn"
                                                    data-order-id="{{ $order->order_id }}">
                                                    <span class="flex items-center gap-1">
                                                        <iconify-icon icon="mdi:receipt" width="16" height="16"></iconify-icon>
                                                        Receipt
                                                    </span>
                                                </button>
                                                <div id="receipt-data-{{ $order->order_id }}" class="hidden">
                                                    <div data-order-number="{{ $order->order_number }}"></div>
                                                    <div data-order-date="{{ $order->created_at->format('M d, Y h:i A') }}"></div>
                                                    <div data-seller-name="{{ $order->seller->stall_name ?? 'Unknown Seller' }}"></div>
                                                    <div data-status="{{ ucfirst($order->status) }}"></div>
                                                    <div data-total="{{ number_format($order->total_amount, 2) }}"></div>
                                                    <div data-building="{{ Auth::user()->building ?? 'New Building of College' }}"></div>
                                                    <div data-stall-location="{{ $order->seller->stall_location ?? '' }}"></div>
                                                    @foreach($order->orderItems as $item)
                                                        <div class="receipt-item" 
                                                             data-name="{{ $item->menuItem->item_name ?? 'Unknown Item' }}"
                                                             data-quantity="{{ $item->quantity }}"
                                                             data-subtotal="{{ number_format($item->subtotal, 2) }}">
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Order Thumbnail -->
                                    <div class="w-full md:w-40 h-32 md:h-40 flex items-center justify-center overflow-hidden border-t md:border-t-0 md:border-l border-gray-100">
                                        <div class="w-24 h-24 rounded-full overflow-hidden">
                                            @if($order->orderItems->first() && $order->orderItems->first()->menuItem && $order->orderItems->first()->menuItem->image_url)
                                                <img src="{{ $order->orderItems->first()->menuItem->image_url }}" alt="Food" class="w-full h-full object-cover">
                                            @else
                                                <div class="bg-gray-200 w-full h-full flex items-center justify-center">
                                                    <iconify-icon icon="mdi:food" class="text-gray-400" width="36" height="36"></iconify-icon>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="bg-white rounded-xl p-8 shadow-sm text-center">
                            <iconify-icon icon="mdi:food-off" class="text-gray-400 mb-4" width="64" height="64"></iconify-icon>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">No Orders Yet</h3>
                            <p class="text-gray-600 mb-4">You haven't placed any orders yet. Browse the menu and place your first order!</p>
                            <a href="{{ route('dashboard') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition">
                                Browse Menu
                            </a>
                        </div>
                    @endif
                </div>
                
                <!-- Pagination -->
                @if($orders->hasPages())
                    <div class="mt-6">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Setup receipt buttons with event listeners
                    document.addEventListener('DOMContentLoaded', function() {
            const receiptButtons = document.querySelectorAll('.print-receipt-btn');
            receiptButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    const receiptData = document.getElementById(`receipt-data-${orderId}`);
                    
                    if (!receiptData) {
                        console.error("Receipt data container not found");
                        return;
                    }
                    
                    const orderNumber = receiptData.querySelector('[data-order-number]').getAttribute('data-order-number');
                    const orderDate = receiptData.querySelector('[data-order-date]').getAttribute('data-order-date');
                    const sellerName = receiptData.querySelector('[data-seller-name]').getAttribute('data-seller-name');
                    const status = receiptData.querySelector('[data-status]').getAttribute('data-status');
                    const total = receiptData.querySelector('[data-total]').getAttribute('data-total');
                    const building = receiptData.querySelector('[data-building]').getAttribute('data-building');
                    const stallLocation = receiptData.querySelector('[data-stall-location]').getAttribute('data-stall-location');
                    
                    // Get items from the receipt-item divs
                    const itemElements = receiptData.querySelectorAll('.receipt-item');
                    const items = [];
                    
                    itemElements.forEach(item => {
                        items.push({
                            name: item.getAttribute('data-name'),
                            quantity: item.getAttribute('data-quantity'),
                            subtotal: item.getAttribute('data-subtotal')
                        });
                    });
                    
                    printOrderReceipt(orderId, orderNumber, orderDate, sellerName, status, total, items, building, stallLocation);
                });
            });
            
            // Initialize countdown timers if they exist
            initializeCountdownTimers();
            checkMobileView();
            
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
            
            // Filter buttons setup
            const allBtn = document.getElementById('all-orders-btn');
            const activeBtn = document.getElementById('active-orders-btn');
            const completedBtn = document.getElementById('completed-orders-btn');
            const cancelledBtn = document.getElementById('cancelled-orders-btn');
            const searchInput = document.getElementById('search-orders');
            const orderItems = document.querySelectorAll('.order-item');
            
            // Add event listeners to filter buttons
            if (allBtn) allBtn.addEventListener('click', function() {
                setActiveButton(this);
                filterOrders();
            });
            
            if (activeBtn) activeBtn.addEventListener('click', function() {
                setActiveButton(this);
                filterOrders();
            });
            
            if (completedBtn) completedBtn.addEventListener('click', function() {
                setActiveButton(this);
                filterOrders();
            });
            
            if (cancelledBtn) cancelledBtn.addEventListener('click', function() {
                setActiveButton(this);
                filterOrders();
            });
            
            // Add event listener to search input
            if (searchInput) searchInput.addEventListener('input', filterOrders);
            
            // Remove modal functionality for View Details links - Allow direct page navigation
            // const viewDetailsLinks = document.querySelectorAll('.order-item a');
            // viewDetailsLinks.forEach(link => {
            //     if (link.textContent.trim() === 'View Details') {
            //         link.addEventListener('click', function(e) {
            //             e.preventDefault();
            //             const orderId = this.href.split('/').pop();
            //             openOrderDetailsModal(orderId);
            //         });
            //     }
            // });
        });
        
        // Function to generate and print receipt - FIXED VERSION
        function printOrderReceipt(orderId, orderNumber, orderDate, sellerName, status, total, items, building, stallLocation) {
            try {
                // Create items HTML
                let itemsHTML = '';
                if (items && items.length) {
                    items.forEach(item => {
                        itemsHTML += `
                            <tr>
                                <td>${item.name}</td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-right">₱${item.subtotal}</td>
                            </tr>
                        `;
                    });
                }
                
                // Use default building if none provided
                const displayBuilding = building || 'New Building of College';
                // Use stall location if provided
                const displayStallLocation = stallLocation || '';
                
                // Create receipt content with simplified structure
                let receiptContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Order Receipt #${orderNumber}</title>
                        <meta charset="UTF-8">
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"><\/script>
                        <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"><\/script>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
                            .receipt-container { max-width: 500px; margin: 20px auto; background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; }
                            .receipt-header { background-color: #3B82F6; color: white; padding: 15px; text-align: center; position: relative; }
                            .close-btn { position: absolute; right: 15px; top: 15px; background: rgba(255,255,255,0.3); width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; }
                            .order-number { font-size: 24px; font-weight: bold; text-align: center; margin: 15px 0; }
                            .order-date { text-align: center; color: #666; margin-bottom: 15px; }
                            .receipt-content { padding: 15px; }
                            .receipt-section { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
                            .receipt-section:last-child { border-bottom: none; }
                            .section-title { font-weight: bold; margin-bottom: 8px; }
                            .receipt-info { display: flex; justify-content: space-between; margin-bottom: 5px; }
                            .receipt-info span:first-child { font-weight: 500; }
                            .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                            .items-table th { border-bottom: 1px solid #ddd; text-align: left; padding: 8px 4px; }
                            .items-table td { border-bottom: 1px solid #eee; padding: 8px 4px; }
                            .text-right { text-align: right; }
                            .text-center { text-align: center; }
                            .receipt-total { text-align: right; font-weight: bold; margin-top: 10px; font-size: 18px; }
                            .status-badge { display: inline-block; background-color: #FEF3C7; color: #D97706; padding: 5px 15px; border-radius: 20px; font-size: 14px; margin: 10px auto; text-align: center; }
                            .action-buttons { display: flex; justify-content: center; gap: 10px; padding: 15px; background: #f9f9f9; }
                            .btn { padding: 10px 15px; border-radius: 5px; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; }
                            .btn-primary { background: #3B82F6; color: white; border: none; }
                            .btn-secondary { background: white; color: #333; border: 1px solid #ddd; }
                            @media print { .no-print, .action-buttons { display: none; } .receipt-container { box-shadow: none; } }
                            .btn iconify-icon { margin-right: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class="receipt-container">
                            <div class="receipt-header">
                                <div class="close-btn" onclick="window.close()">✕</div>
                                <h2>Order Receipt</h2>
                                <p>Show this to the seller to collect your order</p>
                            </div>
                            
                            <div class="order-number">${orderId}</div>
                            <div class="order-date">
                                Order #${orderNumber}<br>
                                ${orderDate}
                            </div>
                            
                            <div class="receipt-content">
                                                                    <div class="receipt-section">
                                    <div class="section-title">Stall Details:</div>
                                    <div class="receipt-info">
                                        <span>Name:</span>
                                        <span>${sellerName}</span>
                                    </div>
                                    <div class="receipt-info">
                                        <span>Location:</span>
                                        <span>${displayStallLocation}</span>
                                    </div>
                                    <div class="receipt-info">
                                        <span>Building:</span>
                                        <span>${displayBuilding}</span>
                                    </div>
                                </div>
                                
                                <div class="receipt-section">
                                    <div class="section-title">Student:</div>
                                    <span>{{ Auth::user()->email }}</span>
                                </div>
                                
                                <div class="receipt-section">
                                    <div class="section-title">Items:</div>
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-right">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${itemsHTML}
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="receipt-total">
                                    <div class="section-title">Total:</div>
                                    <p>₱${total}</p>
                                </div>
                                
                                <div style="text-align: center;">
                                    <div class="status-badge">${status}</div>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="window.print()">
                                    <iconify-icon icon="mdi:printer" width="20" height="20"></iconify-icon>
                                    Print Receipt
                                </button>
                                <button class="btn btn-secondary" onclick="downloadPdf()">
                                    <iconify-icon icon="mdi:download" width="20" height="20"></iconify-icon>
                                    Download
                                </button>
                            </div>
                        </div>
                        
                        <script>
                            function downloadPdf() {
                                try {
                                    // Get the receipt container
                                    const element = document.querySelector('.receipt-container');
                                    
                                    // Hide the action buttons during PDF generation
                                    const actionButtons = document.querySelector('.action-buttons');
                                    actionButtons.style.display = 'none';
                                    
                                    // Configure html2pdf options
                                    const options = {
                                        margin: 10,
                                        filename: 'order-receipt-${orderNumber}.pdf',
                                        image: { type: 'jpeg', quality: 0.98 },
                                        html2canvas: { scale: 2, useCORS: true },
                                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                                    };
                                    
                                    // Generate the PDF
                                    html2pdf().from(element).set(options).save().then(function() {
                                        // Restore the action buttons after PDF generation
                                        actionButtons.style.display = 'flex';
                                    });
                                } catch (error) {
                                    console.error("Error generating PDF:", error);
                                    alert("There was an error generating the PDF. Please try again.");
                                }
                            }
                        <\/script>
                    </body>
                    </html>
                `;
                
                // Open a new window and write content
                const receiptWin = window.open('', '_blank', 'width=600,height=700');
                if (!receiptWin) {
                    alert("Popup blocker may be preventing the receipt from opening. Please allow popups for this site.");
                    return;
                }
                
                receiptWin.document.open();
                receiptWin.document.write(receiptContent);
                receiptWin.document.close();
            } catch (error) {
                console.error("Error opening receipt:", error);
                alert("There was an error opening the receipt. Please try again.");
            }
        }

        // Check if we're on mobile and hide sidebar by default
        function checkMobileView() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth < 768 && sidebar) {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('transform-none');
            } else if (sidebar) {
                sidebar.classList.remove('-translate-x-full');
            }
        }
        
        // Run on resize
        window.addEventListener('resize', checkMobileView);
        
        function setActiveButton(button) {
            const allBtn = document.getElementById('all-orders-btn');
            const activeBtn = document.getElementById('active-orders-btn');
            const completedBtn = document.getElementById('completed-orders-btn');
            const cancelledBtn = document.getElementById('cancelled-orders-btn');
            
            [allBtn, activeBtn, completedBtn, cancelledBtn].forEach(btn => {
                if (btn) {
                    btn.classList.remove('bg-primary', 'text-white');
                    btn.classList.add('bg-white', 'text-gray-700');
                }
            });
            
            button.classList.remove('bg-white', 'text-gray-700');
            button.classList.add('bg-primary', 'text-white');
        }
        
        function filterOrders() {
            const searchInput = document.getElementById('search-orders');
            const orderItems = document.querySelectorAll('.order-item');
            const searchText = searchInput ? searchInput.value.toLowerCase() : '';
            const activeFilter = document.querySelector('.px-4.py-2.bg-primary.text-white') ? 
                                 document.querySelector('.px-4.py-2.bg-primary.text-white').id : 'all-orders-btn';
            
            orderItems.forEach(item => {
                const status = item.getAttribute('data-status');
                const orderNumber = item.getAttribute('data-order-number').toLowerCase();
                const seller = item.getAttribute('data-seller').toLowerCase();
                
                let showItem = true;
                
                // Filter by status
                if (activeFilter === 'active-orders-btn' && status !== 'pending' && status !== 'ready') {
                    showItem = false;
                } else if (activeFilter === 'completed-orders-btn' && status !== 'completed') {
                    showItem = false;
                } else if (activeFilter === 'cancelled-orders-btn' && status !== 'cancelled') {
                    showItem = false;
                }
                
                // Filter by search text
                if (searchText && !orderNumber.includes(searchText) && !seller.includes(searchText)) {
                    showItem = false;
                }
                
                // Show/hide the item
                if (showItem) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }
        
        // Initialize countdown timers for pending orders
        function initializeCountdownTimers() {
            const countdownElements = document.querySelectorAll('.pickup-countdown');
            
            countdownElements.forEach(element => {
                const orderId = element.getAttribute('data-order-id');
                
                // First verify with the server how much time is actually remaining
                $.ajax({
                    url: "{{ route('orders.check-remaining-time') }}",
                    type: "POST",
                    data: {
                        order_id: orderId,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (!response.success) {
                            console.error('Failed to check remaining time:', response.message);
                            return;
                        }
                        
                        console.log('Timer response for order #' + response.order_number + ':', response);
                        console.log('Remaining seconds:', response.remaining_seconds);
                        console.log('Should cancel:', response.should_cancel);
                        
                        // If remaining seconds is more than 15 minutes (900 seconds), something is wrong
                        if (response.remaining_seconds > 900) {
                            console.log('Invalid remaining seconds (>15 minutes), marking as expired');
                            element.textContent = 'Expired!';
                            element.classList.add('text-red-600', 'font-bold');
                            
                            // Send auto-cancel request
                            $.ajax({
                                url: "{{ route('orders.auto-cancel') }}",
                                type: "POST",
                                data: {
                                    order_id: orderId,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(cancelResponse) {
                                    console.log('Auto-cancel response for invalid timer:', cancelResponse);
                                    if (cancelResponse.success) {
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 1000);
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Error auto-cancelling order:', error);
                                }
                            });
                            
                            return; // Skip setting up the timer
                        }
                        
                        // If the order should be cancelled automatically
                        if (response.should_cancel) {
                            console.log('Order should be cancelled, showing Expired!');
                            element.textContent = 'Expired!';
                            element.classList.add('text-red-600', 'font-bold');
                            
                            // Find the order item and update its appearance
                            const orderItem = element.closest('.order-item');
                            if (orderItem) {
                                const statusBadge = orderItem.querySelector('.rounded-full:not(.pickup-timer-badge)');
                                if (statusBadge) {
                                    statusBadge.classList.remove('bg-blue-100', 'text-blue-800');
                                    statusBadge.classList.add('bg-red-100', 'text-red-800');
                                    statusBadge.textContent = 'Cancelled';
                                }
                                
                                // Manually send auto-cancel request
                                $.ajax({
                                    url: "{{ route('orders.auto-cancel') }}",
                                    type: "POST",
                                    data: {
                                        order_id: orderId,
                                        _token: $('meta[name="csrf-token"]').attr('content')
                                    },
                                    success: function(cancelResponse) {
                                        console.log('Auto-cancel response:', cancelResponse);
                                        if (cancelResponse.success) {
                                            // Reload the page to show the updated status
                                            setTimeout(() => {
                                                window.location.reload();
                                            }, 1000);
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Error auto-cancelling order:', error);
                                    }
                                });
                                
                                return; // Skip setting up the timer
                            }
                        }
                        
                        // Set the initial remaining seconds from server response
                        let remainingSeconds = response.remaining_seconds;
                        
                        // Ensure remaining seconds is not negative and not more than 15 minutes
                        if (remainingSeconds < 0 || remainingSeconds > 900) {
                            console.log('Invalid remaining seconds from server:', remainingSeconds);
                            // Calculate client-side as fallback
                            const orderCreated = new Date(element.getAttribute('data-created-at'));
                            const now = new Date();
                            const elapsedSeconds = Math.floor((now - orderCreated) / 1000);
                            remainingSeconds = Math.max(0, 900 - elapsedSeconds);
                            console.log('Using client-side calculation:', remainingSeconds);
                        }
                        
                        // If the order is older than 15 minutes, mark it as expired
                        if (remainingSeconds <= 0) {
                            console.log('Order is expired, showing Expired!');
                            element.textContent = 'Expired!';
                            element.classList.add('text-red-600', 'font-bold');
                            return; // Don't set up the timer
                        }
                        
                        // Update the display
                        const minutes = Math.floor(remainingSeconds / 60);
                        const seconds = remainingSeconds % 60;
                        element.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                        
                        // Apply styling based on remaining time
                        if (remainingSeconds <= 60) { // Show warning in last minute
                            const badge = element.closest('.pickup-timer-badge');
                            if (badge) {
                                badge.classList.remove('bg-red-100', 'text-red-800');
                                badge.classList.add('bg-red-500', 'text-white', 'animate-pulse');
                            }
                        }
                        
                        // Update the timer every second
                        const timerId = setInterval(() => {
                            try {
                                remainingSeconds--;
                                
                                // Ensure remaining seconds is not negative
                                if (remainingSeconds < 0) {
                                    remainingSeconds = 0;
                                }
                                
                                // Update the display
                                const minutes = Math.floor(remainingSeconds / 60);
                                const seconds = remainingSeconds % 60;
                                element.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                                
                                // Apply styling based on remaining time
                                if (remainingSeconds <= 60) { // Show warning in last minute
                                    const badge = element.closest('.pickup-timer-badge');
                                    if (badge) {
                                        badge.classList.remove('bg-red-100', 'text-red-800');
                                        badge.classList.add('bg-red-500', 'text-white', 'animate-pulse');
                                    }
                                }
                                
                                // Clear the interval when time is up
                                if (remainingSeconds <= 0) {
                                    clearInterval(timerId);
                                    element.textContent = 'Expired!';
                                    
                                    // Find the order item and update its appearance
                                    const orderItem = element.closest('.order-item');
                                    if (orderItem) {
                                        const statusBadge = orderItem.querySelector('.rounded-full:not(.pickup-timer-badge)');
                                        if (statusBadge) {
                                            statusBadge.classList.remove('bg-blue-100', 'text-blue-800');
                                            statusBadge.classList.add('bg-red-100', 'text-red-800');
                                            statusBadge.textContent = 'Cancelled';
                                        }
                                        
                                        // Manually send auto-cancel request
                                        $.ajax({
                                            url: "{{ route('orders.auto-cancel') }}",
                                            type: "POST",
                                            data: {
                                                order_id: orderId,
                                                _token: $('meta[name="csrf-token"]').attr('content')
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    // Show a message to the user
                                                    Swal.fire({
                                                        title: 'Order Auto-Cancelled',
                                                        text: 'Your order has been automatically cancelled because it was not picked up within 15 minutes.',
                                                        icon: 'warning',
                                                        confirmButtonText: 'OK',
                                                        confirmButtonColor: '#4F46E5'
                                                    }).then(() => {
                                                        // Reload the page to show the updated status
                                                        window.location.reload();
                                                    });
                                                }
                                            },
                                            error: function(xhr, status, error) {
                                                console.error('Error auto-cancelling order:', error);
                                            }
                                        });
                                    }
                                }
                            } catch (error) {
                                console.error('Error in countdown timer:', error);
                                clearInterval(timerId);
                            }
                        }, 1000);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error checking remaining time:', error);
                    }
                });
            });
        }
        
        // Function to open order details modal - No longer needed, keeping for reference
        /*
        function openOrderDetailsModal(orderId) {
            // Show loading indicator
            Swal.fire({
                title: 'Loading...',
                text: 'Fetching order details',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Fetch order details
            $.ajax({
                url: `/orders/${orderId}`,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html' 
                },
                type: 'GET',
                success: function(response) {
                    Swal.close();
                    
                    // Create modal container
                    const modalContainer = document.createElement('div');
                    modalContainer.className = 'fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] p-4';
                    modalContainer.id = 'order-details-modal';
                    
                    // Create modal content wrapper
                    const modalContent = document.createElement('div');
                    modalContent.className = 'bg-white rounded-xl w-full max-w-3xl mx-5 overflow-y-auto max-h-[90vh] shadow-xl';
                    
                    // Insert the response HTML (from modal.blade.php) into the content wrapper
                    modalContent.innerHTML = response;
                    
                    // Add close button at the top right corner
                    const closeBtn = document.createElement('button');
                    closeBtn.className = 'absolute top-3 right-3 text-white hover:text-gray-300 p-2 rounded-full bg-black/40 hover:bg-black/60 transition-all duration-200 transform hover:scale-110 shadow-lg z-[10000]';
                    closeBtn.innerHTML = '<iconify-icon icon="mdi:close" width="24" height="24"></iconify-icon>';
                    closeBtn.id = 'close-modal-btn';
                    
                    // Append modal content to container
                    modalContainer.appendChild(modalContent);
                    modalContainer.appendChild(closeBtn);
                    
                    // Add animation class to the modal
                    modalContent.classList.add('animate-modalIn');
                    
                    // Add to document
                    document.body.appendChild(modalContainer);
                    document.body.style.overflow = 'hidden'; // Prevent scrolling
                    
                    // Setup modal interactions
                    setupModalInteractions(modalContainer, closeBtn);
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Could not load order details. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3B82F6'
                    });
                }
            });
        }
        
        // Set up modal interactions - No longer needed, keeping for reference
        function setupModalInteractions(modalContainer, closeBtn) {
            // Close modal button click handler
            const closeModalBtn = modalContainer.querySelector('#close-order-modal');
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function() {
                    // Add fade out animation
                    modalContainer.classList.add('opacity-0');
                    setTimeout(() => {
                        if (document.body.contains(modalContainer)) {
                            document.body.removeChild(modalContainer);
                            document.body.style.overflow = 'auto';
                        }
                    }, 200);
                });
            }
            
            // Setup cancel order button click handler if present
            const cancelBtn = modalContainer.querySelector('.cancel-order-btn');
            if (cancelBtn) {
                const cancelForm = cancelBtn.closest('form');
                cancelForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Cancel Order',
                        text: 'Are you sure you want to cancel this order?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, cancel it',
                        cancelButtonText: 'No, keep it',
                        confirmButtonColor: '#EF4444',
                        cancelButtonColor: '#3B82F6',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Submit the form
                            fetch(cancelForm.action, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: new URLSearchParams(new FormData(cancelForm))
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('Cancelled!', 'Your order has been cancelled.', 'success')
                                    .then(() => {
                                        // Close the modal
                                        document.body.removeChild(modalContainer);
                                        document.body.style.overflow = 'auto';
                                        
                                        // Reload the page to show updated status
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Error', data.message || 'Could not cancel the order.', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
                            });
                        }
                    });
                });
            }
            
            // Close button functionality
            closeBtn.addEventListener('click', () => {
                // Add fade out animation
                modalContainer.classList.add('opacity-0');
                setTimeout(() => {
                    if (document.body.contains(modalContainer)) {
                        document.body.removeChild(modalContainer);
                        document.body.style.overflow = 'auto';
                    }
                }, 200);
            });
            
            // Close on background click
            modalContainer.addEventListener('click', (e) => {
                if (e.target === modalContainer) {
                    // Add fade out animation
                    modalContainer.classList.add('opacity-0');
                    setTimeout(() => {
                        if (document.body.contains(modalContainer)) {
                            document.body.removeChild(modalContainer);
                            document.body.style.overflow = 'auto';
                        }
                    }, 200);
                }
            });
            
            // Close on ESC key
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') {
                    if (document.body.contains(modalContainer)) {
                        // Add fade out animation
                        modalContainer.classList.add('opacity-0');
                        setTimeout(() => {
                            if (document.body.contains(modalContainer)) {
                                document.body.removeChild(modalContainer);
                                document.body.style.overflow = 'auto';
                            }
                        }, 200);
                    }
                    document.removeEventListener('keydown', escHandler);
                }
            });
        }
        */
    </script>
</body>
</html>
