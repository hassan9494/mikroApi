<?php

namespace Modules\Admin\Http\Resources;

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

            'products' => $this->getItems(),

            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'updated_at' => $this->updated_at,

            'status' => $this->status,
            'note' => $this->note,

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
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $product->pivot->quantity,
                'price' => $product->pivot->price,
                'image' => $product->getFirstMediaUrl()
            ];
        }
        return $items;
    }

}
