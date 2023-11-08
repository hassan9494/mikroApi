<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomsStatementResource extends JsonResource
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
            'invoice_2_percent' => $this->invoice_2_percent,
            'media' => $this->getFirstMediaUrl(),
            'notes' => $this->notes
        ];
    }

}
