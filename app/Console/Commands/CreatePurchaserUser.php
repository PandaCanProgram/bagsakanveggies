<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreatePurchaserUser extends Command
{
    protected $signature = 'purchaser:create {email? : Email address used to sign in}';

    protected $description = 'Create a purchaser account with access to assigned purchase orders';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email address');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:users,email']])->fails()) {
            $this->error('Enter a valid email address that does not already have an account.');

            return self::FAILURE;
        }

        $name = $this->ask('Name', 'Purchaser');
        $password = $this->secret('Password (at least 8 characters)');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['is_admin' => false, 'is_purchaser' => true])->save();

        $this->info("{$email} can now sign in as a purchaser at ".route('admin.login'));

        return self::SUCCESS;
    }
}
