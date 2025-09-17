<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shop\Entities\Order;

class FixMissingCityInShippingData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
//        ini_set('memory_limit', '4096M');
        $orders = Order::whereNotNull('shipping')->get();

        if ($orders){
            foreach ($orders as $order) {
                $shipping = (array) $order->shipping;

                // Add city field if it's missing
                if (!isset($shipping['city'])) {
                    // Try to get city from city_id relationship
                    if ($order->city_id && $order->city) {
                        $shipping['city'] = $order->city->name;
                    } else {
                        $shipping['city'] = '';
                    }

                    $order->shipping = $shipping;
                    $order->save();
                }
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
        Schema::table('shipping_data', function (Blueprint $table) {
            //
        });
    }
}
