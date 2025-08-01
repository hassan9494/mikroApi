<?php

namespace Modules\Admin\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,

            'products' => $this->getItems(),

            'created_at' => $this->created_at,
            'date' => Carbon::parse($this->date)->format('Y-m-d'),
            'completed_at' => $this->completed_at,
            'updated_at' => $this->updated_at,

            'status' => $this->status,
            'note' => $this->note,
            'source_id' => $this->source_id != null ? $this->source_id : null,
            'attachments' => MediaResource::collection($this->getMedia()),
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
                'sku' => $product->sku,
                'source_sku' => $product->pivot->source_sku,
                'quantity' => $product->pivot->quantity,
                'purchases_price' => $product->pivot->purchases_price,
                'distributer_price' => $product->pivot->distributer_price,
                'normal' => $product->pivot->normal,
                'sale_price' => $product->pivot->sale_price,
                'image' => $product->getFirstMediaUrl()
            ];
        }
        return $items;
    }

}
