<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortLinkIndividualClick extends Model
{
    protected $fillable = [
        'click_summary_id'
    ];

    public $timestamps = false;

    /**
     * Relationship with click summary
     */
    public function clickSummary(): BelongsTo
    {
        return $this->belongsTo(ShortLinkClickSummary::class, 'click_summary_id');
    }
}
