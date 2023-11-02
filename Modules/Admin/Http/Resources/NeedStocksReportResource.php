<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
            return [
                'id' => $this->id,
                'name' => $this->name,
                'stock' => $this->stock,
                'price' => $this->price->normal_price,
                'image' => $this->getFirstMediaUrl(),
                'min_qty' => $this->min_qty,
                'sku'  => $this->sku,
                'sales' => $this->sales(null, null),
                'priceAll' => $priceAll,
            ];


    }
}
