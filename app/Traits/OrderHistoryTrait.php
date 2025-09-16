<?php

namespace App\Traits;



use Modules\Shop\Entities\OrderHistory;

trait OrderHistoryTrait
{
    public function recordHistory($action, $details = null, $notes = null)
    {
        try {
            return OrderHistory::create([
                'order_id' => $this->id,
                'user_id' => auth()->check() ? auth()->id() : null,
                'action' => $action,
                'details' => $details,
                'notes' => $notes
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the application
            \Log::error('Failed to record order history: ' . $e->getMessage());
            return null;
        }
    }
}
