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
            'note' => $this->note,
            'type' => $this->type,
            'amount' => $this->amount,
            'commission' => $this->commission,
            'shipping' => $this->shipping,
            'total_amount' => $this->total_amount,
            'order_id' => $this->order_id,
            'return_order_id' => $this->return_order_id,
            'payment_method_id' => $this->payment_method_id,
            'created_at' => $this->created_at,
            'paymentMethod' => $this->paymentMethod?->name,
            'receipt' => $this->receipt,
            'order' => $this->order,
            'return_order' => $this->return_order,
            'created_by' => $this->created_by,
            'created_user' => $this->createdUser?->name,
            'updated_user' => $this->updatedUser?->name,
            'updated_by' => $this->updated_by,
        ];
    }

}
