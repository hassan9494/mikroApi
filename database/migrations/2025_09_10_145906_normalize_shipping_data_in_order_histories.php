<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shop\Entities\OrderHistory;

class NormalizeShippingDataInOrderHistories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get all order histories with shipping field
        $histories = OrderHistory::where('field', 'shipping')->get();

        foreach ($histories as $history) {
            try {
                // Decode JSON values
                $oldValue = json_decode($history->old_value, true);
                $newValue = json_decode($history->new_value, true);

                // Normalize old value
                if (is_array($oldValue)) {
                    if (isset($oldValue['cost'])) {
                        $oldValue['cost'] = (string) $oldValue['cost'];
                    }
                    // Ensure all expected fields are present
                    $oldValue = array_merge([
                        'city' => '',
                        'address' => '',
                        'cost' => '0',
                        'status' => 'WAITING',
                        'free' => false
                    ], $oldValue);

                    $history->old_value = json_encode($oldValue);
                }

                // Normalize new value
                if (is_array($newValue)) {
                    if (isset($newValue['cost'])) {
                        $newValue['cost'] = (string) $newValue['cost'];
                    }
                    // Ensure all expected fields are present
                    $newValue = array_merge([
                        'city' => '',
                        'address' => '',
                        'cost' => '0',
                        'status' => 'WAITING',
                        'free' => false
                    ], $newValue);

                    $history->new_value = json_encode($newValue);
                }

                $history->save();
            } catch (\Exception $e) {
                // Log any errors but continue processing
                \Log::error("Failed to normalize shipping data for history ID {$history->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_histories', function (Blueprint $table) {
            //
            throw new \Exception("This migration cannot be reversed.");

        });
    }
}
