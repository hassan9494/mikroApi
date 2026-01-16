<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductVariant;

class OrderExcelResource extends JsonResource
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
            'customer' => $this->customer,

            'taxed_at' => $this->taxed_at,

            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'discount_percentage' => $this->discount_percentage,
            'total' => $this->total,
            'coupon_discount' => $this->coupon_discount,

            'status' => $this->status,
            'tax_number' => $this->tax_number,
            'tax_amount' => $this->tax_amount,
            'identity_number_type' => $this->identity_number_type,
            'customer_identity_number' => $this->customer_identity_number,
        ];
    }


}
