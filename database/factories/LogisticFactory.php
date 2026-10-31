<?php

namespace Database\Factories;

use App\Models\Logistic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Logistic>
 */
class LogisticFactory extends Factory
{
    protected $model = Logistic::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('09#########'),
            'address' => fake()->address(),
            'api_address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'status' => 'active',
            'approved_at' => now(),

            'sec_registration_number' => fake()->unique()->numerify('SEC-########'),
            'tin' => fake()->unique()->numerify('###-###-###-###'),

            'business_region' => 'Region IV-A',
            'business_province' => 'Laguna',
            'business_city' => 'Santa Cruz',
            'business_barangay' => 'Poblacion',
            'business_street' => fake()->streetAddress(),
            'business_postal_code' => '4009',

            'contact_first_name' => fake()->firstName(),
            'contact_last_name' => fake()->lastName(),
            'contact_position' => 'Operations Manager',

            'service_areas' => 'Laguna',
        ];
    }
}