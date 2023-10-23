<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OutlayResource extends JsonResource
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
            'amount' => $this->amount,
            'date' => $this->date,
            'invoice' => $this->invoice,
            'media' => $this->getFirstMediaUrl(),
            'notes' => $this->notes,
            'type' => $this->type,
            'sub_type' => $this->sub_type,
            'total_amount' => $this->total_amount,
            'tax' => $this->tax
        ];
    }

}
