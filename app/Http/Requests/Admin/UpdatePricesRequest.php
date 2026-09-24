<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Prices are keyed by product ID, then by variant index: prices[{product}][{variant}].
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
