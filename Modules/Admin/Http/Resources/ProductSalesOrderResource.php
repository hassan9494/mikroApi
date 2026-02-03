<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ProductSalesOrderResource extends JsonResource
{
    private $targetProductId;
    private $kitProductIds;

    public function __construct($resource, $targetProductId = null, $kitProductIds = [])
    {
        parent::__construct($resource);
        $this->targetProductId = $targetProductId;
        $this->kitProductIds = $kitProductIds;
    }

    public function toArray($request): array
    {
        $productQuantity = 0;
        $kitQuantity = 0;
        $isKitOrder = false;

        if ($this->targetProductId) {
            // 1. Check for direct product quantity
            foreach ($this->products as $orderProduct) {
                // Direct match
                if ($orderProduct->id == $this->targetProductId) {
                    $productQuantity = $orderProduct->pivot->quantity;
                }

                // Check if this order product is a KIT that contains our target product
                if (in_array($orderProduct->id, $this->kitProductIds)) {
                    $isKitOrder = true;

                    // Find the kit item for our target product
                    foreach ($orderProduct->kit as $kitItem) {
                        if ($kitItem->id == $this->targetProductId) {
                            // Kit quantity = order quantity × kit item quantity
                            $kitQuantity += ($orderProduct->pivot->quantity * $kitItem->pivot->quantity);
                            break;
                        }
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at,
            'taxed_at' => $this->taxed_at ,
            'customer' => $this->customer,
            'total' => $this->total,
            'product_quantity' => $productQuantity,
            'kit_quantity' => $kitQuantity,
            'total_quantity' => $productQuantity + $kitQuantity,
            'is_kit_order' => $isKitOrder,
            'order_url' => url('/order/edit/' . $this->id),
        ];
    }

    public static function collection($resource, $targetProductId = null, $kitProductIds = [])
    {
        return parent::collection($resource)->additional([
            'target_product_id' => $targetProductId,
            'kit_product_ids' => $kitProductIds
        ]);
    }
}
