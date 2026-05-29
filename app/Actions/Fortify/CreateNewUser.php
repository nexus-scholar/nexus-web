<?php

namespace App\Actions\Fortify;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Workspaces\CreatePersonalWorkspace;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly CreatePersonalWorkspace $createPersonalWorkspace,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $this->audit->handle('user.registered', $user, $user);
        $this->createPersonalWorkspace->handle($user);

        return $user->fresh();
    }
}
