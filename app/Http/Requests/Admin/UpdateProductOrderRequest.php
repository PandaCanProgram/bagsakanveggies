<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * The whole Veggies or Fruits list in its new order: ids[] from first to last.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(Product::CATEGORIES))],
            'ids' => ['required', 'array', 'list'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * The list must hold exactly the products on that page. It won't if a product was added, deleted or moved
     * to the other page after this one was opened, and saving it then would put things in the wrong place.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $pageIds = Product::where('category', $this->input('category'))->pluck('id')->sort()->values()->all();
                $sentIds = collect($this->input('ids'))->map(fn ($id) => (int) $id)->sort()->values()->all();

                if ($pageIds !== $sentIds) {
                    $validator->errors()->add('ids', 'The list changed since this page was opened. Reload the page and try again.');
                }
            },
        ];
    }
}
