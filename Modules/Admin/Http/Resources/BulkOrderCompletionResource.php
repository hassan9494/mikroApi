<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BulkOrderCompletionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'payment_method' => $this->paymentMethod,
            'details' => $this->bulkCompletionOrders,
            'total_calculated_amount' => (float) $this->total_calculated_amount,
            'total_entered_amount' => (float) $this->total_entered_amount,
            'total_calculated_shipping' => (float) $this->total_calculated_shipping,
            'total_entered_shipping' => (float) $this->total_entered_shipping,
            'total_calculated_commission' => (float) $this->total_calculated_commission,
            'total_entered_commission' => (float) $this->total_entered_commission,
            'notes' => $this->notes,
            'orders' => OrderResource::collection($this->orders),
            'created_at' => $this->created_at,
        ];
    }
}
