<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#163a24">
    <title>@yield('title', 'BagsakanVeggies')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Instrument+Sans:wght@400..700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @yield('head')
    <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.14.1/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="@yield('body-class')">
    <a href="#main" class="skip-link">Skip to content</a>

    <div class="announcement">
        <div class="container announcement-inner">
            <x-icon name="truck" size="18" />
            <p>Order before <strong>12:00 NN</strong> for <strong>same-day delivery</strong> in Quezon City.</p>
        </div>
    </div>

    @yield('header')

    <main id="main">
        @if (session('notice'))
            <div class="container">
                <div class="notice" role="status">
                    <x-icon name="alert" size="18" />
                    <p>{{ session('notice') }}</p>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    @include('partials.site-footer')

    @yield('overlays')
    @include('partials.toast')
</body>
</html>
