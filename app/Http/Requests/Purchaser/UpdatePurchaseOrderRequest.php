<?php

namespace App\Http\Requests\Purchaser;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('purchaseOrder');

        return $this->user()?->is_purchaser
            && ! $this->user()->is_admin
            && $order instanceof PurchaseOrder
            && $order->purchaser_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['save', 'complete'])],
            'items' => ['required', 'array', 'size:'.$this->route('purchaseOrder')->items()->count()],
            'items.*' => ['required', 'array:actual_unit_price,purchaser_remarks'],
            'items.*.actual_unit_price' => [$this->input('action') === 'complete' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'items.*.purchaser_remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $expectedIds = $this->route('purchaseOrder')->items()->pluck('id')->map(fn (int $id): string => (string) $id)->all();
            $submittedIds = array_map('strval', array_keys((array) $this->input('items', [])));

            if (array_diff($expectedIds, $submittedIds) || array_diff($submittedIds, $expectedIds)) {
                $validator->errors()->add('items', 'Submit only the items on this purchase order. Refresh the page and try again.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['items.*.actual_unit_price' => 'actual unit price', 'items.*.purchaser_remarks' => 'purchaser remarks'];
    }
}
