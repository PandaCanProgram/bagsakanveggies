{{-- One block per person, in the order they first ordered: what to pack, where it goes and how much to collect. --}}
<div class="a-people">
    @foreach ($summary->people as $person)
        <article class="a-person" aria-labelledby="person-{{ $loop->iteration }}">
            <header class="a-person-head">
                <div>
                    <h3 id="person-{{ $loop->iteration }}" class="a-person-name">
                        <span class="a-person-number">{{ $loop->iteration }}.</span>
                        {{ $person['name'] }}
                    </h3>
                    <ul class="a-person-details">
                        <li>
                            <x-admin.icon name="phone" :size="16" />
                            <span class="sr-only">CP or Viber:</span>
                            <a href="tel:{{ $person['contact_number'] }}">{{ $person['contact_number'] }}</a>
                        </li>
                        @foreach ($person['addresses'] as $address)
                            <li>
                                <x-admin.icon name="map-pin" :size="16" />
                                <span class="sr-only">Address:</span>
                                <span>{{ $address }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <ul class="a-person-orders">
                    @foreach ($person['orders'] as $order)
                        <li>Order #{{ $order['id'] }} · {{ $order['time'] }}</li>
                    @endforeach
                </ul>
            </header>

            <table class="a-summary-table a-person-table">
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col" class="a-num">Qty</th>
                        <th scope="col" class="a-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($person['lines'] as $line)
                        <tr>
                            <th scope="row">
                                <span class="a-emoji" aria-hidden="true">{{ $line['emoji'] }}</span> {{ $line['product'] }}
                                <span class="a-item-size">{{ $line['size'] }}</span>
                            </th>
                            <td class="a-num">{{ \App\Support\Format::qty($line['qty']) }}</td>
                            <td class="a-num">{{ \App\Support\Format::peso($line['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @if ($person['delivery_fee'] > 0)
                        <tr>
                            <th scope="row" colspan="2">Delivery fee</th>
                            <td class="a-num">{{ \App\Support\Format::peso($person['delivery_fee']) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th scope="row" colspan="2">TOTAL</th>
                        <td class="a-num">{{ \App\Support\Format::peso($person['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </article>
    @endforeach
</div>

<p class="a-day-total">
    <span>TOTAL FOR THE DAY</span>
    <strong>{{ \App\Support\Format::peso($summary->total) }}</strong>
</p>
