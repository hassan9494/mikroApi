<?php

namespace Modules\Shop\Http\Resources;

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
            'items' => $this->getItems(),
            'date' => $this->date,
            'completed_at' => $this->completed_at,
            'status' => $this->status,
        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'name' => $product->name,
                'price' => $product->pivot->buy_price,
                'quantity' => $product->pivot->quantity,
                'image' => $product->getMedia()->first()?->getFullUrl(),
            ];
        }
        return $items;
    }

}
