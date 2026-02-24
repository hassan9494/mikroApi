<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\StockClose;
use Modules\Shop\Entities\StockCloseDetail;

class StoreStockCloseDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $stockCloseId;

    public function __construct($stockCloseId)
    {
        $this->stockCloseId = $stockCloseId;
    }

    public function handle()
    {
        $stockClose = StockClose::findOrFail($this->stockCloseId);

        // Build the query exactly like ?needConditionReport=stock
        $query = Product::query()
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit')) = 'false'")
            ->where('hasVariants', false);

        // Process in chunks to handle large dataset
        $query->chunk(100, function ($products) use ($stockClose) {
            // Get the IDs of products already saved for this stock close
            $existingProductIds = StockCloseDetail::where('stock_close_id', $stockClose->id)
                ->whereIn('product_id', $products->pluck('id'))
                ->pluck('product_id')
                ->toArray();

            // Filter out products that already exist
            $newProducts = $products->reject(function ($product) use ($existingProductIds) {
                return in_array($product->id, $existingProductIds);
            });

            if ($newProducts->isEmpty()) {
                return; // Nothing new to insert
            }

            $details = [];
            foreach ($newProducts as $product) {
                // Access price JSON field (cast to object)
                $price = $product->price ?? (object) ['normal_price' => 0, 'real_price' => 0];
                $normalPrice = $price->normal_price ?? 0;
                $realPrice   = $price->real_price ?? 0;

                $details[] = [
                    'stock_close_id'          => $stockClose->id,
                    'product_id'               => $product->id,
                    'product_name'              => $product->name,
                    'stock'                     => $product->stock,
                    'price'                     => $normalPrice,
                    'real_price'                => $realPrice,
                    'purchases_quantity'        => $product->purchases_qty,
                    'price_all'                 => $product->stock * $normalPrice,
                    'product_real_price_all'    => $product->stock * $realPrice,
                    'air_source_sku'            => $product->air_source_sku ?? '',
                    'sea_source_sku'            => $product->sea_source_sku ?? '',
                    'local_source_sku'          => $product->local_source_sku ?? '',
                    'stock_location'            => $product->stock_location ?? '',
                    'store_location'            => $product->location ?? '',
                    'link'                       => $this->generateProductLink($product->sku) ?? '',
                    'air_source'                 => $product->air_source_id,
                    'sea_source'                 => $product->sea_source_id,
                    'local_source'               => $product->local_source_id,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ];
            }

            // Bulk insert only new records
            StockCloseDetail::insert($details);
        });
    }

    private function generateProductLink($sku)
    {
        $frontendUrl = env('FRONTEND_URL', config('app.url'));
        return rtrim($frontendUrl, '/') . '/product/' . $sku;
    }
}
