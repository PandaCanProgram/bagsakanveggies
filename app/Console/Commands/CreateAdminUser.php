<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:create {email? : Email address used to sign in}';

    /**
     * @var string
     */
    protected $description = 'Create an admin account for the store admin page, promote an existing user, or reset an admin password';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email address');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            if (! $user->is_admin && ! $this->confirm("{$email} already has an account. Give it admin access?", true)) {
                return self::FAILURE;
            }

            if ($this->confirm("Set a new password for {$email}?", (bool) $user->is_admin)) {
                $password = $this->askForNewPassword();

                if ($password === null) {
                    return self::FAILURE;
                }

                $user->password = $password;
            }
        } else {
            $name = $this->ask('Name', 'Admin');
            $password = $this->askForNewPassword();

            if ($password === null) {
                return self::FAILURE;
            }

            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info("{$email} can now sign in at ".route('admin.login'));

        return self::SUCCESS;
    }

    /**
     * Ask for a password twice, returning null (after printing why) when it is too short or doesn't match.
     */
    protected function askForNewPassword(): ?string
    {
        $password = (string) $this->secret('Password (at least 8 characters)');

        if (mb_strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return null;
        }

        if ($password !== $this->secret('Confirm password')) {
            $this->error('The passwords don’t match.');

            return null;
        }

        return $password;
    }
}
