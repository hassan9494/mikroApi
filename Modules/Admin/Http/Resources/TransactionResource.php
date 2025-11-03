<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'note' => $this->transaction_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'commission' => $this->commission,
            'shipping' => $this->shipping,
            'total_amount' => $this->total_amount,
            'order_id' => $this->order_id,
            'paymentMethod' => $this->paymentMethod?->name,
        ];
    }

}
