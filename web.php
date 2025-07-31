<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EarningController;
use App\Http\Controllers\FeedbackController;

Route::get('/', function () {
    return view('canteen');
})->name('home');

// Terms and Privacy Policy route
Route::get('/terms-policy', function () {
    return view('terms-policy');
})->name('terms-policy');

// Student specific auth routes
Route::get('/student/login', function () {
    return view('login');
})->name('student.login');

// Simple student login handler
Route::post('/student/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Add the role condition
    $user = User::where('email', $credentials['email'])
                ->where('role', 'student')
                ->first();

    if ($user && Auth::attempt($credentials)) {
        $request->session()->regenerate();
        return redirect()->route('dashboard');
    }

    return back()->withErrors([
        'email' => 'The provided credentials do not match our records or you are not registered as a student.',
    ])->onlyInput('email');
})->name('student.login.submit');

Route::get('/student/register', function () {
    return view('register');
})->name('student.register');

// Student registration handler
Route::post('/student/register', function (Request $request) {
    $request->validate([
        'username' => 'required|string|unique:users,username|max:50',
        'email' => 'required|email|unique:users,email|max:100',
        'password' => 'required|min:8|confirmed',
        'full_name' => 'required|string|max:100',
        'student_number' => 'required|string|unique:users,student_number|max:20',
        'contact_number' => 'nullable|string|max:20',
    ]);
    
    try {
        // Create the user
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'full_name' => $request->full_name,
            'role' => 'student',
            'student_number' => $request->student_number,
            'contact_number' => $request->contact_number
        ]);
        
        // Log the user in
        Auth::login($user);
        
        return redirect()->route('dashboard');
        
    } catch (\Exception $e) {
        return back()->withInput()->withErrors(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
})->name('student.register.submit');

// Seller specific auth routes
Route::get('/seller/login', function () {
    return view('seller.login');
})->name('seller.login');

// Simple seller login handler
Route::post('/seller/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Add the role condition
    $user = User::where('email', $credentials['email'])
                ->where('role', 'seller')
                ->first();

    if ($user && Auth::attempt($credentials)) {
        $request->session()->regenerate();
        return redirect()->route('seller.dashboard');
    }

    return back()->withErrors([
        'email' => 'The provided credentials do not match our records or you are not registered as a seller.',
    ])->onlyInput('email');
})->name('seller.login.submit');

Route::get('/seller/register', function () {
    return view('seller.register');
})->name('seller.register');

// Use the SellerController for registration
Route::post('/seller/register', [\App\Http\Controllers\SellerController::class, 'store'])
    ->name('seller.register.submit');

// Student dashboard (regular users)
Route::get('dashboard', function() {
    // Load all menu items from the database with eager loading of categories
    try {
        $menuItems = \App\Models\MenuItem::orderBy('created_at', 'desc')->get();
        
        // Convert any category objects to strings for consistency in the view
        foreach ($menuItems as $item) {
            if (is_object($item->category)) {
                // Category is an object with a name property
                $item->category_name = $item->category->name;
            } else {
                // Category is already a string or null
                $item->category_name = $item->category ?? 'Uncategorized';
            }
        }
        
        // Get all unique categories
        $categories = \App\Models\Category::all();
        
        // Get all active sellers with their associated user data
        $sellers = \App\Models\Seller::with('user')->get();
        
        return view('dashboard', compact('menuItems', 'categories', 'sellers'));
    } catch (\Exception $e) {
        // Log the error
        \Illuminate\Support\Facades\Log::error('Error loading dashboard: ' . $e->getMessage());
        
        // Return an error view or redirect
        return view('dashboard', [
            'menuItems' => collect(),
            'categories' => collect(),
            'sellers' => collect(),
            'error' => 'Error loading menu items: ' . $e->getMessage()
        ]);
    }
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Use the SellerController for the dashboard
Route::get('seller/dashboard', [SellerController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('seller.dashboard');

// User Settings Routes
Route::middleware(['auth'])->group(function () {
    // Update the settings route to use our controller
    Route::get('/settings', [\App\Http\Controllers\UserSettingsController::class, 'index'])->name('settings.profile');
    Route::put('/settings/update', [\App\Http\Controllers\UserSettingsController::class, 'update'])->name('user-settings.update');
    Route::put('/settings/password', [\App\Http\Controllers\UserSettingsController::class, 'updatePassword'])->name('user-settings.password');
});

// Seller Dashboard Routes
Route::middleware(['auth'])->prefix('seller')->name('seller.')->group(function () {
    // Seller Dashboard
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
    
    // Menu Items Routes - Using resourceful routing
    Route::resource('menu-items', MenuItemController::class)->parameter('menu-items', 'menuItem')->names('menuItems');
    
    // Explicit route for delete since the resource route might have issues
    Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy'])->name('menuItems.destroy');
    
    // EMERGENCY FIX: Direct SQL update route to prevent item deletion during edit
    Route::post('menu-items/{id}/direct-update', [MenuItemController::class, 'directUpdate'])->name('menuItems.directUpdate');
    
    // Products Routes
    Route::get('/products', [MenuItemController::class, 'indexAsProducts'])->name('products.index');
    Route::get('/products/{id}/edit', [MenuItemController::class, 'editAsProduct'])->name('products.edit');
    Route::put('/products/{id}', [MenuItemController::class, 'updateAsProduct'])->name('products.update');
    Route::delete('/products/{id}', [MenuItemController::class, 'destroyAsProduct'])->name('products.destroy');
    
    // Categories Routes
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/categories/{id}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Orders Routes
    Route::get('/orders', [OrderController::class, 'sellerIndex'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'sellerShow'])->name('orders.show');
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::put('/orders/{id}/ready', [OrderController::class, 'markAsReady'])->name('orders.ready');
    Route::put('/orders/{id}/complete', [OrderController::class, 'markAsCompleted'])->name('orders.complete');
    
    // Feedback Routes
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/feedback/stats', [FeedbackController::class, 'getStats'])->name('feedback.stats');
    Route::get('/feedback/{id}', [FeedbackController::class, 'show'])->name('feedback.show');
    Route::post('/feedback/mark-read', [FeedbackController::class, 'markAllAsRead'])->name('feedback.markRead');
    
    // Sales Report Route
    Route::get('/reports/sales', [ReportController::class, 'salesReport'])->name('reports.sales');
    
    // Profile Routes
    Route::get('/profile', [SellerController::class, 'profile'])->name('profile');
    Route::put('/profile/update', [SellerController::class, 'updateProfile'])->name('profile.update');
    
    // Earnings Route
    Route::get('/earnings', [EarningController::class, 'index'])->name('earnings.index');
    
    // Stall availability endpoints
    Route::post('/check-stall-availability', [SellerController::class, 'checkStallAvailability'])->name('check-stall-availability');
    Route::post('/get-available-stalls', [SellerController::class, 'getAvailableStalls'])->name('get-available-stalls');
});

// Image Upload Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/images/upload', [ImageController::class, 'upload'])->name('images.upload');
    Route::delete('/images/{id}', [ImageController::class, 'delete'])->name('images.delete');
});

// Cart Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/cart/add', [CartController::class, 'addToCart'])->name('cart.add');
    Route::post('/cart/remove', [CartController::class, 'removeFromCart'])->name('cart.remove');
    Route::post('/cart/update', [CartController::class, 'updateQuantity'])->name('cart.update');
    Route::get('/cart', [CartController::class, 'getCart'])->name('cart.get');
    Route::post('/cart/clear', [CartController::class, 'clearCart'])->name('cart.clear');
    
    // Student Order Routes
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/check-pending', [OrderController::class, 'checkPending'])->name('orders.check-pending');
    Route::get('/orders/check-ready-for-pickup', [OrderController::class, 'checkReadyForPickup'])->name('orders.check-ready-for-pickup');
    Route::post('/orders/auto-cancel', [OrderController::class, 'autoCancel'])->name('orders.auto-cancel');
    Route::post('/orders/check-remaining-time', [OrderController::class, 'checkRemainingTime'])->name('orders.check-remaining-time');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{id}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/get-details', [OrderController::class, 'getDetails'])->name('orders.get-details');
    Route::post('/orders/check-status', [OrderController::class, 'checkOrderStatus'])->name('orders.check-status');
    
    // Rating Routes
    Route::post('/ratings', [\App\Http\Controllers\RatingController::class, 'store'])->name('rating.store');
});

// Notification Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications/mark-as-read/{id}', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::get('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::get('/notifications/clear-test', [NotificationController::class, 'clearTestNotifications'])->name('notifications.clearTest');
});

// Add a route to view a specific seller's menu
Route::get('/seller/{seller_id}/menu', function($seller_id) {
    try {
        // Find the seller
        $seller = \App\Models\Seller::with('user')->findOrFail($seller_id);
        
        // Get the seller's menu items
        $menuItems = \App\Models\MenuItem::where('seller_id', $seller_id)
            ->where('is_available', true)
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Process category info for menu items
        foreach ($menuItems as $item) {
            if (is_object($item->category)) {
                $item->category_name = $item->category->name;
            } else {
                $item->category_name = $item->category ?? 'Uncategorized';
            }
        }
        
        // Get categories for this seller
        $categories = \App\Models\Category::where('seller_id', $seller_id)->get();
        
        return view('seller-menu', compact('seller', 'menuItems', 'categories'));
    } catch (\Exception $e) {
        return redirect()->route('dashboard')->with('error', 'Could not find seller or menu: ' . $e->getMessage());
    }
})->middleware(['auth', 'verified'])->name('seller.menu');

// Diagnostic Page (for development only)
Route::get('/diagnostic', function() {
    return view('diagnostic');
})->middleware(['auth'])->name('diagnostic');

// Public routes accessible without login
Route::middleware(['web'])->group(function () {
    // Stall search routes
    Route::get('/stalls/search', [\App\Http\Controllers\SellerController::class, 'searchStalls'])->name('stalls.search');
    Route::post('/stalls/search', [\App\Http\Controllers\SellerController::class, 'searchStallsResults'])->name('stalls.search.results');
    
    // Public menu page that doesn't require login
    Route::get('/menu', function() {
        try {
            $menuItems = \App\Models\MenuItem::orderBy('created_at', 'desc')->get();
            
            // Convert any category objects to strings for consistency in the view
            foreach ($menuItems as $item) {
                if (is_object($item->category)) {
                    // Category is an object with a name property
                    $item->category_name = $item->category->name;
                } else {
                    // Category is already a string or null
                    $item->category_name = $item->category ?? 'Uncategorized';
                }
            }
            
            // Get all unique categories
            $categories = \App\Models\Category::all();
            
            // Get all active sellers with their associated user data
            $sellers = \App\Models\Seller::with('user')->get();
            
            return view('public-menu', compact('menuItems', 'categories', 'sellers'));
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error loading public menu: ' . $e->getMessage());
            
            // Return an error view
            return view('public-menu', [
                'menuItems' => collect(),
                'categories' => collect(),
                'sellers' => collect(),
                'error' => 'Error loading menu items: ' . $e->getMessage()
            ]);
        }
    })->name('public.menu');
});

// Route for product ratings
Route::get('/get-product-ratings/{itemId}', [\App\Http\Controllers\RatingController::class, 'getProductRatings'])->name('get-product-ratings');

// Debug route for menu items (temporary)
Route::get('/debug/menu-items', [OrderController::class, 'debugMenuItems'])->name('debug.menuItems');

require __DIR__.'/auth.php';
