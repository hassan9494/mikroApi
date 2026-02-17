<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'user_id',
        'adjustment_type',
        'adjustment_location', // 'total', 'stock_available', 'store_available'
        'quantity',
        'stock_before',
        'stock_after',
        'stock_after_calculated',
        'stock_available_before',
        'stock_available_after',
        'store_available_before',
        'store_available_after',
        'transfer_to_location', // null or 'stock_available' or 'store_available'
        'transfer_from_location', // null or 'stock_available' or 'store_available'
        'reason',
        'status',
        'approved_by',
        'rejection_reason',
        'approved_at',
        'previous_status', // Add this for tracking status changes
        'status_changed_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'stock_after_calculated' => 'integer',
        'stock_available_before' => 'integer',
        'stock_available_after' => 'integer',
        'store_available_before' => 'integer',
        'store_available_after' => 'integer',
        'previous_status' => 'string',
        'status_changed_at' => 'datetime'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Shop\Entities\Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function getFormattedStatusAttribute()
    {
        $statusColors = [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger'
        ];

        return [
            'text' => ucfirst($this->status),
            'color' => $statusColors[$this->status] ?? 'secondary'
        ];
    }

    public function getNewStockAttribute()
    {
        if (!$this->product) {
            return null;
        }

        $currentStock = $this->product->stock;

        if ($this->adjustment_type === 'increase') {
            return $currentStock + $this->quantity;
        } else {
            return max(0, $currentStock - $this->quantity);
        }
    }

    public function getAdjustmentTypeLabelAttribute()
    {
        $types = [
            'increase' => 'Increase',
            'decrease' => 'Decrease',
            'transfer' => 'Transfer'
        ];

        return $types[$this->adjustment_type] ?? $this->adjustment_type;
    }

    public function getAdjustmentLocationLabelAttribute()
    {
        $locations = [
            'total' => 'Total Stock',
            'stock_available' => 'Stock Available',
            'store_available' => 'Store Available'
        ];

        return $locations[$this->adjustment_location] ?? $this->adjustment_location;
    }

    public function isTransfer()
    {
        return $this->adjustment_type === 'transfer';
    }

    public function getTransferDescriptionAttribute()
    {
        if (!$this->isTransfer()) {
            return null;
        }

        return "Transfer {$this->quantity} from " .
            ($this->transfer_from_location === 'stock_available' ? 'Stock Available' : 'Store Available') .
            " to " .
            ($this->transfer_to_location === 'stock_available' ? 'Stock Available' : 'Store Available');
    }
    public function getIsEditableAttribute()
    {
        $user = auth()->user();

        // Only pending adjustments can be edited
        if ($this->status !== 'pending') {
            return false;
        }

        // User can edit their own pending adjustments
        if ($user && $user->id === $this->user_id) {
            return true;
        }

        // Admin/approvers can edit any pending adjustment
        if ($user && $user->can('stock_adjustment_approve')) {
            return true;
        }

        return false;
    }

    /**
     * Check if status can be changed
     */
    public function getCanChangeStatusAttribute()
    {
        $user = auth()->user();

        // Only admin/approvers can change status
        if (!$user || !$user->can('stock_adjustment_approve')) {
            return false;
        }

        // Can change status from any state to any state
        return true;
    }

    /**
     * Check if stock needs to be reversed when changing status from approved
     */
    public function needsStockReversal($newStatus)
    {
        // If changing from approved to pending/rejected, stock needs to be reversed
        if ($this->status === 'approved' && in_array($newStatus, ['pending', 'rejected'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if stock needs to be applied when changing to approved
     */
    public function needsStockApplication($newStatus)
    {
        // If changing to approved from pending/rejected, stock needs to be applied
        if ($newStatus === 'approved' && in_array($this->status, ['pending', 'rejected'])) {
            return true;
        }

        return false;
    }

}
