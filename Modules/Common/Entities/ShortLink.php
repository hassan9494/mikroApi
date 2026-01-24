<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShortLink extends Model
{
    protected $fillable = [
        'name',
        'link',
        'short_id',
        'total_clicks'
    ];

    /**
     * Relationship with click summaries
     */
    public function clickSummaries(): HasMany
    {
        return $this->hasMany(ShortLinkClickSummary::class);
    }

    /**
     * Track a click on this short link
     */
    public function trackClick($ip, $country = null)
    {
        // Find or create click summary for this IP
        $summary = $this->clickSummaries()
            ->where('ip_address', $ip)
            ->first();

        if ($summary) {
            // Update existing summary
            $summary->increment('click_count');
        } else {
            // Create new summary
            $summary = $this->clickSummaries()->create([
                'ip_address' => $ip,
                'country' => $country,
                'click_count' => 1
            ]);
        }

        // Log individual click
        $summary->individualClicks()->create([]);

        // Update total clicks counter
        $this->increment('total_clicks');

        return $summary;
    }

    /**
     * Get all click statistics
     */
    public function getClickStats()
    {
        return [
            'total_clicks' => $this->total_clicks,
            'unique_visitors' => $this->clickSummaries()->count(),
            'countries' => $this->clickSummaries()
                ->select('country', \DB::raw('SUM(click_count) as total'))
                ->groupBy('country')
                ->get()
                ->pluck('total', 'country'),
            'top_ips' => $this->clickSummaries()
                ->orderBy('click_count', 'desc')
                ->take(10)
                ->get()
        ];
    }
}
