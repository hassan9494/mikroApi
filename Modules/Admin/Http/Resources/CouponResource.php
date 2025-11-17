<?php

namespace Modules\Admin\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'amount' => $this->amount,
            'is_percentage' => $this->is_percentage,
            'count' => $this->count,
            'count_per_user' => $this->count_per_user,
            'apply_count' => $this->apply_count,
            'apply_count_per_user' => $this->apply_count_per_user,
            'start_at' =>Carbon::parse($this->start_at)->format('Y-m-d') ,
            'end_at' =>Carbon::parse($this->end_at)->format('Y-m-d') ,
            'active' => $this->active,
            'valid' => $this->valid,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'products' => $this->getItems(),
            'brands' => $this->getBrands(),

        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'id' => $product->id,
                'name' => $product->pivot->product_name,
                'image' => $product->getFirstMediaUrl()
            ];
        }
        return $items;
    }
    private function getBrands()
    {
        $items = [];
        foreach ($this->brands as $brand)
        {
            $items[] = [
                'id' => $brand->id,
                'name' => $brand->pivot->brand_name
            ];
        }
        return $items;
    }

}
