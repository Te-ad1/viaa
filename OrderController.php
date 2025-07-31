<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of the user's orders.
     */
    public function index()
    {
        $user = Auth::user();
        
        // If user is a seller, redirect to seller orders page
        if ($user->role === 'seller') {
            return redirect()->route('seller.orders.index')
                ->with('info', 'Please use the seller dashboard to view orders.');
        }
        
        $orders = Order::where('student_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->with(['seller', 'orderItems.menuItem'])
            ->paginate(10);
            
        return view('orders.index', compact('orders'));
    }

    /**
     * Create notification for pending orders to remind customers to pay
     *
     * @param Order $order
     * @return void
     */
    private function createPendingOrderNotification($order)
    {
        try {
            $notificationId = Str::uuid()->toString();
            $notificationInserted = DB::table('notifications')->insert([
                'id' => $notificationId,
                'type' => 'App\\Notifications\\OrderPending',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $order->student_id,
                'data' => json_encode([
                    'order_id' => $order->order_id,
                    'order_number' => $order->order_number,
                    'message' => 'Please proceed to the counter to settle your payment in order to confirm your order. Unpaid orders will be cancelled within 15 minutes.',
                    'type' => 'order_pending'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            \Log::info('Added pending order notification for user #' . $order->student_id . ', result: ' . ($notificationInserted ? 'success' : 'failed'));
        } catch (\Exception $e) {
            \Log::error('Failed to create pending order notification: ' . $e->getMessage());
        }
    }

    /**
     * Create notification for cancelled orders.
     *
     * @param Order $order
     * @return void
     */
    private function createCancelledOrderNotification($order)
    {
        try {
            $notificationId = Str::uuid()->toString();
            $notificationInserted = DB::table('notifications')->insert([
                'id' => $notificationId,
                'type' => 'App\\Notifications\\OrderCancelled',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $order->student_id,
                'data' => json_encode([
                    'order_id' => $order->order_id,
                    'order_number' => $order->order_number,
                    'message' => 'Your order has been cancelled due to non-payment. Please proceed to the counter to settle your payment.',
                    'type' => 'order_cancelled'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            \Log::info('Added cancelled order notification for user #' . $order->student_id . ', result: ' . ($notificationInserted ? 'success' : 'failed'));
        } catch (\Exception $e) {
            \Log::error('Failed to create cancelled order notification: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'spot_number' => 'nullable|string|max:20',
            'payment_method' => 'nullable|in:cash,credit,wallet'
        ]);
        
        try {
            DB::beginTransaction();
            
            $user = Auth::user();
            
            // Clean up expired pending orders first
            $expiredOrders = DB::table('orders')
                ->where('student_id', $user->user_id)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(15)) // Orders older than 15 minutes
                ->get();
                
            foreach ($expiredOrders as $expiredOrder) {
                // Update the order status to cancelled
                DB::table('orders')
                    ->where('order_id', $expiredOrder->order_id)
                    ->update(['status' => 'cancelled']);
                    
                // Return items to inventory
                $orderItems = DB::table('orderitems')
                    ->where('order_id', $expiredOrder->order_id)
                    ->get();
                    
                foreach ($orderItems as $item) {
                    DB::table('menuitems')
                        ->where('item_id', $item->item_id)
                        ->increment('available_stock', $item->quantity);
                }
                
                \Log::info("Auto-cancelled expired order #{$expiredOrder->order_number}");
            }
            
            // Check if user has any valid pending orders (not expired)
            $validPendingOrdersCount = DB::table('orders')
                ->where('student_id', $user->user_id)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subMinutes(15)) // Only count orders created within last 15 minutes
                ->count();
                
            if ($validPendingOrdersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have pending orders. Please wait for them to be completed before placing a new order.'
                ], 400);
            }
            
            // Get the cart for the user
            $cart = DB::table('carts')
                ->where('user_id', $user->user_id)
                ->first();
                
            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your cart is empty'
                ], 400);
            }
            
            // Get cart items
            $cartItems = DB::table('cart_items')
                ->where('cart_id', $cart->cart_id)
                ->get();
                
            if ($cartItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your cart is empty'
                ], 400);
            }
            
            // Check if total items exceed limit
            $totalQuantity = $cartItems->sum('quantity');
            if ($totalQuantity > 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only order up to a maximum of 3 items per transaction. Please complete your current order first.'
                ], 400);
            }
            
            // Check stock availability for all items
            foreach ($cartItems as $item) {
                $menuItem = DB::table('menuitems')
                    ->where('item_id', $item->item_id)
                    ->first();
                    
                if (!$menuItem) {
                    return response()->json([
                        'success' => false,
                        'message' => "Item '{$item->item_name}' is no longer available."
                    ], 400);
                }
                
                if (!$menuItem->is_available) {
                    return response()->json([
                        'success' => false,
                        'message' => "'{$item->item_name}' is currently unavailable."
                    ], 400);
                }
            }

            // Get information about each menu item
            $enrichedCartItems = [];
            foreach ($cartItems as $item) {
                $menuItem = DB::table('menuitems')
                    ->where('item_id', $item->item_id)
                    ->first();
                    
                $enrichedItem = clone $item;
                $enrichedItem->seller_id = $menuItem->seller_id;
                $enrichedCartItems[] = $enrichedItem;
            }
            
            // Group items by seller
            $itemsBySeller = collect($enrichedCartItems)->groupBy('seller_id');
            
            // Don't verify stock - allow all orders to proceed
            
            $orders = [];
            
            // Create an order for each seller
            foreach ($itemsBySeller as $sellerId => $items) {
                $totalAmount = $items->sum(function ($item) {
                    return $item->price * $item->quantity;
                });
                
                // If spot_number wasn't provided, generate one
                $spotNumber = $validated['spot_number'] ?? 'Table-' . rand(1, 100);
                
                // Generate order number with sequential number format ORD-XXX
                $lastOrder = DB::table('orders')->latest('order_id')->first();
                $lastOrderNumber = 0;
                
                if ($lastOrder) {
                    // Extract the numeric part of the last order number
                    preg_match('/ORD-(\d+)/', $lastOrder->order_number, $matches);
                    if (isset($matches[1])) {
                        $lastOrderNumber = (int)$matches[1];
                    }
                }
                
                // Generate next order number with zero-padding
                $orderNumber = 'ORD-' . str_pad($lastOrderNumber + 1, 3, '0', STR_PAD_LEFT);
                
                // Create the order using Laravel's automatic timestamp handling
                $orderId = DB::table('orders')->insertGetId([
                    'student_id' => $user->user_id,
                    'seller_id' => $sellerId,
                    'order_number' => $orderNumber,
                    'total_amount' => $totalAmount,
                    'spot_number' => $spotNumber,
                    'status' => 'pending',
                    'created_at' => now()
                ]);
                
                // Get the created order from database to ensure all timestamps are correct
                $order = Order::find($orderId);
                
                // Add the order items directly with DB
                foreach ($items as $item) {
                    $subtotal = $item->price * $item->quantity;
                    
                    DB::table('orderitems')->insert([
                        'order_id' => $orderId,
                        'item_id' => $item->item_id,
                        'quantity' => $item->quantity,
                        'subtotal' => $subtotal
                    ]);
                    
                    // Update inventory - decrease available_stock
                    DB::table('menuitems')
                        ->where('item_id', $item->item_id)
                        ->decrement('available_stock', $item->quantity);
                }
                
                $orders[] = $order;
                
                // Create notification for pending order
                $this->createPendingOrderNotification($order);
            }
            
            // Clear the cart
            DB::table('cart_items')->where('cart_id', $cart->cart_id)->delete();
            DB::table('carts')->where('cart_id', $cart->cart_id)->update(['total_amount' => 0]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'order_number' => $orderNumber,
                'orders' => $orders
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detailed error
            \Log::error('Order creation failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = 'Failed to place order: ' . $e->getMessage();
            
            // Check for unique constraint violation on order_number
            if (strpos($e->getMessage(), 'order_number') !== false && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errorMessage = 'System error: Duplicate order number generated. Please try again.';
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }
    }

    /**
     * Display the specified order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = Auth::user();
        $order = Order::with(['orderItems.menuItem', 'seller', 'student'])
            ->findOrFail($id);
        
        // Check if this order belongs to the authenticated student
        if (auth()->user()->user_id !== $order->student_id) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this order.'
                ], 403);
            }
            
            return redirect()->route('orders.index')
                ->with('error', 'You are not authorized to view this order.');
        }
        
        // If this is an AJAX request
        if (request()->ajax()) {
            // Check if JSON response is explicitly requested
            if (request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest' && request()->header('Accept') === 'application/json') {
                // Format order items with image URLs
                $formattedItems = $order->orderItems->map(function($item) {
                    // Add logging
                    \Log::debug("Item ID: {$item->item_id}, Name: " . ($item->menuItem ? $item->menuItem->item_name : 'Unknown') . ", Image: " . ($item->menuItem ? $item->menuItem->image_url : 'No image'));
                    
                    return [
                        'item_id' => $item->item_id,
                        'item_name' => $item->menuItem ? $item->menuItem->item_name : 'Unknown Item',
                        'description' => $item->menuItem ? $item->menuItem->description : '',
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                        'image_url' => $item->menuItem ? $item->menuItem->image_url : null
                    ];
                });

                // Format the order for JSON response
                $formattedOrder = [
                    'order_id' => $order->order_id,
                    'order_number' => $order->order_number,
                    'created_at' => $order->created_at ? $order->created_at->format('M d, Y h:i A') : now()->format('M d, Y h:i A'),
                    'status' => ucfirst($order->status),
                    'total_amount' => number_format($order->total_amount, 2),
                    'spot_number' => $order->spot_number,
                    'customer_name' => $order->student ? $order->student->firstname . ' ' . $order->student->lastname : 'Unknown',
                    'student_number' => $order->student ? $order->student->student_number : 'Unknown',
                    'seller' => $order->seller ? $order->seller->stall_name : 'Unknown Seller',
                    'items' => $formattedItems
                ];

                return response()->json([
                    'success' => true,
                    'order' => $formattedOrder
                ]);
            }
            
            // For regular AJAX requests (not JSON), return the HTML content for the modal
            return view('orders.modal', compact('order'))->render();
        }
        
        // For regular requests, return the full view
        return view('orders.show', compact('order'));
    }

    /**
     * Display a listing of orders for the seller.
     *
     * @return \Illuminate\Http\Response
     */
    public function sellerIndex()
    {
        $user = auth()->user();
        
        if ($user->role !== 'seller') {
            return redirect()->route('dashboard')->with('error', 'You do not have access to seller orders.');
        }
        
        $seller = \App\Models\Seller::where('user_id', $user->user_id)->first();
        
        if (!$seller) {
            return redirect()->route('dashboard')->with('error', 'Seller profile not found.');
        }
        
        // If request is just for the count for AJAX updates
        if (request()->has('count_only') && request('count_only') == 'true') {
            $pendingCount = \App\Models\Order::where('seller_id', $seller->seller_id)
                ->where('status', 'pending')
                ->count();
                
            return response()->json([
                'success' => true,
                'pending_count' => $pendingCount
            ]);
        }
        
        // Get orders with optional status filter
        $query = \App\Models\Order::where('seller_id', $seller->seller_id);
        
        // Apply status filter if specified
        if (request('status') && in_array(request('status'), ['pending', 'confirm', 'ready', 'completed', 'cancelled'])) {
            $query->where('status', request('status'));
        }
        
        // Search by order number if provided
        if (request()->has('search') && !empty(request('search'))) {
            $search = request('search');
            $query->where('order_number', 'like', '%' . $search . '%');
        }
        
        $orders = $query->orderBy('created_at', 'desc')
            ->with(['student', 'orderItems'])
            ->paginate(20);
        
        return view('seller.orders.index', compact('orders', 'seller'));
    }
    
    /**
     * Display the specified order for the seller.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function sellerShow($id)
    {
        $user = auth()->user();
        
        if ($user->role !== 'seller') {
            return redirect()->route('dashboard')->with('error', 'You do not have access to seller orders.');
        }
        
        $seller = \App\Models\Seller::where('user_id', $user->user_id)->first();
        
        if (!$seller) {
            return redirect()->route('dashboard')->with('error', 'Seller profile not found.');
        }
        
        $order = \App\Models\Order::where('order_id', $id)
            ->where('seller_id', $seller->seller_id)
            ->with(['student', 'orderItems', 'orderItems.menuItem'])
            ->first();
        
        if (!$order) {
            return redirect()->route('seller.orders.index')->with('error', 'Order not found or you do not have permission to view it.');
        }
        
        return view('seller.orders.show', compact('order', 'seller'));
    }
    
    /**
     * Update the order status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        
        if ($user->role !== 'seller') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to update order status.'], 403);
            }
            return redirect()->route('dashboard')->with('error', 'You do not have permission to update order status.');
        }
        
        $seller = \App\Models\Seller::where('user_id', $user->user_id)->first();
        
        if (!$seller) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Seller profile not found.'], 404);
            }
            return redirect()->route('dashboard')->with('error', 'Seller profile not found.');
        }
        
        $order = \App\Models\Order::where('order_id', $id)
            ->where('seller_id', $seller->seller_id)
            ->with('student') // Load the student relationship
            ->first();
        
        if (!$order) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Order not found or you do not have permission to update it.'], 404);
            }
            return redirect()->route('seller.orders.index')->with('error', 'Order not found or you do not have permission to update it.');
        }
        
        $request->validate([
            'status' => 'required|in:pending,confirm,ready,completed,cancelled',
        ]);
        
        // Get the previous status
        $oldStatus = $order->status;
        
        // Update the status
        $order->status = $request->status;
        
        // If the order is marked as confirmed, set the confirmed_at timestamp
        if ($request->status === 'confirm' && $oldStatus !== 'confirm') {
            $order->confirmed_at = now();
            \Log::info('Order #' . $order->order_number . ' marked as confirmed at ' . $order->confirmed_at);
        }
        
        // If the order is marked as ready for pickup, set the ready_since timestamp
        if ($request->status === 'ready' && $oldStatus !== 'ready') {
            $order->ready_since = now();
            \Log::info('Order #' . $order->order_number . ' marked as ready at ' . $order->ready_since);
        }
        
        $order->save();
        
        // If the order is marked as ready for pickup
        if ($request->status === 'ready' && $oldStatus !== 'ready') {
            // Send notification to student
            try {
                // Direct insert into notifications table to bypass any queue issues during development
                \DB::table('notifications')->insert([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\\Notifications\\OrderReadyForPickup',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $order->student_id,
                    'data' => json_encode([
                        'order_id' => $order->order_id,
                        'order_number' => $order->order_number,
                        'seller_name' => $order->seller->stall_name ?? 'Seller #' . $order->seller_id,
                        'message' => 'Your order is ready for pickup! Please collect it.',
                        'type' => 'ready_for_pickup',
                        'pickup_by' => now()->addSeconds(900)->toDateTimeString(),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Schedule job to auto-cancel if not picked up within 15 minutes
                \App\Jobs\CancelUnpickedOrder::dispatch($order->order_id)
                    ->delay(now()->addSeconds(900));
                
                // Return JSON for AJAX requests
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true, 
                        'message' => 'Order status has been updated to Ready for Pickup. The customer has been notified.',
                        'status' => 'ready'
                    ]);
                }
                    
                // Add an extra success message
                return redirect()->route('seller.orders.show', $order->order_id)
                    ->with('success', 'Order status has been updated to Ready for Pickup. The customer has been notified and has 15 minutes to pick up the order.');
            } catch (\Exception $e) {
                \Log::error('Failed to process ready order notification: ' . $e->getMessage(), [
                    'order_id' => $id,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Return JSON for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true, 
                'message' => 'Order status has been updated to ' . ucfirst($order->status),
                'status' => $order->status
            ]);
        }
        
        return redirect()->route('seller.orders.show', $order->order_id)
            ->with('success', 'Order status has been updated to ' . ucfirst($order->status));
    }

    /**
     * Cancel a pending order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        $user = Auth::user();
        $order = Order::findOrFail($id);
        
        // Check if this order belongs to the current user
        if ($order->student_id !== $user->user_id) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to cancel this order'
                ], 403);
            }
            
            return redirect()->route('orders.index')
                ->with('error', 'You do not have permission to cancel this order');
        }
        
        // Check if the order can be cancelled (only pending orders)
        if ($order->status !== 'pending') {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be cancelled'
                ], 400);
            }
            
            return redirect()->route('orders.show', $id)
                ->with('error', 'Only pending orders can be cancelled');
        }
        
        try {
            DB::beginTransaction();
            
            // Update the order status to 'cancelled'
            DB::table('orders')
                ->where('order_id', $order->order_id)
                ->update(['status' => 'cancelled']);
            
            // Return items to inventory
            foreach ($order->orderItems as $item) {
                // Get the menu item
                $menuItem = DB::table('menuitems')
                    ->where('item_id', $item->item_id)
                    ->first();
                
                if ($menuItem) {
                    // Increase the available stock
                    DB::table('menuitems')
                        ->where('item_id', $item->item_id)
                        ->increment('available_stock', $item->quantity);
                }
            }
            
            DB::commit();
            
            // Create notification for cancelled order
            $this->createCancelledOrderNotification($order);
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order cancelled successfully'
                ]);
            }
            
            return redirect()->route('orders.index')
                ->with('success', 'Order cancelled successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detailed error
            \Log::error('Order cancellation failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'order_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel order: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('orders.show', $id)
                ->with('error', 'Failed to cancel order: ' . $e->getMessage());
        }
    }

    /**
     * Check if the user has any pending orders
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPending()
    {
        try {
            $user = Auth::user();
            
            $pendingOrdersCount = DB::table('orders')
                ->where('student_id', $user->user_id)
                ->where('status', 'pending')
                ->count();
                
            return response()->json([
                'success' => true,
                'has_pending_orders' => $pendingOrdersCount > 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check pending orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for orders that are ready for pickup
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkReadyForPickup()
    {
        try {
            $user = Auth::user();
            \Log::info('Checking ready for pickup orders for user #' . $user->user_id);
            
            $readyOrders = DB::table('orders')
                ->where('student_id', $user->user_id)
                ->where('status', 'ready')
                ->select('order_id', 'order_number', 'ready_since')
                ->get();
            
            // Log each ready order for debugging
            if ($readyOrders->count() > 0) {
                \Log::info('Found ' . $readyOrders->count() . ' ready orders:');
                foreach ($readyOrders as $order) {
                    $readySince = \Carbon\Carbon::parse($order->ready_since);
                    $now = now();
                    $elapsedSeconds = $now->timestamp - $readySince->timestamp;
                    $remainingSeconds = max(0, 60 - $elapsedSeconds);
                    
                    \Log::info("Order #{$order->order_number}: ready since {$order->ready_since}, {$elapsedSeconds}s ago, {$remainingSeconds}s remaining");
                }
            } else {
                \Log::info('No ready orders found for user #' . $user->user_id);
            }
                
            return response()->json([
                'success' => true,
                'ready_orders' => $readyOrders
            ]);
        } catch (\Exception $e) {
            \Log::error('Error checking ready orders: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check ready orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Automatically cancel an order if not picked up
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function autoCancel(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            \Log::info('Auto-cancel request received for order ID: ' . $orderId);
            
            $order = Order::with('orderItems')->findOrFail($orderId);
            \Log::info('Found order: #' . $order->order_number . ' in status: ' . $order->status);
            
            // Only auto-cancel orders that are in 'pending' status
            if ($order->status !== 'pending') {
                \Log::warning('Cannot auto-cancel order #' . $order->order_number . ' - not in pending status (current status: ' . $order->status . ')');
                return response()->json([
                    'success' => false,
                    'message' => 'Order is not in pending status'
                ]);
            }
            
            DB::beginTransaction();
            
            // Update the order status to 'cancelled'
            $updated = DB::table('orders')
                ->where('order_id', $order->order_id)
                ->update([
                    'status' => 'cancelled'
                ]);
                
            \Log::info('Updated order status to cancelled, result: ' . ($updated ? 'success' : 'failed'));
            
            // Return items to inventory
            foreach ($order->orderItems as $item) {
                // Get the menu item
                $menuItem = DB::table('menuitems')
                    ->where('item_id', $item->item_id)
                    ->first();
                
                if ($menuItem) {
                    // Increase the available stock
                    $stockUpdated = DB::table('menuitems')
                        ->where('item_id', $item->item_id)
                        ->increment('available_stock', $item->quantity);
                        
                    \Log::info('Returned ' . $item->quantity . ' of item #' . $item->item_id . ' to inventory, result: ' . ($stockUpdated ? 'success' : 'failed'));
                }
            }
            
            // Add notification for the user
            $notificationId = Str::uuid()->toString();
            $notificationInserted = DB::table('notifications')->insert([
                'id' => $notificationId,
                'type' => 'App\\Notifications\\OrderPending',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $order->student_id,
                'data' => json_encode([
                    'order_id' => $order->order_id,
                    'order_number' => $order->order_number,
                    'message' => 'Please proceed to the counter to settle your payment in order to confirm your order. Unpaid orders will be cancelled within 15 minutes.',
                    'type' => 'order_pending'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            \Log::info('Added notification for user #' . $order->student_id . ', result: ' . ($notificationInserted ? 'success' : 'failed'));
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order auto-cancelled successfully',
                'order_number' => $order->order_number
            ]);
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detailed error
            \Log::error('Order auto-cancellation failed: ' . $e->getMessage(), [
                'order_id' => $request->input('order_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to auto-cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check remaining time before auto-cancellation for a specific order
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkRemainingTime(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            
            $order = Order::findOrFail($orderId);
            
            // Only calculate for orders in 'pending' status
            if ($order->status !== 'pending') {
                return response()->json([
                    'success' => true,
                    'should_cancel' => false,
                    'remaining_seconds' => 0,
                    'message' => 'Order is not in pending status'
                ]);
            }
            
            // Calculate seconds remaining based on when the order was created
            $orderCreated = \Carbon\Carbon::parse($order->created_at);
            $now = now();
            $elapsedSeconds = $now->timestamp - $orderCreated->timestamp;
            $remainingSeconds = max(0, 900 - $elapsedSeconds); // 15 minutes = 900 seconds
            
            // If the order is older than 15 minutes, it should be cancelled
            $shouldCancel = $elapsedSeconds >= 900;
            
            // Log for debugging
            \Log::info("Order #{$order->order_number} time check: created at {$orderCreated}, {$elapsedSeconds}s elapsed, {$remainingSeconds}s remaining, should cancel: " . ($shouldCancel ? 'yes' : 'no'));
            \Log::info("Current time: {$now}, Order created: {$orderCreated}, Difference: {$elapsedSeconds} seconds");
            \Log::info("Raw created_at from database: " . $order->created_at);
            \Log::info("Order ID: {$order->order_id}, Status: {$order->status}");
            
            // Additional safety check - if elapsed time is more than 1 hour, definitely cancel
            if ($elapsedSeconds > 3600) {
                $shouldCancel = true;
                $remainingSeconds = 0;
                \Log::warning("Order #{$order->order_number} is more than 1 hour old, forcing cancellation");
            }
            
            // Prevent auto-cancellation for orders created less than 5 minutes ago (safety buffer)
            if ($elapsedSeconds < 300) {
                $shouldCancel = false;
                \Log::info("Order #{$order->order_number} is less than 5 minutes old, preventing auto-cancellation");
            }
            
            return response()->json([
                'success' => true,
                'should_cancel' => $shouldCancel,
                'remaining_seconds' => $remainingSeconds,
                'elapsed_seconds' => $elapsedSeconds,
                'order_number' => $order->order_number
            ]);
        } catch (\Exception $e) {
            \Log::error('Error checking remaining time: ' . $e->getMessage(), [
                'order_id' => $request->input('order_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check remaining time: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order details for the modal view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getDetails(Request $request)
    {
        $user = Auth::user();
        
        // If user is a seller, redirect to seller orders page
        if ($user->role === 'seller') {
            return response()->json(['error' => 'Seller cannot view student orders'], 403);
        }
        
        $id = $request->input('order_id');
        $order = Order::with(['seller', 'orderItems.menuItem'])
            ->findOrFail($id);
        
        // Check if this order belongs to the current user
        if ($order->student_id !== $user->user_id) {
            return response()->json(['error' => 'You do not have permission to view this order'], 403);
        }
        
        // Return the order details HTML partial
        return view('orders.partials.details-modal', compact('order'))->render();
    }

    /**
     * Temporary method to view all menu items and their images
     */
    public function debugMenuItems()
    {
        $items = \App\Models\MenuItem::all(['item_id', 'item_name', 'image_url']);
        
        // Output as JSON for easy inspection
        return response()->json([
            'menu_items' => $items
        ]);
    }

    /**
     * Display a receipt for the specified order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function receipt($id)
    {
        $user = Auth::user();
        $order = Order::with(['orderItems.menuItem', 'seller', 'student'])
            ->findOrFail($id);
        
        // Check if this order belongs to the authenticated user
        if (auth()->user()->user_id !== $order->student_id && auth()->user()->user_id !== $order->seller_id) {
            return redirect()->route('orders.index')
                ->with('error', 'You do not have permission to view this receipt');
        }
        
        return view('orders.receipt', compact('order'));
    }

    /**
     * Check order status for countdown timer
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkOrderStatus(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $order = Order::findOrFail($orderId);
            
            // Check if this order belongs to the authenticated user
            if (auth()->user()->user_id !== $order->student_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this order.'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'status' => $order->status,
                'order_number' => $order->order_number
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check order status: ' . $e->getMessage()
            ], 500);
        }
    }
} 
