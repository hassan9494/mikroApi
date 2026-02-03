<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ProductOrderExportResource extends JsonResource
{
    private $productId;
    private $kitProductIds = [];

    public function __construct($resource, $productId = null, $kitProductIds = [])
    {
        parent::__construct($resource);
        $this->productId = $productId;
        $this->kitProductIds = $kitProductIds;
    }

    public function toArray($request): array
    {
        // Calculate quantities for this specific product in the order
        $directQuantity = 0;
        $kitQuantity = 0;

        if ($this->productId) {
            // Find direct product in order
            $directProduct = $this->products->where('id', $this->productId)->first();
            $directQuantity = $directProduct ? $directProduct->pivot->quantity : 0;

            // Calculate kit quantities
            foreach ($this->products as $orderProduct) {
                if (in_array($orderProduct->id, $this->kitProductIds)) {
                    $kitPivot = DB::table('product_kit')
                        ->where('kit_id', $orderProduct->id)
                        ->where('product_id', $this->productId)
                        ->first();

                    if ($kitPivot) {
                        $kitQuantity += ($orderProduct->pivot->quantity * $kitPivot->quantity);
                    }
                }
            }
        }

        // Format dates
        $createdAt = $this->created_atl;
        $completedAt = $this->completed_at;
        $taxedAt = $this->taxed_at;

        // Parse customer data
        $customer = $this->customer;
        if (is_string($customer)) {
            $customer = json_decode($customer, true);
        }

        // Calculate financials (similar to OrderResource but with product quantities)
        $discount = (float) ($this->discount ?? 0);
        $subtotal = (float) ($this->subtotal ?? 0);
        $taxAmount = $this->getTaxAmountAttribute();
        $total = (float) ($this->total ?? 0);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'created_at' => $createdAt,
            'completed_at' => $completedAt,
            'taxed_at' => $taxedAt,
            'customer' => $customer,
            'customer_name' => $customer->name ?? '',
            'customer_phone' => $customer->phone ?? '',
            'customer_email' => $customer->email ?? '',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'tax_amount' => $taxAmount,
            'tax_number' => $this->tax_number,
            'direct_quantity' => $directQuantity,
            'kit_quantity' => $kitQuantity,
            'total_product_quantity' => $directQuantity + $kitQuantity,
            'is_kit_sale' => $kitQuantity > 0,
            // Add other fields that might be needed for Excel export
            'identity_number_type' => $this->identity_number_type,
            'customer_identity_number' => $this->customer_identity_number,
            'uuid' => $this->uuid,
            'notes' => $this->notes,
        ];
    }

    public static function collection($resource, $productId = null, $kitProductIds = [])
    {
        return parent::collection($resource)->additional([
            'product_id' => $productId,
            'export_info' => [
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'product_id' => $productId,
                'kit_product_ids' => $kitProductIds,
            ]
        ]);
    }
}
