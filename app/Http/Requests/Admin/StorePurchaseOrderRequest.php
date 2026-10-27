<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
        return [
            'purchaser_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn (Builder $query): Builder => $query->where('is_purchaser', true)->where('is_admin', false))],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:particulars,quantity,unit,estimated_unit_price,remarks'],
            'items.*.particulars' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99999.999', 'regex:/^\d+(\.\d{1,3})?$/'],
            'items.*.unit' => ['required', 'string', 'max:40'],
            'items.*.estimated_unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'items.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'purchaser_id' => 'purchaser',
            'items.*.particulars' => 'particulars',
            'items.*.quantity' => 'quantity',
            'items.*.unit' => 'unit',
            'items.*.estimated_unit_price' => 'estimated unit price',
            'items.*.remarks' => 'item remarks',
        ];
    }
}
