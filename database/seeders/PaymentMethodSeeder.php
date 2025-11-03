<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Shop\Entities\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $defaultPaymentMethods = [
            [
                'name' => 'Cash',
                'commission_type' => 'Fixed',
                'commission' => 0,
            ],
            [
                'name' => 'Visa',
                'commission_type' => 'Fixed',
                'commission' => 0,
            ],
            [
                'name' => 'Click',
                'commission_type' => 'Fixed',
                'commission' => 0,
            ],
            [
                'name' => 'Bank Transfer',
                'commission_type' => 'Fixed',
                'commission' => 0,
            ],
            [
                'name' => 'Check',
                'commission_type' => 'Fixed',
                'commission' => 0,
            ],
        ];

        foreach ($defaultPaymentMethods as $defaultPaymentMethod) {
            PaymentMethod::firstOrCreate(
                ['name' => $defaultPaymentMethod['name']],
                [
                    'commission_type' => $defaultPaymentMethod['commission_type'],
                    'commission' => $defaultPaymentMethod['commission'],
                ]
            );
        }
    }
}
