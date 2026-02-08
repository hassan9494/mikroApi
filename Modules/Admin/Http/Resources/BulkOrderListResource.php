<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BulkOrderListResource extends JsonResource
{
    public function toArray($request)
    {
        // Calculate existing payments
        $existingPayments = $this->transactions->sum(function($transaction) {
            if ($transaction->type === 'deposit') {
                return $transaction->amount;
            } else if ($transaction->type === 'refund') {
                return -$transaction->amount;
            }
            return 0;
        });

        // Calculate remaining balance
        $shippingFree = $this->shipping && ($this->shipping->free ?? false);
        $shippingCost = $shippingFree ? 0 : ($this->shipping->cost ?? 0);
        $remainingBalance = $this->total - $existingPayments - $shippingCost;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer' => $this->customer,
            'total' => (float) $this->total,
            'remaining_balance' => (float) max(0, $remainingBalance),
            'shipping' => $this->shipping,
            'shipping_provider_id' => $this->shipping_provider_id,
            'shipping_provider' => $this->shippingProvider,
            'status' => $this->status,
            'products_count' => $this->products->count(),
            'created_at' => $this->created_at,
            'is_pending' => $this->status === 'PENDING',
            'is_processing' => $this->status === 'PROCESSING',
            'transactions' => TransactionResource::collection($this->transactions),
        ];
    }
}
