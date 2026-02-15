<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountStatementDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'transaction_number'=> $this->transaction_number,
            'description'       => $this->description,
            'transaction_type'  => $this->transaction_type,
            'debtor'            => (float) $this->debtor,
            'creditor'          => (float) $this->creditor,
            'balance'           => (float) $this->balance,
            'transaction_date'  => $this->transaction_date,
            'order'             => $this->whenLoaded('order', fn() => [
                'id'     => $this->order->id,
                'number' => $this->order->number,
            ]),
        ];
    }
}
