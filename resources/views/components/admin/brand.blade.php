<a href="{{ route(auth()->user()?->is_admin ? 'admin.home' : 'purchaser.home') }}" {{ $attributes->merge(['class' => 'a-brand']) }}>
    <span class="a-brand-mark"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="256" height="256"></span>
    <span class="a-brand-text {{ auth()->user()?->is_admin ? '' : 'a-brand-purchaser' }}">
        <span class="a-brand-name">Bagsakan Veggies Phils</span>
        @if (auth()->user()?->is_admin)
            <span class="a-brand-chip">Admin</span>
        @else
            <span class="a-brand-chip a-brand-system">Purchase order system</span>
            <span class="a-brand-user"><span class="sr-only">Signed in as </span>{{ \Illuminate\Support\Str::before(auth()->user()?->email ?? '', '@') }}</span>
        @endif
    </span>
</a>
