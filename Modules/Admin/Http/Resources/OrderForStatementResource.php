<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderForStatementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'number'           => $this->number,
            'customer'         => $this->customer,
            'total'            => (float) $this->total,
            'remaining_balance'=> (float) ($this->remaining_balance ?? 0),
            'status'           => $this->status,
            'created_at'       => $this->created_at,
        ];
    }
}
