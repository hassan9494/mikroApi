<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'reference_number',
        'status',
        'notes',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(StockCountProduct::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StockCountHistory::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
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

    public function getFormattedStatusAttribute()
    {
        $statusColors = [
            'draft' => 'info',
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
        ];

        return [
            'text' => ucfirst($this->status),
            'color' => $statusColors[$this->status] ?? 'secondary',
        ];
    }

    public function getIsEditableAttribute()
    {
        if (in_array($this->status, ['approved', 'rejected'])) {
            return false;
        }

        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // Draft counts can be edited by the creator
        if ($this->status === 'draft' && $user->id === $this->user_id) {
            return true;
        }

        // Pending counts can be edited by the creator or by super/admin/Stock Manager
        if ($this->status === 'pending') {
            if ($user->id === $this->user_id) {
                return true;
            }
            if ($user->can('stock_count_approve')) {
                return true;
            }
        }

        return false;
    }

    public function getCanSubmitAttribute()
    {
        if ($this->status !== 'draft') {
            return false;
        }

        return $this->products()->whereNotNull('store_available_counted')->exists();
    }
}
