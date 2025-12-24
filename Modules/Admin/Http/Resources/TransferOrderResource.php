<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransferOrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email
            ] : null,
            'approved_by' => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email
            ] : null,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'products' => $this->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product->name ?? 'Unknown',
                    'product_sku' => $product->product->sku ?? 'N/A',
                    'product_image' => $product->product->getFirstMediaUrl() ? asset($product->product->getFirstMediaUrl()) : null,
                    'from_location' => $product->from_location,
                    'to_location' => $product->to_location,
                    'quantity' => $product->quantity,
                    'from_location_label' => $product->from_location_label,
                    'to_location_label' => $product->to_location_label,
                    'current_stock_available' => $product->product->stock_available ?? 0,
                    'current_store_available' => $product->product->store_available ?? 0,

                    // Stock history fields
                    'stock_before' => $product->stock_before,
                    'stock_after' => $product->stock_after,
                    'stock_available_before' => $product->stock_available_before,
                    'stock_available_after' => $product->stock_available_after,
                    'store_available_before' => $product->store_available_before,
                    'store_available_after' => $product->store_available_after,

                    'has_stock_history' => !is_null($product->stock_before) && !is_null($product->stock_after),

                    // Calculate differences
                    'stock_difference' => $product->stock_after - $product->stock_before,
                    'stock_available_difference' => $product->stock_available_after - $product->stock_available_before,
                    'store_available_difference' => $product->store_available_after - $product->store_available_before,
                ];
            }),

            'histories' => $this->histories->map(function ($history) {
                // Get parsed values using the fixed accessors
                $parsedOldValue = $history->parsed_old_value;
                $parsedNewValue = $history->parsed_new_value;

                // Format values for display
                $formatValue = function ($value) {
                    if (is_null($value)) {
                        return 'N/A';
                    }

                    if (is_array($value)) {
                        return json_encode($value);
                    }

                    if (is_bool($value)) {
                        return $value ? 'true' : 'false';
                    }

                    return (string) $value;
                };

                return [
                    'id' => $history->id,
                    'action' => $history->action,
                    'action_label' => $history->action_label,
                    'field' => $history->field,
                    'change_type' => $history->change_type,
                    'change_description' => $history->change_description,
                    'is_product_change' => $history->is_product_change,

                    // Raw values from database
                    'old_value' => $history->old_value,
                    'new_value' => $history->new_value,

                    // Parsed values (using the fixed accessors)
                    'parsed_old_value' => $parsedOldValue,
                    'parsed_new_value' => $parsedNewValue,

                    // Formatted for display
                    'old_value_display' => $formatValue($parsedOldValue),
                    'new_value_display' => $formatValue($parsedNewValue),

                    'notes' => $history->notes,
                    'product' => $history->product ? [
                        'id' => $history->product->id,
                        'name' => $history->product->name,
                        'sku' => $history->product->sku,
                        'image' => $history->product->getFirstMediaUrl() ? asset($history->product->getFirstMediaUrl()) : null,
                    ] : null,
                    'user' => $history->user ? [
                        'id' => $history->user->id,
                        'name' => $history->user->name,
                        'email' => $history->user->email
                    ] : null,
                    'created_at' => $history->created_at,
                    'created_at_formatted' => $history->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $history->created_at->diffForHumans()
                ];
            }),

            'can_edit' => $this->can_edit,
            'can_complete' => $this->can_complete,
            'can_revert' => $this->can_revert,
            'can_delete' => $this->can_edit, // Can only delete if editable
            'summary' => [
                'total_products' => $this->products->count(),
                'total_quantity' => $this->products->sum('quantity'),
                'from_stock_count' => $this->products->where('from_location', 'stock_available')->count(),
                'to_stock_count' => $this->products->where('to_location', 'stock_available')->count()
            ],
            'status_label' => $this->formatted_status
        ];
    }
}
