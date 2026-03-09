<?php

namespace Modules\Shop\Http\Controllers\Api;

use App\Services\PointService;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Shop\Http\Resources\PointTransactionResource;

/**
 * User-facing Points API Controller
 */
class PointController extends Controller
{
    use ApiResponser;

    /**
     * @var PointService
     */
    private PointService $pointService;

    /**
     * PointController constructor.
     *
     * @param PointService $pointService
     */
    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    /**
     * Get current user's points summary and transaction history grouped by order
     *
     * GET /api/user/points
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $summary = $this->pointService->getUserSummary($user->id);

        // Get pagination parameters
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        // Get transactions grouped by order with server-side pagination
        $groupedTransactions = $this->getOrderGroupedTransactions($user->id, $perPage, $page);

        return $this->success([
            'summary' => $summary,
            'transactions' => $groupedTransactions['data'],
            'pagination' => [
                'current_page' => $groupedTransactions['current_page'],
                'last_page' => $groupedTransactions['last_page'],
                'per_page' => $groupedTransactions['per_page'],
                'total' => $groupedTransactions['total'],
                'from' => $groupedTransactions['from'],
                'to' => $groupedTransactions['to'],
            ],
        ]);
    }

    /**
     * Get transactions grouped by order_id with pagination
     *
     * @param int $userId
     * @param int $perPage
     * @param int $page
     * @return array
     */
    private function getOrderGroupedTransactions(int $userId, int $perPage = 10, int $page = 1): array
    {
        // Get all transactions for user (order ASC so EARN is processed before ADJUST)
        $transactions = \Modules\Shop\Entities\PointTransaction::forUser($userId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Group transactions by order_id
        $orderMap = [];

        foreach ($transactions as $tx) {
            $orderId = $tx->order_id ?? 'other';

            if (!isset($orderMap[$orderId])) {
                $orderMap[$orderId] = [
                    'order_id' => $orderId === 'other' ? null : $orderId,
                    'earned' => 0,
                    'spent' => 0,
                    'refund' => 0,
                    'date' => $tx->created_at->toISOString(),
                ];
            }

            switch ($tx->type) {
                case 'earn':
                    $orderMap[$orderId]['earned'] += abs($tx->points);
                    break;
                case 'spend':
                    $orderMap[$orderId]['spent'] += abs($tx->points);
                    break;
                case 'refund':
                    $orderMap[$orderId]['refund'] += abs($tx->points);
                    break;
                case 'adjust':
                    // Negative adjust = reversal of earned points
                    if ($tx->points < 0) {
                        $orderMap[$orderId]['earned'] = max(0, $orderMap[$orderId]['earned'] - abs($tx->points));
                    }
                    break;
            }

            // Keep the most recent date
            if ($tx->created_at->toISOString() > $orderMap[$orderId]['date']) {
                $orderMap[$orderId]['date'] = $tx->created_at->toISOString();
            }
        }

        // Convert to array and sort by date descending
        $groupedData = array_values($orderMap);
        usort($groupedData, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Apply pagination
        $total = count($groupedData);
        $lastPage = (int) ceil($total / $perPage);
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($groupedData, $offset, $perPage);

        return [
            'data' => $paginatedData,
            'current_page' => $page,
            'last_page' => max(1, $lastPage),
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Get points balance only
     *
     * GET /api/user/points/balance
     *
     * @return JsonResponse
     */
    public function balance(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'balance' => $this->pointService->getAvailableBalance($user->id),
            'exchange_rate' => $this->pointService->getSettings()['exchange_rate'],
        ]);
    }

    /**
     * Calculate points usage preview for checkout
     *
     * POST /api/checkout/calculate-points
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateForCheckout(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $data = $request->validate([
            'order_total' => 'required|numeric|min:0',
            'points_to_use' => 'nullable|integer|min:0',
            'user_balance' => 'nullable|integer|min:0',
        ]);

        $orderTotal = (float) $data['order_total'];
        $userBalance = $this->pointService->getAvailableBalance($user->id);
        $maxUsable = $this->pointService->calculateMaxUsablePoints($orderTotal, $userBalance);

        $pointsToUse = min($data['points_to_use'] ?? 0, $maxUsable);
        $discount = $this->pointService->calculateDiscount($pointsToUse);

        $settings = $this->pointService->getSettings();

        return $this->success([
            'success' => true,
            'available_balance' => $userBalance,
            'max_usable_points' => $maxUsable,
            'points_to_use' => $pointsToUse,
            'discount_amount' => $discount,
            'order_total_after_discount' => $orderTotal - $discount,
            'exchange_rate' => $settings['exchange_rate'],
            'settings' => [
                'exchange_rate' => $settings['exchange_rate'],
                'min_order_total' => $settings['min_order_total'],
                'max_per_order' => $settings['max_per_order'],
            ],
        ]);
    }

    /**
     * Get points settings (public info for UI)
     *
     * GET /api/points/settings
     *
     * @return JsonResponse
     */
    public function settings(): JsonResponse
    {
        $settings = $this->pointService->getSettings();

        return $this->success([
            'enabled' => $settings['enabled'],
            'exchange_rate' => $settings['exchange_rate'],
            'earning_percentage' => $settings['earning_percentage'],
        ]);
    }
}
