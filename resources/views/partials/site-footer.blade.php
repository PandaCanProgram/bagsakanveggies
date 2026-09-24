<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <a href="{{ route('products.index') }}" class="brand brand-inverse">
                <span class="brand-mark"><x-icon name="sprout" size="20" /></span>
                <span class="brand-word">Bagsakan<span>Veggies</span></span>
            </a>
            <p>Fresh vegetables by the bag or by the kilo, delivered around Metro Manila.</p>
        </div>

        <ul class="footer-facts">
            <li><x-icon name="clock" size="18" /> Order before 12:00 NN for same-day delivery</li>
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
        <p>&copy; {{ date('Y') }} BagsakanVeggies</p>
    </div>
</footer>
