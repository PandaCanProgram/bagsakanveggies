<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Prices are keyed by product ID, then by variant index: prices[{product}][{variant}].
     * The category says which list (Veggies or Fruits) to go back to.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::in(array_keys(Product::CATEGORIES))],
            'prices' => ['required', 'array'],
            'prices.*' => ['required', 'array'],
            'prices.*.*' => ['required', 'integer', 'min:1', 'max:'.SaveProductRequest::MAX_PRICE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.*.*.required' => 'Enter a price.',
            'prices.*.*.integer' => 'Use whole pesos only.',
            'prices.*.*.min' => 'Must be at least ₱1.',
            'prices.*.*.max' => 'Price is too high.',
        ];
    }
}
