<?php

namespace App\Http\Requests;

use App\Services\DailyOrderSummary;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin || $this->user()?->is_purchaser);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'all_dates' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['date.date_format' => 'Pick a day from the calendar.'];
    }

    public function day(): ?CarbonImmutable
    {
        if ($this->boolean('all_dates')) {
            return null;
        }

        $date = $this->validated('date');

        return $date ? CarbonImmutable::parse($date, DailyOrderSummary::TIMEZONE) : DailyOrderSummary::today();
    }
}
