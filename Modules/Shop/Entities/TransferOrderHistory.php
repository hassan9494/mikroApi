<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
class TransferOrderHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_order_id',
        'user_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'notes',
        'product_id',
        'transfer_order_product_id',
        'change_type'
    ];

    // REMOVE THE PROBLEMATIC CASTS - Keep only datetime casts
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // FIXED: Better accessor that handles all data types
    public function getParsedOldValueAttribute()
    {
        return $this->parseValue($this->old_value);
    }

    public function getParsedNewValueAttribute()
    {
        return $this->parseValue($this->new_value);
    }

    // In TransferOrderHistory.php
    private function parseValue($value)
    {
        // If null, return null
        if (is_null($value)) {
            return null;
        }

        // If it's already an array or object, return as array
        if (is_array($value) || is_object($value)) {
            return (array) $value;
        }

        // If it's a string, try to decode as JSON
        if (is_string($value)) {
            // Remove any BOM or special characters
            $value = trim($value);

            // Check if it looks like JSON (starts with { or [)
            if (in_array(substr($value, 0, 1), ['{', '['])) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            // Try to decode anyway in case it's JSON without proper formatting
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            // If not JSON or decode failed, return the string as-is
            return $value;
        }

        // For any other type (int, float, bool), return as-is
        return $value;
    }

    // Relationships
    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(TransferOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Shop\Entities\Product::class);
    }

    public function transferOrderProduct(): BelongsTo
    {
        return $this->belongsTo(TransferOrderProduct::class);
    }

    // Attributes
    public function getActionLabelAttribute()
    {
        $actions = [
            'created' => 'Created',
            'updated' => 'Updated',
            'status_changed' => 'Status Changed',
            'product_added' => 'Product Added',
            'product_removed' => 'Product Removed',
            'product_updated' => 'Product Updated',
            'quantity_changed' => 'Quantity Changed',
            'from_location_changed' => 'From Location Changed',
            'to_location_changed' => 'To Location Changed',
            'completed' => 'Completed',
            'reverted' => 'Reverted',
            'deleted' => 'Deleted'
        ];
        return $actions[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    // Helper to format change description - FIXED VERSION
    public function getChangeDescriptionAttribute()
    {
        if ($this->notes) {
            return $this->notes;
        }

        $old = $this->parsed_old_value;
        $new = $this->parsed_new_value;

        if (is_null($old) && is_null($new)) {
            return ucfirst($this->action);
        }

        switch ($this->change_type) {
            case 'quantity':
                return "Quantity changed from {$old} to {$new}";

            case 'from_location':
                $oldLabel = $this->getLocationLabel($old);
                $newLabel = $this->getLocationLabel($new);
                return "From location changed from {$oldLabel} to {$newLabel}";

            case 'to_location':
                $oldLabel = $this->getLocationLabel($old);
                $newLabel = $this->getLocationLabel($new);
                return "To location changed from {$oldLabel} to {$newLabel}";

            case 'product':
                if ($this->action === 'product_added') {
                    if (is_array($new)) {
                        $productName = $new['product_name'] ?? 'Unknown';
                        $quantity = $new['quantity'] ?? 1;
                        return "Product added: {$productName} (Qty: {$quantity})";
                    }
                    return "Product added";
                } elseif ($this->action === 'product_removed') {
                    if (is_array($old)) {
                        $productName = $old['product_name'] ?? 'Unknown';
                        return "Product removed: {$productName}";
                    }
                    return "Product removed";
                }
                return "Product updated";

            case 'status':
                return "Status changed from {$old} to {$new}";

            case 'notes':
                $oldText = $old ?: 'empty';
                $newText = $new ?: 'empty';
                return "Notes changed from '{$oldText}' to '{$newText}'";

            default:
                if ($this->action === 'updated') {
                    return "Transfer order updated";
                }
                return ucfirst(str_replace('_', ' ', $this->action));
        }
    }

    private function getLocationLabel($location)
    {
        $labels = [
            'stock_available' => 'Stock',
            'store_available' => 'Store'
        ];
        return $labels[$location] ?? $location;
    }}
