@extends('layouts.admin')

@section('title', $purchaseOrder->number())
@section('nav', 'purchase-orders')

@section('content')
    @php
        $money = \App\Support\PurchaseMoney::class;
        $completed = $purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_COMPLETED;
        $editable = $isPurchaser && ! $completed;
        $pending = $purchaseOrder->unpricedItemsCount();
        $estimated = $purchaseOrder->estimatedTotalCents();
        $actual = $purchaseOrder->actualTotalCents();
        $entries = $purchaseOrder->items->mapWithKeys(fn ($item) => [(string) $item->id => [
            'quantity' => $item->quantity,
            'actual_unit_price' => $editable ? old('items.'.$item->id.'.actual_unit_price', $item->actual_unit_price ?? '') : ($item->actual_unit_price ?? ''),
        ]])->all();
    @endphp

    <a href="{{ route($isPurchaser ? 'purchaser.home' : 'admin.purchase-orders.index') }}" class="a-link-muted a-back po-no-print"><x-admin.icon name="arrow-left" :size="16" /> All purchase orders</a>
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">{{ $purchaseOrder->number() }}</h1>
            <p class="a-muted">{{ $isPurchaser ? 'Record what each item actually cost per unit.' : 'Estimated and actual costs, ready for your liquidation summary.' }}</p>
        </div>
        <button type="button" class="a-btn a-btn-secondary" onclick="window.print()"><x-admin.icon name="printer" /> Print {{ $completed ? 'liquidation' : 'purchase order' }}</button>
    </div>

    <div class="a-stack" x-data="purchaseActuals(@js($entries), {{ $estimated }})">
        @if ($errors->any())
            <div class="a-error-summary" role="alert" tabindex="-1" x-init="$el.focus()">
                <h2 class="a-error-summary-title">Please check the actual prices</h2>
                <ul>
                    @foreach ($errors->getMessages() as $field => $messages)
                        <li><a href="#{{ str_starts_with($field, 'items.') ? str_replace('.', '-', $field) : 'po-items' }}">{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="a-card a-card-pad a-stack po-details" aria-label="Purchase order details">
            <div class="po-section-head">
                <h2 class="a-section-title">{{ $purchaseOrder->number() }} &middot; {{ $completed ? 'Liquidation summary' : 'Purchase order' }}</h2>
                <span class="po-status">{{ $completed ? 'Completed' : 'Assigned' }}</span>
            </div>
            <dl class="po-metadata">
                <div><dt>Purchase date</dt><dd>{{ $purchaseOrder->purchase_date->format('F j, Y') }}</dd></div>
                <div><dt>Purchaser</dt><dd>{{ $purchaseOrder->purchaser->name }}</dd></div>
                <div><dt>Prepared by</dt><dd>{{ $purchaseOrder->creator->name }}</dd></div>
                @if ($completed)<div><dt>Completed</dt><dd>{{ $purchaseOrder->completed_at->format('M j, Y g:i A') }}</dd></div>@endif
            </dl>
            @if ($purchaseOrder->remarks)<p class="po-remarks"><strong>Admin remarks:</strong> {{ $purchaseOrder->remarks }}</p>@endif
        </section>

        <div class="po-summary" aria-label="Cost summary">
            <div class="a-card a-card-pad"><h2 class="a-label">Estimated total</h2><p class="po-summary-value po-money">{{ $money::format($estimated) }}</p><p class="a-hint">Original admin estimate</p></div>
            <div class="a-card a-card-pad"><h2 class="a-label">{{ $editable ? 'Actual total' : 'Recorded actual total' }}</h2><p class="po-summary-value po-money" @if ($editable) x-text="money(totalCents())" @endif>{{ $money::format($actual) }}</p><p class="a-hint" @if ($editable) x-text="pendingCount() ? pendingCount() + ' item(s) still need an actual price' : 'All items have actual prices'" @endif>{{ $pending ? $pending.' item(s) still need an actual price' : 'All items have actual prices' }}</p></div>
            <div class="a-card a-card-pad"><h2 class="a-label">Actual minus estimate</h2><p class="po-summary-value po-money" @if ($editable) x-text="pendingCount() ? 'Pending' : money(totalCents() - estimated)" @endif>{{ $pending ? 'Pending' : $money::format($actual - $estimated) }}</p><p class="a-hint">Positive = over estimate; negative = saved</p></div>
        </div>

        @if ($editable)
            <form id="po-actual-form" method="POST" action="{{ route('purchaser.purchase-orders.update', $purchaseOrder) }}" @submit="startSubmit($event)">
                @csrf
                @method('PUT')
                <input type="hidden" name="action" value="save">
            </form>
            <p class="a-muted po-no-print">Save your progress at any time. Complete the PO after entering every actual price; completed orders are locked.</p>
        @elseif ($completed)
            <p class="a-muted">Final actual costs submitted by the purchaser. This purchase order is locked.</p>
        @else
            <p class="a-muted po-no-print">Purchaser updates appear here after saving. Refresh this page to see the latest figures.</p>
        @endif

        <section id="po-items" class="a-card po-table-wrap" aria-label="Purchase order items">
            <table class="po-table">
                <caption class="sr-only">Purchase order particulars, quantities, estimated prices, actual prices and remarks</caption>
                <thead><tr><th scope="col">Particulars</th><th scope="col">Qty / unit</th><th scope="col">Est. unit price</th><th scope="col">Est. total unit price</th><th scope="col">Actual unit price</th><th scope="col">Actual total unit price</th><th scope="col">Remarks</th></tr></thead>
                <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        <tr>
                            <th scope="row" data-label="Particulars">{{ $item->particulars }}</th>
                            <td data-label="Qty / unit">{{ \App\Support\Format::qty($item->quantity, 3) }} {{ $item->unit }}</td>
                            <td data-label="Est. unit price" class="po-money">{{ $money::format($money::cents($item->estimated_unit_price)) }}</td>
                            <td data-label="Est. total" class="po-money">{{ $money::format($item->estimatedTotalCents()) }}</td>
                            <td data-label="Actual unit price">
                                @if ($editable)
                                    <div class="a-field">
                                        <label for="items-{{ $item->id }}-actual_unit_price" class="sr-only">Actual unit price for {{ $item->particulars }}</label>
                                        <input form="po-actual-form" type="number" id="items-{{ $item->id }}-actual_unit_price" name="items[{{ $item->id }}][actual_unit_price]" class="a-input po-price-input" min="0" max="999999.99" step="0.01" inputmode="decimal"
                                               value="{{ old('items.'.$item->id.'.actual_unit_price', $item->actual_unit_price) }}" x-model="entries['{{ $item->id }}'].actual_unit_price"
                                               @error('items.'.$item->id.'.actual_unit_price') aria-invalid="true" aria-describedby="price-error-{{ $item->id }}" @enderror>
                                        @error('items.'.$item->id.'.actual_unit_price')<p id="price-error-{{ $item->id }}" class="a-field-error">{{ $message }}</p>@enderror
                                    </div>
                                @else
                                    <span class="po-money">{{ $item->actual_unit_price === null ? 'Pending' : $money::format($money::cents($item->actual_unit_price)) }}</span>
                                @endif
                            </td>
                            <td data-label="Actual total" class="po-money" @if ($editable) x-text="lineTotal('{{ $item->id }}')" @endif>{{ $item->actualTotalCents() === null ? 'Pending' : $money::format($item->actualTotalCents()) }}</td>
                            <td data-label="Remarks" class="po-item-remarks">
                                @if ($item->remarks)<p><strong>Admin:</strong> {{ $item->remarks }}</p>@endif
                                @if ($editable)
                                    <label for="items-{{ $item->id }}-purchaser_remarks" class="a-label">Your remarks (optional)</label>
                                    <textarea form="po-actual-form" id="items-{{ $item->id }}-purchaser_remarks" name="items[{{ $item->id }}][purchaser_remarks]" class="a-input" rows="2" maxlength="500">{{ old('items.'.$item->id.'.purchaser_remarks', $item->purchaser_remarks) }}</textarea>
                                    @error('items.'.$item->id.'.purchaser_remarks')<p class="a-field-error">{{ $message }}</p>@enderror
                                @elseif ($item->purchaser_remarks)
                                    <p><strong>Purchaser:</strong> {{ $item->purchaser_remarks }}</p>
                                @elseif (! $item->remarks)
                                    <span class="a-muted">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        @if ($editable)
            <div class="po-form-actions po-no-print">
                <button form="po-actual-form" type="submit" value="save" class="a-btn a-btn-secondary" :disabled="submitting">Save progress</button>
                <button form="po-actual-form" type="submit" value="complete" class="a-btn a-btn-primary" :disabled="submitting || pendingCount() > 0">Complete purchase order</button>
            </div>
        @endif
    </div>
@endsection
