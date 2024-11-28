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
            return [
                'id' => $this->id,
                'name' => $this->name,
                'stock' => $this->stock,
                'price' => $this->price->normal_price,
                'real_price' => $this->price->real_price,
                'image' => $this->getFirstMediaUrl(),
                'min_qty' => $this->min_qty,
                'sku'  => $this->sku,
                'source_sku' => $this->source_sku,
                'sales' => $this->sales(null, null),
                'priceAll' => $priceAll,
                'allRealPrice' => $realPriceAll,
                'slug' => $this->slug,
                'source' =>  $source ? $source->name : null,
            ];


    }
}
