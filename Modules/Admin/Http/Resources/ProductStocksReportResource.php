<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductStocksReportResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $priceAll = $this->stock * $this->price->normal_price;
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stock' => $this->stock,
            'price' => $this->price,
            'image' => $this->getFirstMediaUrl(),
            'sales' => $this->sales(null, null),
            'priceAll' => $priceAll,
        ];

    }
}
