<?php

namespace App\Services;

use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\OrderHistory;


class PrintTrackingService
{
    public static function trackPrint($orderId, $printType = 'normal', $userId = null)
    {
        $order = Order::find($orderId);

        if (!$order) {
            return false;
        }

        // Avoid duplicate tracking within a short period
        if ($order->wasRecentlyPrinted(2)) {
            return true;
        }

        return $order->recordPrintAction($printType, $userId);
    }

    public static function trackPrintDialog($orderId, $userId = null)
    {
        $order = Order::find($orderId);

        if (!$order) {
            return false;
        }

        return OrderHistory::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'action' => 'print_dialog_opened',
            'field' => 'print_dialog',
            'old_value' => null,
            'new_value' => 'opened',
            'notes' => 'Print dialog was opened',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
