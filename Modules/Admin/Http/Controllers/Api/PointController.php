<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Services\PointService;
use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shop\Entities\PointTransaction;
use Modules\Shop\Entities\Setting;
use Modules\Shop\Http\Resources\PointTransactionResource;
use Modules\Shop\Repositories\PointTransaction\PointTransactionRepositoryInterface;

/**
 * Admin Points API Controller
 */
class PointController extends ApiAdminController
{
    /**
     * @var PointService
     */
    private PointService $pointService;

    /**
     * PointController constructor.
     *
     * @param PointTransactionRepositoryInterface $repository
     * @param PointService $pointService
     */
    public function __construct(
        PointTransactionRepositoryInterface $repository,
        PointService $pointService
    ) {
        parent::__construct($repository);
        $this->pointService = $pointService;
    }

    /**
     * Get points transactions datatable
     *
     * GET /api/admin/points/datatable
     *
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id', 'user_id', 'order_id', 'type', 'source')
            ->with(['user:id,name,email', 'order:id,status,total'])
            ->resource(PointTransactionResource::class)
            ->json();
    }

    /**
     * Get transaction details
     *
     * GET /api/admin/points/{id}
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $model = $this->repository->findOrFail($id, ['user', 'order']);
        return $this->success(new PointTransactionResource($model));
    }

    /**
     * Get user's point history
     *
     * GET /api/admin/points/user/{userId}
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function userHistory(int $userId): JsonResponse
    {
        $summary = $this->pointService->getUserSummary($userId);
        $transactions = $this->repository->getByUserId($userId, request()->get('limit', 20));

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
     * Manual points adjustment
     *
     * POST /api/admin/points/adjust
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'points' => 'required|integer',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $transaction = $this->pointService->adjustPoints(
                $data['user_id'],
                $data['points'],
                $data['description'] ?? null
            );

            return $this->success([
                'transaction' => new PointTransactionResource($transaction),
                'new_balance' => $this->pointService->getAvailableBalance($data['user_id']),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get points settings
     *
     * GET /api/admin/points/settings
     *
     * @return JsonResponse
     */
    public function getSettings(): JsonResponse
    {
        $settings = $this->pointService->getSettings();

        return $this->success($settings);
    }

    /**
     * Update points settings
     *
     * PUT /api/admin/points/settings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'earning_percentage' => 'nullable|numeric|min:0|max:100',
            'expiry_days' => 'nullable|integer|min:1',
            'exchange_rate' => 'nullable|numeric|min:0.01',
            'min_order_total' => 'nullable|numeric|min:0',
            'max_per_order' => 'nullable|integer|min:1',
            'enabled' => 'nullable|boolean',
        ]);

        // Update only provided settings
        foreach ($data as $key => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(
                    ['key' => "points_{$key}"],
                    [
                        'value' => json_encode($value),
                        'name' => ucwords(str_replace('_', ' ', $key)),
                        'field' => is_bool($value) ? 'boolean' : 'number',
                        'active' => true,
                    ]
                );
            }
        }

        // Clear cache
        $this->pointService->clearSettingsCache();

        return $this->success([
            'message' => 'Settings updated successfully',
            'settings' => $this->pointService->getSettings(),
        ]);
    }

    /**
     * Get points statistics
     *
     * GET /api/admin/points/stats
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $today = now()->format('Y-m-d');

        $totalEarned = PointTransaction::where('type', 'earn')->sum('points');
        $totalSpent = abs(PointTransaction::where('type', 'spend')->sum('points'));
        $totalExpired = abs(PointTransaction::where('type', 'expire')->sum('points'));

        $earnedToday = PointTransaction::where('type', 'earn')
            ->whereDate('created_at', $today)
            ->sum('points');

        $spentToday = abs(PointTransaction::where('type', 'spend')
            ->whereDate('created_at', $today)
            ->sum('points'));

        $activeUsers = PointTransaction::distinct('user_id')->count('user_id');

        return $this->success([
            'total_earned' => $totalEarned,
            'total_spent' => $totalSpent,
            'total_expired' => $totalExpired,
            'earned_today' => $earnedToday,
            'spent_today' => $spentToday,
            'active_users' => $activeUsers,
            'net_outstanding' => $totalEarned - $totalSpent - $totalExpired,
        ]);
    }

    /**
     * @return array
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'user_id', 'order_id', 'type', 'source'];
    }
}
