<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · Bagsakan Veggies Phils</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@500;600&family=Fira+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <script defer src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
{{-- On phones and tablets the side menu slides in from the ☰ button; on wider screens it stays open. --}}
<body class="@auth has-sidebar @endauth @yield('body-class')"
      x-data="{ menuOpen: false }"
      x-init="$watch('menuOpen', open => $nextTick(() => (open ? $refs.menuClose : $refs.menuButton)?.focus()))"
      :class="{ 'is-menu-open': menuOpen }"
      @keydown.escape.window="menuOpen = false">
    <a href="#main" class="skip-link">Skip to content</a>

    @auth
        @php
            // Each page names its menu item with @section('nav', ...): dashboard, vegetable or fruit.
            $activeNav = trim(\Illuminate\Support\Facades\View::yieldContent('nav'));
        @endphp

        <header class="a-mobilebar">
            <button type="button" class="a-icon-btn" x-ref="menuButton" @click="menuOpen = true"
                    aria-controls="admin-menu" aria-expanded="false" :aria-expanded="menuOpen" aria-label="Open menu">
                <x-admin.icon name="menu" />
            </button>
            <x-admin.brand />
        </header>

        <div class="a-sidebar-backdrop" x-show="menuOpen" x-cloak x-transition.opacity @click="menuOpen = false"></div>

        <aside id="admin-menu" class="a-sidebar" :class="{ 'is-open': menuOpen }" aria-label="Admin menu">
            <div class="a-sidebar-head">
                <x-admin.brand />
                <button type="button" class="a-icon-btn a-sidebar-close" x-ref="menuClose" @click="menuOpen = false" aria-label="Close menu">
                    <x-admin.icon name="x" />
                </button>
            </div>

            <nav class="a-sidenav" aria-label="Admin">
                <a href="{{ route('admin.home') }}" class="a-sidenav-link" @if ($activeNav === 'dashboard') aria-current="page" @endif>
                    <x-admin.icon name="layout-dashboard" />
                    <span>Dashboard</span>
                </a>
                <a href="{{ \App\Models\Product::adminListUrl(\App\Models\Product::CATEGORY_VEGETABLE) }}" class="a-sidenav-link" @if ($activeNav === \App\Models\Product::CATEGORY_VEGETABLE) aria-current="page" @endif>
                    <x-admin.icon name="carrot" />
                    <span>Veggies</span>
                </a>
                <a href="{{ \App\Models\Product::adminListUrl(\App\Models\Product::CATEGORY_FRUIT) }}" class="a-sidenav-link" @if ($activeNav === \App\Models\Product::CATEGORY_FRUIT) aria-current="page" @endif>
                    <x-admin.icon name="apple" />
                    <span>Fruits</span>
                </a>
            </nav>

            <div class="a-sidebar-foot">
                <a href="{{ route('products.index') }}" class="a-sidenav-link" target="_blank" rel="noopener">
                    <x-admin.icon name="store" />
                    <span>View store</span>
                    <span class="sr-only">(opens in a new tab)</span>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="a-sidenav-link" title="Sign out {{ auth()->user()->name }}">
                        <x-admin.icon name="log-out" />
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </aside>
    @endauth

    <main id="main" class="a-main" tabindex="-1">
        @if (session('status'))
            <div class="a-flash" role="status">
                <x-admin.icon name="check-circle" :size="20" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
