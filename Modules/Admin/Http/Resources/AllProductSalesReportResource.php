<?php

namespace Modules\Admin\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class AllProductSalesReportResource extends JsonResource
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
            'all_sales_with_pending' => $this->allSalesWithPending($from, $to),
            'is_kit' => $this->isKit(), // Whether this product is a kit
            'kit_products_count' => $this->kit ? count($this->kit) : 0, // How many products are in this kit
            'in_kits_count' => count($this->inKits), // How many kits contain this product
            'sales_with_kit' => count($this->inKits) == 0 ? $this->sales($from, $to) :$this->totalSalesWithKit($from, $to),
            'all_sales_with_kit' => count($this->inKits) == 0 ?$this->allSalesWithPending($from, $to): $this->totalAllSalesWithKitWithPending($from, $to),
        ];
    }

    public function with($request)
    {
        return [
            'additional' => $this->additional
        ];
    }
}




