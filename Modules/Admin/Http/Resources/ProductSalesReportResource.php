<?php

namespace Modules\Admin\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSalesReportResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        if ($request->from != 'undefined'){
            $from = $request->from ?? null;
        }else{
            $from =  null;
        }

        if ($request->to != 'undefined'){
            $to = isset($request->to) ? Carbon::parse($request->to)->endOfDay() : null;
        }else{
            $to = null;
        }


        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'quantity' => $this->stock,
            'slug' => $this->sku,
            'price' => $this->price,
            'image' => $this->getFirstMediaUrl(),
            'sales' => $this->sales($from, $to),
            'untaxed_sales' => $this->untaxed_sales($from, $to),
            'taxed_sales' => $this->taxed_sales($from, $to),
        ];
    }

    public function with($request)
    {
        return [
            'additional' => $this->additional
        ];
    }
}




