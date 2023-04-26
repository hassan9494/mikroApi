<?php

namespace Modules\Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shop\Entities\Order;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $status = $this->faker->boolean ? 'COMPLETED' : 'PROCESSING';
        $created_at = $this->faker->dateTimeBetween('-700 days');
        return [
            'customer' => [
                'name' => $this->faker->name(),
                'phone' => $this->faker->phoneNumber,
                'email' => $this->faker->email,
            ],
            'options' => [
                'taxed' => $this->faker->boolean,
                'tax_exempt' => $this->faker->boolean,
                'dept' => $this->faker->boolean(),
                'price_offer' => $this->faker->boolean(),
            ],
            'notes' => $this->faker->paragraph,
            'status' => $status,
            'created_at' => $created_at,
            'completed_at' => $status === 'COMPLETED' ? $created_at : null
        ];
    }

}
