<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'user_id' => $this->user_id,
            'adjustment_type' => $this->adjustment_type,
            'adjustment_type_label' => $this->adjustment_type_label,
            'adjustment_location' => $this->adjustment_location,
            'adjustment_location_label' => $this->adjustment_location_label,
            'quantity' => $this->quantity,

            // Historical stock data
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'stock_available_before' => $this->stock_available_before,
            'stock_available_after' => $this->stock_available_after,
            'store_available_before' => $this->store_available_before,
            'store_available_after' => $this->store_available_after,

            // Transfer data
            'transfer_from_location' => $this->transfer_from_location,
            'transfer_to_location' => $this->transfer_to_location,
            'is_transfer' => $this->isTransfer(),
            'transfer_description' => $this->transfer_description,

            // Editable properties
            'is_editable' => $this->is_editable,
            'can_change_status' => $this->can_change_status,
            'previous_status' => $this->previous_status,
            'status_changed_at' => $this->status_changed_at,


            // For display purposes
            'historical_stock_before' => $this->stock_before ?? 'N/A',
            'historical_stock_after' => $this->stock_after ?? 'N/A',
            'historical_stock_available_before' => $this->stock_available_before ?? 'N/A',
            'historical_stock_available_after' => $this->stock_available_after ?? 'N/A',
            'historical_store_available_before' => $this->store_available_before ?? 'N/A',
            'historical_store_available_after' => $this->store_available_after ?? 'N/A',

            // Current stock (from product - may have changed since approval)
            'current_product_stock' => $this->whenLoaded('product', function () {
                return $this->product->stock;
            }),
            'current_stock_available' => $this->whenLoaded('product', function () {
                return $this->product->stock_available;
            }),
            'current_store_available' => $this->whenLoaded('product', function () {
                return $this->product->store_available;
            }),

            'reason' => $this->reason,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'current_stock' => $this->product->stock,
                    'current_stock_available' => $this->product->stock_available,
                    'current_store_available' => $this->product->store_available,
                    'image' => $this->product->getFirstMediaUrl(),
                    'base_purchases_price' => $this->product->base_purchases_price, // add this
                    'price' => $this->product->price, // ✅ full price object with real_price

                ];
            }),

            'edited_product' => $this->when($this->product_id != $this->getOriginal('product_id'), function () {
                return [
                    'id' => $this->product_id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku
                ];
            }),


            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email
                ];
            }),

            'approver' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                    'email' => $this->approver->email
                ] : null;
            }),

            'formatted_status' => $this->getFormattedStatusAttribute(),

            // For pending requests, show calculated values
            'expected_stock_before' => $this->when($this->status === 'pending', function () {
                return $this->product ? $this->product->stock : null;
            }),
            'expected_stock_available_before' => $this->when($this->status === 'pending', function () {
                return $this->product ? $this->product->stock_available : null;
            }),
            'expected_store_available_before' => $this->when($this->status === 'pending', function () {
                return $this->product ? $this->product->store_available : null;
            }),

            'expected_stock_after' => $this->when($this->status === 'pending', function () {
                if (!$this->product) return null;

                if ($this->adjustment_type === 'transfer') {
                    return $this->product->stock; // Stock doesn't change for transfers
                }

                if ($this->adjustment_type === 'increase') {
                    return $this->product->stock + $this->quantity;
                } else {
                    return max(0, $this->product->stock - $this->quantity);
                }
            }),
        ];
    }
}
