<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '519'.fake()->unique()->numerify('########'),
            'email' => fake()->optional()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
            'sede_id' => null,
            'custom_fields' => null,
        ];
    }
}
