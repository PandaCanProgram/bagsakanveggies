<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePurchaserRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in(array_keys(User::MANAGED_ROLES))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', function (string $attribute, string $value, Closure $fail): void {
                $query = User::withTrashed()->whereRaw('LOWER(email) = ?', [$value]);
                $user = $this->route('user');

                if ($user instanceof User) {
                    $query->whereKeyNot($user->id);
                }

                if ($query->exists()) {
                    $fail('An account with this email already exists.');
                }
            }],
            'password' => ['bail', $this->passwordIsRequired() ? 'required' : 'nullable', 'string', Password::min(8), 'confirmed', function (string $attribute, string $value, Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail('Please use a shorter password.');
                }
            }],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    protected function passwordIsRequired(): bool
    {
        return true;
    }
}
