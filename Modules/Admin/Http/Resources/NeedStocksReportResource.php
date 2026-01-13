<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shop\Entities\Source;

class NeedStocksReportResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $priceAll = $this->stock * $this->price->normal_price;
        $realPriceAll = $this->stock * $this->price->real_price;
        $source = Source::find($this->source_id);
        $airSource = Source::find($this->air_source_id);
        $seaSource = Source::find($this->sea_source_id);
        $localSource = Source::find($this->local_source_id);
            return [
                'id' => $this->id,
                'name' => $this->name,
                'stock' => $this->stock,
                'price' => $this->price->normal_price,
                'real_price' => $this->price->real_price,
                'image' => $this->getFirstMediaUrl(),
                'min_qty' => $this->min_qty,
                'source' =>  $source ? $source->name : null,
                'air_source' =>  $airSource ? $airSource->name : null,
                'sea_source' =>  $seaSource ? $seaSource->name : null,
                'local_source' =>  $localSource ? $localSource->name : null,
                'air_min_qty' => $this->air_min_qty,
                'sea_min_qty' => $this->sea_min_qty,
                'local_min_qty' => $this->local_min_qty,
                'order_qty' => $this->order_qty,
                'air_order_qty' => $this->air_order_qty,
                'sea_order_qty' => $this->sea_order_qty,
                'local_order_qty' => $this->local_order_qty,
                'purchases_qty' => $this->purchases_qty,
                'sku'  => $this->sku,
                'source_sku' => $this->source_sku,
                'air_source_sku' => $this->air_source_sku,
                'sea_source_sku' => $this->sea_source_sku,
                'local_source_sku' => $this->local_source_sku,
                'sales' => $this->sales(null, null),
                'priceAll' => $priceAll,
                'allRealPrice' => $realPriceAll,
                'slug' => $this->slug,
                'location' => $this->location,
                'stock_location' => $this->stock_location,
            ];


    }
}
