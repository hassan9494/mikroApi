<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedPointsSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $settings = [
            [
                'key' => 'points_earning_percentage',
                'name' => 'Points Earning Percentage',
                'description' => 'Percentage of order total to award as points (e.g., 10 = 10%)',
                'value' => json_encode(10),
                'field' => 'number',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'points_expiry_days',
                'name' => 'Points Expiry Days',
                'description' => 'Number of days before points expire (e.g., 365 = 1 year)',
                'value' => json_encode(365),
                'field' => 'number',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'points_exchange_rate',
                'name' => 'Points Exchange Rate',
                'description' => 'Value of 1 point in currency (e.g., 0.10 = $0.10 per point)',
                'value' => json_encode(0.10),
                'field' => 'number',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'points_min_order_total',
                'name' => 'Minimum Order Total',
                'description' => 'Minimum order total after points discount (e.g., 1 = $1.00)',
                'value' => json_encode(1.00),
                'field' => 'number',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'points_max_per_order',
                'name' => 'Maximum Points Per Order',
                'description' => 'Maximum points that can be used per order',
                'value' => json_encode(100),
                'field' => 'number',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'points_enabled',
                'name' => 'Points System Enabled',
                'description' => 'Enable or disable the points system',
                'value' => json_encode(true),
                'field' => 'boolean',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('settings')->whereIn('key', [
            'points_earning_percentage',
            'points_expiry_days',
            'points_exchange_rate',
            'points_min_order_total',
            'points_max_per_order',
            'points_enabled',
        ])->delete();
    }
}
