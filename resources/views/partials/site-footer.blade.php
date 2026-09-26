<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <a href="{{ route('products.index') }}" class="brand brand-inverse">
                <span class="brand-mark"><img src="{{ asset('images/logo-mark.png') }}" alt="" width="256" height="256"></span>
                <span class="brand-word">Bagsakan <span>Veggies</span> Phils</span>
            </a>
            <p>Fresh vegetables by the bag or by the kilo, delivered around Quezon City.</p>
        </div>

        <ul class="footer-facts">
            <li><x-icon name="clock" size="18" /> Orders before 10&nbsp;AM dispatched by 12&nbsp;NN; after 10&nbsp;AM, by 3&nbsp;PM</li>
            <li><x-icon name="banknote" size="18" /> Cash on delivery</li>
            @if ($messengerPageUrl)
                <li>
                    <x-icon name="message" size="18" />
                    <a href="{{ $messengerPageUrl }}" target="_blank" rel="noopener">Message us on Messenger</a>
                </li>
            @endif
        </ul>
    </div>

    <div class="container footer-base">
        <p>&copy; {{ date('Y') }} Bagsakan Veggies Phils</p>
        <p>Powered by AVX</p>
    </div>
</footer>
