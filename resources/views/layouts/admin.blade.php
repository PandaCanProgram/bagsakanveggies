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
<body class="@yield('body-class')">
    <a href="#main" class="skip-link">Skip to content</a>

    @auth
        <header class="a-topbar">
            <div class="a-topbar-inner">
                <a href="{{ route('admin.products.index') }}" class="a-brand">
                    <span class="a-brand-mark"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="256" height="256"></span>
                    <span class="a-brand-text">
                        <span class="a-brand-name">Bagsakan Veggies Phils</span>
                        <span class="a-brand-chip">Admin</span>
                    </span>
                </a>

                <nav class="a-nav" aria-label="Admin">
                    <a href="{{ route('admin.products.index') }}" class="a-nav-link" @if (request()->routeIs('admin.products.*')) aria-current="page" @endif>Veggies &amp; prices</a>
                </nav>

                <div class="a-topbar-actions">
                    <a href="{{ route('products.index') }}" class="a-btn a-btn-ghost" target="_blank" rel="noopener">
                        <x-admin.icon name="store" :size="18" />
                        <span class="a-hide-sm">View store</span>
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="a-btn a-btn-ghost" title="Sign out {{ auth()->user()->name }}">
                            <x-admin.icon name="log-out" :size="18" />
                            <span class="a-hide-sm">Sign out</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>
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
