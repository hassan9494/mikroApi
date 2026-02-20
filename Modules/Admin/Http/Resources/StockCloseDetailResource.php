<?php

namespace Modules\Admin\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCloseDetailResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'stock_close_id' => $this->stock_close_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'stock' => $this->stock,
            'price' => $this->price,
            'real_price' => $this->real_price,
            'purchases_quantity' => $this->purchases_quantity,
            'price_all' => $this->price_all,
            'product_real_price_all' => $this->product_real_price_all,
            'air_source_sku' => $this->air_source_sku,
            'sea_source_sku' => $this->sea_source_sku,
            'local_source_sku' => $this->local_source_sku,
            'stock_location' => $this->stock_location,
            'store_location' => $this->store_location,
            'link' => $this->link,
            'air_source' => $this->air_source,
            'sea_source' => $this->sea_source,
            'local_source' => $this->local_source,
            'airSource' => $this->airSource?->name,
            'seaSource' => $this->seaSource?->name,
            'localSource' => $this->localSource?->name,
            'updated_at' => Carbon::parse($this->updated_at)->format('Y-m-d'),
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d'),
        ];
    }

}
