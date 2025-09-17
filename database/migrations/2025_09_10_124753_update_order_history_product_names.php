<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shop\Entities\OrderHistory;
use Modules\Shop\Entities\Product;

class UpdateOrderHistoryProductNames extends Migration
{
    public function up()
    {
        // Get all product-related history entries
        $histories = OrderHistory::where('field', 'like', 'products.%')
            ->where('field', 'like', '%.quantity')
            ->get();

        foreach ($histories as $history) {
            // Extract product ID from field
            $productId = str_replace(['products.', '.quantity'], '', $history->field);

            // Find the product (including soft-deleted ones)
            $product = Product::withTrashed()->find($productId);

            if ($product) {
                if ($history->old_value == 0) {
                    $history->notes = "Product: {$product->name} was added with quantity {$history->new_value}";
                    $history->action = 'product_added';
                } elseif ($history->new_value == 0) {
                    $history->notes = "Product: {$product->name} was removed";
                    $history->action = 'product_removed';
                } else {
                    $history->notes = "Product: {$product->name} quantity changed from {$history->old_value} to {$history->new_value}";
                    $history->action = 'quantity_updated';
                }

                $history->save();
            }
        }
    }

    public function down()
    {
        // This migration is not reversible
    }
}
