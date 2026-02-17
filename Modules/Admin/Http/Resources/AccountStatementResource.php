<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountStatementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'account_number'  => $this->account_number,
            'account_name'    => $this->account_name,
            'address'         => $this->address,
            'phone'           => $this->phone,
            'from_date'       => $this->from_date,
            'to_date'         => $this->to_date,
            'total_debtor'    => (float) $this->total_debtor,
            'total_creditor'  => (float) $this->total_creditor,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at,
            'user'            => $this->whenLoaded('user', fn() => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'details'         => AccountStatementDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
