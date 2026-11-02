@if ($perPurchaser)
    <div class="a-person-export" x-data="purchaseExport(@js($exportGroups))">
        <button type="button" class="a-btn a-btn-primary" @click="$refs.dialog.showModal()" aria-haspopup="dialog" aria-controls="purchase-export-dialog"><x-admin.icon name="download" :size="18" /> Download Excel</button>
        <dialog id="purchase-export-dialog" class="a-export-dialog" x-ref="dialog" aria-labelledby="purchase-export-title" aria-describedby="purchase-export-description" @click="if ($event.target === $el) $el.close()">
            <div class="a-export-dialog-inner">
                <div class="a-export-dialog-head">
                    <h2 id="purchase-export-title" class="a-section-title">Choose purchases to export</h2>
                    <button type="button" class="a-icon-btn" @click="$refs.dialog.close()" aria-label="Close purchase selection"><x-admin.icon name="x" /></button>
                </div>
                <p id="purchase-export-description" class="a-muted">{{ $day?->format('F j, Y') ?? 'All purchase dates' }}. Select a purchaser or individual purchases for the picking and packing forms. Prices use the admin estimate.</p>
                <form method="GET" action="{{ route('admin.purchase-orders.export') }}" @submit="selected.length ? $refs.dialog.close() : $event.preventDefault()">
                    @if ($day)<input type="hidden" name="date" value="{{ $day->toDateString() }}">@else<input type="hidden" name="all_dates" value="1">@endif
                    <input type="hidden" name="mode" value="purchasers">
                    @if ($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
                    <input type="hidden" name="selection" value="1">
                    <label class="a-check a-export-select-all"><input type="checkbox" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" @change="toggleAll()" autofocus> Select all purchases</label>
                    <div class="a-export-people a-export-purchase-groups">
                        @foreach ($exportGroups as $group)
                            <fieldset class="a-export-purchase-group">
                                <legend class="sr-only">{{ $group['name'] }}</legend>
                                <label class="a-check a-export-person">
                                    <input type="checkbox" :checked="groupSelected({{ $loop->index }})" :indeterminate="groupPartial({{ $loop->index }})" @change="toggleGroup({{ $loop->index }})">
                                    <span class="a-export-person-name">{{ $group['name'] }}</span>
                                </label>
                                <ul class="a-export-purchases">
                                    @foreach ($group['purchases'] as $purchase)
                                        <li><label class="a-check a-export-person"><input type="checkbox" name="purchases[]" value="{{ $purchase['id'] }}" x-model="selected" checked><span>{{ $purchase['label'] }}</span></label></li>
                                    @endforeach
                                </ul>
                            </fieldset>
                        @endforeach
                    </div>
                    <p class="a-hint a-export-selection-count" role="status" x-text="selected.length ? selected.length + ' purchase(s) selected' : 'Select at least one purchase to download.'"></p>
                    <div class="a-export-dialog-actions">
                        <button type="button" class="a-btn a-btn-secondary" @click="$refs.dialog.close()">Cancel</button>
                        <button type="submit" class="a-btn a-btn-primary" :disabled="selected.length === 0"><x-admin.icon name="download" :size="18" /> Download selected</button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>
@else
    <a href="{{ route('admin.purchase-orders.export', [...$filter, 'mode' => 'all']) }}" class="a-btn a-btn-primary"><x-admin.icon name="download" :size="18" /> Download Excel</a>
@endif
