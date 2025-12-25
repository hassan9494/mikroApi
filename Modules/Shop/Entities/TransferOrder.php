<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class TransferOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function products(): HasMany
    {
        return $this->hasMany(TransferOrderProduct::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TransferOrderHistory::class);
    }


    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }


    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Attributes
    public function getFormattedStatusAttribute()
    {
        $statusColors = [
            'PENDING' => 'warning',
            'COMPLETED' => 'success',
        ];

        return [
            'text' => ucfirst(strtolower($this->status)),
            'color' => $statusColors[$this->status] ?? 'secondary'
        ];
    }
    // NEW: Check if can be reverted from COMPLETED to PENDING
    public function getCanRevertAttribute()
    {
        return $this->status === 'COMPLETED';
    }

    public function getCanCompleteAttribute()
    {
        return $this->status === 'PENDING';
    }

    public function getCanEditAttribute()
    {
        return $this->status === 'PENDING';
    }

    // Methods
    public function markAsCompleted($userId)
    {
        $this->update([
            'status' => 'COMPLETED',
            'approved_by' => $userId,
            'completed_at' => now()
        ]);
    }

    public function markAsPending($userId)
    {
        $this->update([
            'status' => 'PENDING',
            'approved_by' => null,
            'completed_at' => null
        ]);
    }


    public function generateNumber()
    {
        if (!$this->number) {
            $lastOrder = self::withTrashed()->latest()->first();
            $nextId = $lastOrder ? intval(substr($lastOrder->number, 3)) + 1 : 1;
            $this->number = 'TR-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        }
    }

    public function getTotalProductsAttribute()
    {
        return $this->products->count();
    }

    public function getTotalQuantityAttribute()
    {
        return $this->products->sum('quantity');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->generateNumber();
        });

        static::created(function ($model) {
            // Record creation history
            $model->histories()->create([
                'user_id' => auth()->id(),
                'action' => 'created',
                'notes' => 'Transfer order created'
            ]);
        });
    }
}
