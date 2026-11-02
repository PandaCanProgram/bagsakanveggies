<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\PurchaseOrderIndexRequest;
use App\Models\PurchaseOrder;
use App\Services\DailyOrderSummary;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ExportPurchaseOrdersRequest extends PurchaseOrderIndexRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $base = parent::rules();
        $this->validateDateBeforeSelection();
        $day = $this->boolean('all_dates') ? null : ($this->input('date') ?: DailyOrderSummary::today()->toDateString());
        $ids = PurchaseOrder::query()->when($day, fn (Builder $query): Builder => $query->whereDate('purchase_date', $day))->pluck('id')->all();

        return [
            ...$base,
            'mode' => ['required', Rule::in(['all', 'purchasers'])],
            'selection' => ['required_if:mode,purchasers', Rule::in(['1'])],
            'purchases' => ['required_if:selection,1', 'array', 'min:1'],
            'purchases.*' => ['required', 'integer', 'distinct', Rule::in($ids)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(),
            'purchases.required_if' => 'Select at least one purchase to download.',
            'purchases.min' => 'Select at least one purchase to download.',
            'purchases.*.in' => 'A selected purchase is not in this date filter. Refresh the page and try again.',
        ];
    }

    private function validateDateBeforeSelection(): void
    {
        validator($this->only(['date', 'all_dates']), parent::rules(), parent::messages())->validate();
    }
}
