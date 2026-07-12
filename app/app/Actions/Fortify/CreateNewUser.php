<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input)
    {
        if (! AppSettings::bool('auth.registration_enabled', true)) {
            throw ValidationException::withMessages(['email' => 'Đăng ký đang tạm đóng.']);
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if (AppSettings::bool('auth.auto_verify_email', false)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // @phpstan-ignore-next-line Fortify documents Illuminate\Foundation\Auth\User, app model implements its auth contracts directly.
        return $user;
    }
}
