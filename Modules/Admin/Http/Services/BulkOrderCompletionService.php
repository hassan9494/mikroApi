<?php

namespace Modules\Admin\Http\Services;


use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Shop\Entities\BulkOrderCompletion;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\PaymentMethod;
use Modules\Shop\Entities\Transaction;
use function PHPUnit\Framework\isNan;

class BulkOrderCompletionService
{
    /**
     * Calculate commission based on payment method
     */
    private function calculateCommission(PaymentMethod $paymentMethod, $amount)
    {
        if (!$paymentMethod || !$amount) return 0;

        $numericAmount = abs((float) $amount);
        if (is_nan($numericAmount)) return 0;

        switch ($paymentMethod->commission_type) {
            case 'Fixed':
                return (float) $paymentMethod->commission;

            case 'Percent':
                $percent = (float) $paymentMethod->commission;
                return ($numericAmount * $percent) / 100;

            case 'Range':
                if (!$paymentMethod->commission_range) return 0;

                try {
                    // Parse commission_range if it's a string
                    $ranges = is_string($paymentMethod->commission_range)
                        ? json_decode($paymentMethod->commission_range, true)
                        : $paymentMethod->commission_range;

                    if (!is_array($ranges)) return 0;

                    // Find matching range
                    foreach ($ranges as $range) {
                        $from = (float) ($range['from'] ?? 0);
                        $to = (float) ($range['to'] ?? INF);
                        if ($numericAmount >= $from && $numericAmount <= $to) {
                            return (float) ($range['commission'] ?? 0);
                        }
                    }
                    return 0;
                } catch (\Exception $e) {
                    Log::error('Error parsing commission_range:', [
                        'payment_method_id' => $paymentMethod->id,
                        'error' => $e->getMessage()
                    ]);
                    return 0;
                }

            default:
                return 0;
        }
    }

    /**
     * Calculate order details for bulk completion
     */
    public function calculateOrderDetails(Order $order, PaymentMethod $paymentMethod = null)
    {
        // Calculate existing payments
        $existingPayments = $order->transactions->sum(function($transaction) {
            if ($transaction->type === 'deposit') {
                return (float) $transaction->amount;
            } else if ($transaction->type === 'refund') {
                return -(float) $transaction->amount;
            }
            return 0;
        });

        // Get shipping data from order
        $shipping = $order->shipping;
        $isShippingFree = $shipping && ($shipping->free ?? false);
        $shippingCost = (float) ($shipping->cost ?? 0);

        // Calculate remaining balance (EXACTLY like OrderStatus component)
        // If shipping is free (shop pays), we DON'T subtract shipping cost from order total
        // If shipping is NOT free (customer paid), we DO subtract shipping cost from order total
        $orderTotal = (float) $order->total;
        $shippingCostToSubtract = $isShippingFree ? 0 : $shippingCost;
        $remainingBalance = $orderTotal - $existingPayments - $shippingCostToSubtract;

        // The shipping amount to show in the UI (for completion)
        // If shipping is free, show the shipping cost (shop pays it)
        // If shipping is NOT free, show 0 (customer already paid it in order total)
        $uiShippingAmount = $isShippingFree ? $shippingCost : 0;

        // Calculate commission if payment method provided
        $commission = $paymentMethod
            ? $this->calculateCommission($paymentMethod, $remainingBalance)
            : 0;

        return [
            'order' => $order,
            'remaining_balance' => max(0, $remainingBalance),
            'calculated_shipping' => $uiShippingAmount, // This is what shows in UI for completion
            'calculated_commission' => $commission,
            'max_amount' => max(0, $remainingBalance),
            'existing_payments' => $existingPayments,
            'order_total' => $orderTotal,
            'shipping_free' => $isShippingFree,
            'actual_shipping_cost' => $shippingCost, // The actual shipping cost
            'shipping_cost_subtracted' => $shippingCostToSubtract, // What was subtracted from total
        ];
    }

    /**
     * Validate orders for bulk completion
     */
    public function validateOrders(array $orderIds)
    {
        $errors = [];
        $orders = Order::with(['products', 'transactions'])
            ->whereIn('id', $orderIds)
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->get();

        // Check if all orders exist
        $foundIds = $orders->pluck('id')->toArray();
        $missingIds = array_diff($orderIds, $foundIds);

        if (!empty($missingIds)) {
            $errors[] = 'Orders not found: ' . implode(', ', $missingIds);
        }

        // Check for already completed orders
        $completedOrders = $orders->where('status', 'COMPLETED');
        if ($completedOrders->isNotEmpty()) {
            $errors[] = 'Already completed orders: ' . $completedOrders->pluck('id')->implode(', ');
        }

        // Check stock for PENDING orders
        foreach ($orders as $order) {
            if ($order->status === 'PENDING') {
                foreach ($order->products as $product) {
                    if ($product->stock < $product->pivot->quantity) {
                        $errors[] = "Order #{$order->id}: Product '{$product->name}' has insufficient stock (Available: {$product->stock}, Required: {$product->pivot->quantity})";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'orders' => $orders,
        ];
    }

    /**
     * Process bulk order completion
     */
    public function processBulkCompletion(array $data, User $user)
    {
        DB::beginTransaction();

        try {
            // Validate input data
            $validation = $this->validateOrders($data['order_ids']);
            if (!$validation['valid']) {
                throw new \Exception(implode('; ', $validation['errors']));
            }

            $orders = $validation['orders'];
            $paymentMethod = PaymentMethod::findOrFail($data['payment_method_id']);

            // Create bulk completion record
            $bulkCompletion = BulkOrderCompletion::create([
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id,
                'total_calculated_amount' => $data['totals']['calculated_amount'] ?? 0,
                'total_entered_amount' => $data['totals']['entered_amount'] ?? 0,
                'total_calculated_shipping' => $data['totals']['calculated_shipping'] ?? 0,
                'total_entered_shipping' => $data['totals']['entered_shipping'] ?? 0,
                'total_calculated_commission' => $data['totals']['calculated_commission'] ?? 0,
                'total_entered_commission' => $data['totals']['entered_commission'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $orderDetails = [];

            // Process each order
            foreach ($orders as $order) {
                $orderData = $data['orders_data'][$order->id] ?? [];

                // Validate entered amount doesn't exceed remaining balance
                $orderDetails = $this->calculateOrderDetails($order, $paymentMethod);
                $maxAmount = $orderDetails['max_amount'];
                $enteredAmount = (float) ($orderData['entered_amount'] ?? 0);

                if ($enteredAmount > $maxAmount) {
                    throw new \Exception("Order #{$order->id}: Entered amount ({$enteredAmount}) exceeds remaining balance ({$maxAmount})");
                }

                // Update order status
                $oldStatus = $order->status;
                $order->update([
                    'status' => 'COMPLETED',
                    'shipping->status' => 'DELIVERED',
                    'completed_at' => now(),
                    'completed_by' => $user->id,
                ]);

                // Record status change in history
                if ($oldStatus !== 'COMPLETED') {
                    $order->recordStatusChange($oldStatus, 'COMPLETED');
                }

                // Create transaction record
                if ($enteredAmount != 0) {
                    $transactionType = $enteredAmount > 0 ? 'deposit' : 'refund';
                    $absoluteAmount = abs($enteredAmount);

                    $transaction = $order->transactions()->create([
                        'transaction_id' => Str::uuid(),
                        'transactionable_id' => $order->id,
                        'transactionable_type' => Order::class,
                        'order_id' => $order->id,
                        'note' => 'Bulk completion',
                        'type' => $transactionType,
                        'amount' => $absoluteAmount,
                        'commission' => (float) ($orderData['entered_commission'] ?? 0),
                        'shipping' => (float) ($orderData['entered_shipping'] ?? 0),
                        'total_amount' => $absoluteAmount -
                            ((float) ($orderData['entered_shipping'] ?? 0)) -
                            ((float) ($orderData['entered_commission'] ?? 0)),
                        'payment_method_id' => $paymentMethod->id,
                        'created_by' => $user->id,
                    ]);
                }

                // Update stock if order was PENDING
                if ($oldStatus === 'PENDING') {
                    foreach ($order->products as $product) {
                        $product->updateStock($product->pivot->quantity, true);
                    }
                }

                // Attach order to bulk completion with amounts
                $bulkCompletion->orders()->attach($order->id, [
                    'calculated_amount' => $orderDetails['remaining_balance'],
                    'entered_amount' => $enteredAmount,
                    'calculated_shipping' => $orderDetails['calculated_shipping'],
                    'entered_shipping' => (float) ($orderData['entered_shipping'] ?? 0),
                    'calculated_commission' => $orderDetails['calculated_commission'],
                    'entered_commission' => (float) ($orderData['entered_commission'] ?? 0),
                ]);
            }

            DB::commit();
            return $bulkCompletion;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk order completion failed:', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }
}
