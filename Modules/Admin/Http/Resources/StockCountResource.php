<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockCountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'formatted_status' => $this->formatted_status,
            'notes' => $this->notes,

            'is_editable' => $this->is_editable,
            'can_submit' => $this->can_submit,

            'submitted_at' => $this->submitted_at,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'rejection_reason' => $this->rejection_reason,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Summary stats
            'total_products' => $this->whenLoaded('products', function () {
                return $this->products->count();
            }),
            'store_counted_products' => $this->whenLoaded('products', function () {
                return $this->products->whereNotNull('store_available_counted')->count();
            }),
            'stock_counted_products' => $this->whenLoaded('products', function () {
                return $this->products->whereNotNull('stock_available_counted')->count();
            }),
            'products_with_store_difference' => $this->whenLoaded('products', function () {
                return $this->products->where('store_available_difference', '!=', 0)
                    ->whereNotNull('store_available_difference')->count();
            }),
            'products_with_stock_difference' => $this->whenLoaded('products', function () {
                return $this->products->where('stock_available_difference', '!=', 0)
                    ->whereNotNull('stock_available_difference')->count();
            }),

            'products' => StockCountProductResource::collection($this->whenLoaded('products')),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            'approver' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                    'email' => $this->approver->email,
                ] : null;
            }),
        ];
    }
}
