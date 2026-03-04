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
     * Get current user's points summary and transaction history
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
        $transactions = $this->pointService->getTransactionHistory(
            $user->id,
            $request->get('limit', 20)
        );

        return $this->success([
            'summary' => $summary,
            'transactions' => PointTransactionResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
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
