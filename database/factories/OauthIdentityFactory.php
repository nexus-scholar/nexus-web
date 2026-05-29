<?php

namespace Database\Factories;

use App\Models\OauthIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OauthIdentity>
 */
class OauthIdentityFactory extends Factory
{
    protected $model = OauthIdentity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'google',
            'provider_user_id' => fake()->uuid(),
            'email' => fake()->safeEmail(),
            'email_verified_at' => now(),
            'last_login_at' => now(),
        ];
    }
}
