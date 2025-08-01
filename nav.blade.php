<nav class="bg-white shadow-sm fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <a href="{{ route('home') }}" class="flex-shrink-0 flex items-center">
                    <span class="font-bold text-primary text-xl">Canteen</span><span class="font-light text-xl">Hub</span>
                </a>
                
                <!-- Navigation Links - Desktop -->
                <div class="hidden md:ml-10 md:flex md:space-x-8">
                    <a href="{{ route('home') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('home') ? 'border-primary text-gray-900 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Home
                    </a>
                    <a href="{{ route('public.menu') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('public.menu') ? 'border-primary text-gray-900 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Menu
                    </a>
                </div>
            </div>
            
            <!-- Right side buttons/user menu -->
            <div class="flex items-center">
                @auth
                    <div class="relative ml-3">
                        <div>
                            <button type="button" id="userDropdownBtn" class="flex items-center px-3 py-2 border border-primary text-primary rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors" aria-expanded="false" aria-haspopup="true">
                                <span>{{ Auth::user()->username }}</span>
                                <svg class="ml-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Dropdown menu -->
                        <div id="userDropdown" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="userDropdownBtn">
                            <a href="#profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">My Profile</a>
                            <a href="#orders" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">My Orders</a>
                            <div class="border-t border-gray-100"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Sign Out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('student.login') }}" class="px-4 py-2 border border-primary text-primary rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors {{ request()->routeIs('student.login') ? 'bg-primary text-white' : '' }}">
                            User Login
                        </a>
                     
                        <a href="{{ route('seller.login') }}" class="px-4 py-2 border border-red-600 text-red-600 rounded-md text-sm font-medium hover:bg-red-600 hover:text-white transition-colors {{ request()->routeIs('seller.login') ? 'bg-red-600 text-white' : '' }}">
                            Seller Login
                        </a>
                    
                    </div>
                @endauth
                
                <!-- Mobile menu button -->
                <div class="flex items-center md:hidden ml-2">
                    <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary" aria-expanded="false">
                        <span class="sr-only">Open main menu</span>
                        <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu, show/hide based on menu state -->
    <div id="mobile-menu" class="hidden md:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'bg-primary-50 border-primary text-primary' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800' }} block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Home
            </a>
            <a href="{{ route('public.menu') }}" class="{{ request()->routeIs('public.menu') ? 'bg-primary-50 border-primary text-primary' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800' }} block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                Menu
            </a>
        </div>
        
        @guest
            <div class="pt-4 pb-3 border-t border-gray-200">
                <div class="space-y-2 px-4">
                    <a href="{{ route('student.login') }}" class="block text-center py-2 rounded-md text-base font-medium {{ request()->routeIs('student.login') ? 'bg-primary text-white' : 'text-primary border border-primary' }}">
                        User Login
                    </a>
                    <a href="{{ route('student.register') }}" class="block text-center py-2 rounded-md text-base font-medium bg-primary text-white">
                        Register
                    </a>
                    <a href="{{ route('seller.login') }}" class="block text-center py-2 rounded-md text-base font-medium {{ request()->routeIs('seller.login') ? 'bg-red-600 text-white' : 'text-red-600 border border-red-600' }}">
                        Seller Login
                    </a>
                    <a href="{{ route('seller.register') }}" class="block text-center py-2 rounded-md text-base font-medium bg-red-600 text-white">
                        Register Stall
                    </a>
                </div>
            </div>
        @endguest
    </div>
</nav>

<!-- Add spacer to prevent content from being hidden behind fixed navbar -->
<div class="h-16"></div>

<script>
    // Mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }
        
        // User dropdown toggle
        const userDropdownBtn = document.getElementById('userDropdownBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userDropdownBtn && userDropdown) {
            userDropdownBtn.addEventListener('click', function() {
                userDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!userDropdownBtn.contains(event.target) && !userDropdown.contains(event.target)) {
                    userDropdown.classList.add('hidden');
                }
            });
        }
    });
</script>
