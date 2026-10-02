<table class="a-summary-table">
    <thead>
        <tr>
            <th scope="col">Product</th>
            <th scope="col" class="a-num">Total Qty Ordered</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($summary->lines as $line)
            <tr>
                <th scope="row"><span class="a-emoji" aria-hidden="true">{{ $line['emoji'] }}</span> {{ $line['product'] }}</th>
                <td class="a-num">{{ $line['quantity'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th scope="row">TOTAL</th>
            <td class="a-num">{{ $summary->total }}</td>
        </tr>
    </tfoot>
</table>
