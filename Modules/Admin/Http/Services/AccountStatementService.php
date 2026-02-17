<?php

namespace Modules\Admin\Http\Services;

use Modules\Shop\Entities\AccountStatement;
use Modules\Shop\Entities\AccountStatementDetail;
use Modules\Shop\Entities\Order;

class AccountStatementService
{
    /**
     * Validate that orders are eligible (exist and have required data).
     */
    public function validateOrders(array $orderIds): array
    {
        $orders = Order::whereIn('id', $orderIds)->get();
        $missing = array_diff($orderIds, $orders->pluck('id')->toArray());

        if (!empty($missing)) {
            return [
                'valid' => false,
                'errors' => ['Orders not found: ' . implode(', ', $missing)]
            ];
        }

        return [
            'valid' => true,
            'orders' => $orders
        ];
    }

    /**
     * Calculate debtor, creditor, and running balance for each order.
     */
    public function calculateDetails($orders, $fromDate = null, $toDate = null): array
    {
        $details = [];
        $runningBalance = 0;
        $totalDebtor = 0;
        $totalCreditor = 0;

        // Sort orders by date (oldest first) to calculate running balance correctly
        $sorted = $orders->sortBy('created_at');

        foreach ($sorted as $order) {
            $debtor = $order->total ?? 0;
            $paid = $order->transactions()->where('type','deposit')->sum('amount') ?? 0; // or use remaining_balance
            $refund = $order->transactions()->where('type','refund')->sum('amount') ?? 0; // or use remaining_balance
            $creditor = $paid - $refund; // amount paid
            $runningBalance += ($debtor - $creditor);

            $details[] = [
                'order'              => $order,
                'transaction_number' => $order->number,
                'description'        => $order->customer->name ?? 'N/A',
                'transaction_type'   => 'invoice', // or determine from order type
                'debtor'             => $debtor,
                'creditor'           => $creditor,
                'balance'            => $runningBalance,
                'transaction_date'   => $order->created_at,
            ];

            $totalDebtor += $debtor;
            $totalCreditor += $creditor;
        }

        return [
            'details'       => $details,
            'total_debtor'  => $totalDebtor,
            'total_creditor' => $totalCreditor,
            'balance'       => $runningBalance,
        ];
    }

    /**
     * Create the account statement with its details.
     */
    public function createStatement(array $data, $user)
    {
        $statement = AccountStatement::create([
            'user_id'        => $user->id,
            'account_number' => $data['account_number'] ?? null,
            'account_name'   => $data['account_name'] ?? null,
            'address'        => $data['address'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'from_date'      => $data['from_date'] ?? null,
            'to_date'        => $data['to_date'] ?? null,
            'total_debtor'   => $data['total_debtor'],
            'total_creditor' => $data['total_creditor'],
            'notes'          => $data['notes'] ?? null,
        ]);

        foreach ($data['details'] as $detail) {
            AccountStatementDetail::create([
                'account_statement_id' => $statement->id,
                'order_id'             => $detail['order']->id,
                'transaction_number'   => $detail['transaction_number'],
                'description'          => $detail['description'],
                'transaction_type'     => $detail['transaction_type'],
                'debtor'               => $detail['debtor'],
                'creditor'             => $detail['creditor'],
                'balance'              => $detail['balance'],
                'transaction_date'     => $detail['transaction_date'],
            ]);
        }

        return $statement->load('details');
    }
}
