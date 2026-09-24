<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public const MAX_VARIANTS = 6;

    public const MAX_PRICE = 1000000;

    /**
     * Largest photo accepted, in kilobytes. Matches PHP's default upload_max_filesize (2M);
     * the admin page shrinks bigger phone photos in the browser before uploading.
     */
    public const MAX_IMAGE_KILOBYTES = 2048;

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('products', 'name')->ignore($this->route('product'))],
            'note' => ['nullable', 'string', 'max:100'],
            'variants' => ['required', 'array', 'min:1', 'max:'.self::MAX_VARIANTS],
            'variants.*.label' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'variants.*.price' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PRICE],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_IMAGE_KILOBYTES],
            'remove_image' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A veggie with this name already exists.',
            'variants.required' => 'Add at least one size and price.',
            'variants.max' => 'A veggie can have up to '.self::MAX_VARIANTS.' sizes.',
            'variants.*.label.required' => 'Enter a size, e.g. “per kg”.',
            'variants.*.label.distinct' => 'Each size needs a different name.',
            'variants.*.price.required' => 'Enter a price.',
            'variants.*.price.integer' => 'Use whole pesos only.',
            'variants.*.price.min' => 'Price must be at least ₱1.',
            'variants.*.price.max' => 'Price is too high.',
            'image.image' => 'Use a JPG, PNG or WebP photo.',
            'image.mimes' => 'Use a JPG, PNG or WebP photo.',
            'image.max' => 'The photo must be 2 MB or smaller.',
            'image.uploaded' => 'The photo couldn’t be uploaded. Try a smaller one (under 2 MB).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'variants.*.label' => 'size',
            'variants.*.price' => 'price',
        ];
    }

    /**
     * Product attributes ready to save, with variants re-indexed and prices as whole pesos.
     *
     * @return array{name: string, note: string|null, variants: list<array{label: string, price: int}>}
     */
    public function productAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'note' => $this->validated('note'),
            'variants' => collect($this->validated('variants'))
                ->map(fn (array $variant): array => [
                    'label' => $variant['label'],
                    'price' => (int) $variant['price'],
                ])
                ->values()
                ->all(),
        ];
    }
}
