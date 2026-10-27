@extends('layouts.admin')

@section('title', 'Create purchase order')
@section('nav', 'purchase-orders')

@section('content')
    <a href="{{ route('admin.purchase-orders.index') }}" class="a-link-muted a-back"><x-admin.icon name="arrow-left" :size="16" /> All purchase orders</a>
    <div class="a-page-head">
        <div>
            <h1 class="a-page-title">Create purchase order</h1>
            <p class="a-muted">Enter the items to buy and your estimated price per unit.</p>
        </div>
    </div>

    @if ($purchasers->isEmpty())
        <div class="a-alert a-alert-error" role="alert">No purchaser account is available yet. Create one with the purchaser:create Artisan command before assigning an order.</div>
    @endif

    @php
        $state = [
            'items' => array_values(old('items', [['particulars' => '', 'quantity' => '1', 'unit' => 'kg', 'estimated_unit_price' => '', 'remarks' => '']])),
            'errors' => $errors->getMessages(),
        ];
    @endphp
    <form method="POST" action="{{ route('admin.purchase-orders.store') }}" class="a-stack" x-data="purchaseOrderForm(@js($state))" @submit="submitting = true" novalidate>
        @csrf
        @if ($errors->any())
            <div class="a-error-summary" role="alert" tabindex="-1" x-init="$el.focus()">
                <h2 class="a-error-summary-title">Please check the purchase order</h2>
                <ul>
                    @foreach ($errors->getMessages() as $field => $messages)
                        <li><a href="#{{ str_starts_with($field, 'items.') ? str_replace('.', '-', $field) : $field }}">{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="a-card a-card-pad a-stack" aria-labelledby="po-details-title">
            <h2 id="po-details-title" class="a-section-title">Order details</h2>
            <div class="po-fields">
                <div class="a-field">
                    <label for="purchaser_id" class="a-label">Assign to purchaser</label>
                    <select name="purchaser_id" id="purchaser_id" class="a-input" required @error('purchaser_id') aria-invalid="true" aria-describedby="purchaser_id-error" @enderror>
                        <option value="">Choose a purchaser</option>
                        @foreach ($purchasers as $purchaser)
                            <option value="{{ $purchaser->id }}" @selected((string) old('purchaser_id') === (string) $purchaser->id)>{{ $purchaser->name }} ({{ $purchaser->email }})</option>
                        @endforeach
                    </select>
                    @error('purchaser_id')<p class="a-field-error" id="purchaser_id-error">{{ $message }}</p>@enderror
                </div>
                <div class="a-field">
                    <label for="purchase_date" class="a-label">Purchase date</label>
                    <input type="date" name="purchase_date" id="purchase_date" class="a-input" value="{{ old('purchase_date', today()->format('Y-m-d')) }}" required @error('purchase_date') aria-invalid="true" aria-describedby="purchase_date-error" @enderror>
                    @error('purchase_date')<p class="a-field-error" id="purchase_date-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="a-field">
                <label for="remarks" class="a-label">Order remarks (optional)</label>
                <textarea name="remarks" id="remarks" class="a-input" rows="2" maxlength="2000">{{ old('remarks') }}</textarea>
                @error('remarks')<p class="a-field-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section id="items" class="a-stack" aria-labelledby="po-items-title">
            <div class="po-section-head">
                <div><h2 id="po-items-title" class="a-section-title">Items to buy</h2><p class="a-hint">Quantity &times; unit price = total unit price. Up to 100 items.</p></div>
            </div>
            <template x-for="(item, index) in items" :key="item.key">
                <fieldset class="a-card a-card-pad po-item-editor">
                    <legend class="sr-only" x-text="'Item ' + (index + 1)"></legend>
                    <div class="po-section-head">
                        <h3 x-text="'Item ' + (index + 1)"></h3>
                        <button type="button" class="a-btn a-btn-ghost" @click="remove(index)" :disabled="items.length === 1" :aria-label="'Remove item ' + (index + 1)"><x-admin.icon name="trash" :size="16" /> Remove</button>
                    </div>
                    <div class="po-item-fields">
                        @foreach (['particulars' => 'Particulars', 'quantity' => 'Qty', 'unit' => 'Unit', 'estimated_unit_price' => 'Estimated unit price', 'remarks' => 'Remarks (optional)'] as $field => $label)
                            <div class="a-field po-field-{{ $field }}">
                                <label class="a-label" :for="'items-' + index + '-{{ $field }}'">{{ $label }}</label>
                                <input :id="'items-' + index + '-{{ $field }}'" :name="'items[' + index + '][{{ $field }}]'" x-model="item.{{ $field }}" class="a-input"
                                       type="{{ in_array($field, ['quantity', 'estimated_unit_price']) ? 'number' : 'text' }}"
                                       @if ($field === 'quantity') min="0.001" max="99999.999" step="0.001" inputmode="decimal"
                                       @elseif ($field === 'estimated_unit_price') min="0" max="999999.99" step="0.01" inputmode="decimal"
                                       @else maxlength="{{ $field === 'remarks' ? 500 : ($field === 'particulars' ? 200 : 40) }}" @endif
                                       @if ($field !== 'remarks') required @endif
                                       :aria-invalid="!!error(index, '{{ $field }}')" :aria-describedby="error(index, '{{ $field }}') ? 'items-' + index + '-{{ $field }}-error' : null">
                                <p class="a-field-error" :id="'items-' + index + '-{{ $field }}-error'" x-show="error(index, '{{ $field }}')" x-text="error(index, '{{ $field }}')" x-cloak></p>
                            </div>
                        @endforeach
                        <div class="a-field"><span class="a-label">Total unit price</span><output class="po-line-preview po-money" x-text="money(lineCents(item))"></output></div>
                    </div>
                </fieldset>
            </template>
            <div><button type="button" class="a-btn a-btn-secondary" @click="add()" :disabled="items.length >= 100"><x-admin.icon name="plus" :size="16" /> Add item</button></div>
        </section>

        <section class="a-card a-card-pad po-order-total" aria-labelledby="po-total-label">
            <div>
                <h2 id="po-total-label" class="a-section-title">Estimated total</h2>
                <p class="a-hint">Total cost of all items in this purchase order.</p>
            </div>
            <p class="po-total-value po-money" role="status" aria-atomic="true">
                <span class="sr-only">Estimated total: </span>
                <span x-text="money(totalCents())">&#8369;0.00</span>
            </p>
        </section>

        <div class="po-form-actions">
            <a href="{{ route('admin.purchase-orders.index') }}" class="a-btn a-btn-ghost">Cancel</a>
            <button type="submit" class="a-btn a-btn-primary" :disabled="submitting || {{ $purchasers->isEmpty() ? 'true' : 'false' }}"><span x-text="submitting ? 'Sending…' : 'Send to purchaser'">Send to purchaser</span></button>
        </div>
    </form>
@endsection
