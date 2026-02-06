<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CouponReportResource extends JsonResource
{
    public function toArray($request)
    {
        $now = Carbon::now();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'amount' => $this->amount,
            'is_percentage' => $this->is_percentage,
            'type' => $this->is_percentage ? 'Percentage' : 'Fixed Amount',
            'formatted_amount' => $this->is_percentage ? $this->amount . '%' : '$' . number_format($this->amount, 2),
            'start_at' => $this->start_at?->format('Y-m-d'),
            'end_at' => $this->end_at?->format('Y-m-d'),
            'count' => $this->count,
            'use_count' => $this->use_count,
            'remaining_uses' => max(0, $this->count - $this->use_count),
            'active' => $this->active,
            'valid' => $this->valid,
            'status' => $this->getStatus(),

            // Calculated fields
            'orders_count' => $this->orders_count ?? $this->orders->count(),
            'total_discount' => $this->total_discount ?? $this->orders->sum('discount'),
            'total_orders_value' => $this->total_orders_value ?? $this->orders->sum('subtotal'),
            'average_discount' => $this->average_discount ?? 0,
            'redemption_rate' => $this->redemption_rate ?? 0,

            // Dates info
            'days_remaining' => $this->end_at ? $now->diffInDays($this->end_at, false) : null,
            'is_expired' => $this->end_at && $this->end_at->isPast(),
            'is_upcoming' => $this->start_at && $this->start_at->isFuture(),

            // Additional info
            'has_products' => $this->products->isNotEmpty(),
            'has_brands' => $this->brands->isNotEmpty(),
            'has_users' => $this->users->isNotEmpty(),
        ];
    }

    private function getStatus()
    {
        if (!$this->active) return 'Inactive';
        if ($this->start_at && $this->start_at->isFuture()) return 'Scheduled';
        if ($this->end_at && $this->end_at->isPast()) return 'Expired';
        if ($this->count > 0 && $this->use_count >= $this->count) return 'Fully Used';
        return 'Active';
    }
}
