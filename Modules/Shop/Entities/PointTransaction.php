<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    /**
     * Transaction types
     */
    const TYPE_EARN = 'earn';
    const TYPE_SPEND = 'spend';
    const TYPE_EXPIRE = 'expire';
    const TYPE_REFUND = 'refund';
    const TYPE_ADJUST = 'adjust';

    /**
     * Point sources
     */
    const SOURCE_ORDER = 'order';
    const SOURCE_REVIEW = 'review';
    const SOURCE_REFERRAL = 'referral';
    const SOURCE_PROMOTION = 'promotion';
    const SOURCE_ADMIN = 'admin';

    /**
     * @var string
     */
    protected $table = 'point_transactions';

    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'points',
        'balance_after',
        'expires_at',
        'source',
        'description',
        'metadata',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'metadata' => 'object',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @var array
     */
    protected $appends = [
        'is_expired',
        'type_label',
    ];

    /**
     * Check if the points are expired
     *
     * @return bool
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    /**
     * Get human-readable type label
     *
     * @return string
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            self::TYPE_EARN => 'Earned',
            self::TYPE_SPEND => 'Spent',
            self::TYPE_EXPIRE => 'Expired',
            self::TYPE_REFUND => 'Refunded',
            self::TYPE_ADJUST => 'Adjusted',
            default => ucfirst($this->type),
        };
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Scope to get only non-expired earn transactions
     */
    public function scopeAvailable($query)
    {
        return $query->where('type', self::TYPE_EARN)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to get transactions by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get transactions by user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get transactions by source
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }
}
