<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShortLinkClickSummary extends Model
{
    protected $fillable = [
        'short_link_id',
        'ip_address',
        'country',
        'click_count'
    ];

    /**
     * Relationship with short link
     */
    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }

    /**
     * Relationship with individual clicks
     */
    public function individualClicks(): HasMany
    {
        return $this->hasMany(ShortLinkIndividualClick::class, 'click_summary_id');
    }

    /**
     * Get all click datetimes for this IP
     */
    public function getClickTimes()
    {
        return $this->individualClicks()
            ->orderBy('clicked_at', 'asc')
            ->pluck('clicked_at');
    }
}
