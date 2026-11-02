<?php

namespace App\Http\Requests\Admin;

use App\Models\User;

class UpdatePurchaserRequest extends StorePurchaserRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return parent::authorize() && $user instanceof User && $user->is_purchaser && ! $user->is_admin;
    }

    protected function passwordIsRequired(): bool
    {
        return false;
    }
}
