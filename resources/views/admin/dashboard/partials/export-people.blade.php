@php
    $personIds = array_map(fn (array $person): string => (string) $person['orders'][0]['id'], $summary->people);
@endphp

<div class="a-person-export" x-data="personExport(@js($personIds))">
    <button type="button" class="a-btn a-btn-primary" @click="$refs.dialog.showModal()"
            aria-haspopup="dialog" aria-controls="person-export-dialog">
        <x-admin.icon name="download" :size="18" />
        Download Excel
    </button>

    <dialog id="person-export-dialog" class="a-export-dialog" x-ref="dialog"
            aria-labelledby="person-export-title" aria-describedby="person-export-description"
            @click="if ($event.target === $el) $el.close()">
        <div class="a-export-dialog-inner">
            <div class="a-export-dialog-head">
                <h2 id="person-export-title" class="a-section-title">Choose people to export</h2>
                <button type="button" class="a-icon-btn" @click="$refs.dialog.close()" aria-label="Close customer selection">
                    <x-admin.icon name="x" />
                </button>
            </div>
            <p id="person-export-description" class="a-muted">
                {{ $summary->day->format('F j, Y') }}. Select whose orders to include in the Excel file.
            </p>

            <form method="GET" action="{{ route('admin.orders-per-person.export') }}"
                  @submit="selected.length ? $refs.dialog.close() : $event.preventDefault()">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="selection" value="1">

                <label class="a-check a-export-select-all">
                    <input type="checkbox" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected"
                           @change="toggleAll()" autofocus>
                    Select all
                </label>

                <fieldset class="a-export-options">
                    <legend class="sr-only">People who ordered on {{ $summary->day->format('F j, Y') }}</legend>
                    <ul class="a-export-people">
                        @foreach ($summary->people as $person)
                            <li>
                                <label class="a-check a-export-person">
                                    <input type="checkbox" name="people[]" value="{{ $person['orders'][0]['id'] }}" x-model="selected" checked>
                                    <span>
                                        <span class="a-export-person-name">{{ $person['name'] }}</span>
                                        <span class="a-export-person-details">
                                            {{ $person['contact_number'] }} &middot;
                                            {{ count($person['orders']) }} {{ \Illuminate\Support\Str::plural('order', count($person['orders'])) }}
                                        </span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>

                <p class="a-hint a-export-selection-count" role="status"
                   x-text="selected.length ? selected.length + ' selected' : 'Select at least one person to download.'"></p>
                <div class="a-export-dialog-actions">
                    <button type="button" class="a-btn a-btn-secondary" @click="$refs.dialog.close()">Cancel</button>
                    <button type="submit" class="a-btn a-btn-primary" :disabled="selected.length === 0">
                        <x-admin.icon name="download" :size="18" />
                        Download selected
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</div>
