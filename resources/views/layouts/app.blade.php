<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BagsakanVeggies')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @yield('head')
    <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body>
    @yield('header')

    <div class="promo-banner">
        🚚 Orders placed before <strong>12:00 NN</strong> will be delivered <strong>same day</strong>!
    </div>

    @if (session('notice'))
        <div class="flash-notice">{{ session('notice') }}</div>
    @endif

    @yield('content')

    <button type="button" class="help-fab" title="Need help? Contact BagsakanVeggies support.">?</button>
</body>
</html>
