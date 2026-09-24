<?php

namespace Database\Factories;

use App\Models\WasteBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WasteBank> */
class WasteBankFactory extends Factory
{
    protected $model = WasteBank::class;

    public function definition(): array
    {
        return [
            'code' => 'BS'.fake()->unique()->numerify('###'),
            'name' => 'Bank Sampah '.fake()->company(),
            'address' => fake()->address(),
            'status' => true,
        ];
    }
}
