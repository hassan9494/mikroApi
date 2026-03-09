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
     * Get transactions grouped by order for datatable with expandable details
     *
     * GET /api/admin/points/grouped
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function groupedByOrder(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('limit', 10);
        $page = (int) $request->get('page', 0) + 1; // Frontend uses 0-based index
        $search = $request->get('search', '');

        // Get all transactions with relationships
        $query = PointTransaction::with(['user:id,name,email', 'order:id,status,total'])
            ->orderBy('created_at', 'asc');

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        $transactions = $query->get();

        // Group transactions by order_id
        $orderMap = [];

        foreach ($transactions as $tx) {
            $orderId = $tx->order_id ?? 'other';

            if (!isset($orderMap[$orderId])) {
                $orderMap[$orderId] = [
                    'order_id' => $orderId === 'other' ? null : $orderId,
                    'user' => $tx->user ? [
                        'id' => $tx->user->id,
                        'name' => $tx->user->name,
                        'email' => $tx->user->email,
                    ] : null,
                    'user_id' => $tx->user_id,
                    'earned' => 0,
                    'spent' => 0,
                    'refund' => 0,
                    'adjusted' => 0,
                    'net' => 0,
                    'date' => $tx->created_at->toISOString(),
                    'transactions' => [],
                ];
            }

            // Add transaction to the list
            $orderMap[$orderId]['transactions'][] = [
                'id' => $tx->id,
                'type' => $tx->type,
                'points' => $tx->points,
                'balance_after' => $tx->balance_after,
                'source' => $tx->source,
                'description' => $tx->description,
                'expires_at' => $tx->expires_at?->toISOString(),
                'created_at' => $tx->created_at->toISOString(),
            ];

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
                    $orderMap[$orderId]['adjusted'] += $tx->points;
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

        // Calculate net for each order and sort transactions by date desc
        foreach ($orderMap as &$order) {
            $order['net'] = $order['earned'] - $order['spent'] + $order['refund'];
            // Sort transactions by created_at desc within each order
            usort($order['transactions'], function ($a, $b) {
                return strcmp($b['created_at'], $a['created_at']);
            });
        }

        // Convert to array and sort by date descending
        $groupedData = array_values($orderMap);
        usort($groupedData, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Apply pagination
        $total = count($groupedData);
        $lastPage = (int) ceil($total / $perPage);
        $page = max(1, min($page, max(1, $lastPage)));
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($groupedData, $offset, $perPage);

        return $this->success([
            'items' => $paginatedData,
            'total' => $total,
        ]);
    }

    /**
     * Get all transactions for a specific order or user's non-order transactions
     *
     * GET /api/admin/points/order/{orderId}/{userId?}
     *
     * @param Request $request
     * @param string $orderId - can be 'other' for null order_id transactions
     * @param int|null $userId - required when orderId is 'other'
     * @return JsonResponse
     */
    public function orderTransactions(Request $request, string $orderId, ?int $userId = null): JsonResponse
    {
        $query = PointTransaction::with(['user:id,name,email', 'order:id,status,total'])
            ->orderBy('created_at', 'desc');

        if ($orderId === 'all') {
            // Get ALL transactions for a specific user
            if (!$userId) {
                return $this->error('User ID is required', 400);
            }
            $query->where('user_id', $userId);
        } elseif ($orderId === 'other') {
            // Get transactions without order_id for a specific user
            if (!$userId) {
                return $this->error('User ID is required for other transactions', 400);
            }
            $query->whereNull('order_id')->where('user_id', $userId);
        } else {
            // Get transactions for a specific order
            $query->where('order_id', (int) $orderId);
        }

        $transactions = $query->get();

        return $this->success([
            'transactions' => PointTransactionResource::collection($transactions),
        ]);
    }

    /**
     * @return array
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'user_id', 'order_id', 'type', 'source'];
    }

    /**
     * Get comprehensive points report with various sorting options
     *
     * GET /api/admin/points/report
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function report(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $sortBy = $request->get('sort_by', 'most_points'); // most_points, most_frequent, most_spent, balance
        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);

        // Build base query for date filtering
        $dateQuery = PointTransaction::query();
        if ($startDate) {
            $dateQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $dateQuery->whereDate('created_at', '<=', $endDate);
        }

        // Period summary statistics
        $periodStats = $this->getPeriodStats($startDate, $endDate);

        // Get user rankings based on sort type
        $userRankings = $this->getUserRankings($sortBy, $startDate, $endDate, $perPage, $page);

        // Top earners (top 10 users by earned points)
        $topEarners = $this->getTopUsers('earn', $startDate, $endDate, 10);

        // Top spenders (top 10 users by spent points)
        $topSpenders = $this->getTopUsers('spend', $startDate, $endDate, 10);

        // Points usage over time (daily breakdown for charts)
        $dailyBreakdown = $this->getDailyBreakdown($startDate, $endDate);

        // Type distribution
        $typeDistribution = $this->getTypeDistribution($startDate, $endDate);

        return $this->success([
            'period_stats' => $periodStats,
            'user_rankings' => $userRankings,
            'top_earners' => $topEarners,
            'top_spenders' => $topSpenders,
            'daily_breakdown' => $dailyBreakdown,
            'type_distribution' => $typeDistribution,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'sort_by' => $sortBy,
            ],
        ]);
    }

    /**
     * Get period statistics
     */
    private function getPeriodStats(?string $startDate, ?string $endDate): array
    {
        $query = PointTransaction::query();
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $earned = (clone $query)->where('type', 'earn')->sum('points');
        $spent = abs((clone $query)->where('type', 'spend')->sum('points'));
        $refunded = abs((clone $query)->where('type', 'refund')->sum('points'));
        $expired = abs((clone $query)->where('type', 'expire')->sum('points'));
        $adjusted = (clone $query)->where('type', 'adjust')->sum('points');

        $totalTransactions = (clone $query)->count();
        $uniqueUsers = (clone $query)->distinct('user_id')->count('user_id');
        $ordersWithPoints = (clone $query)->whereNotNull('order_id')->distinct('order_id')->count('order_id');

        // Users with remaining balance (current state, not filtered by date)
        $usersWithBalance = \App\Models\User::where('points_balance', '>', 0)->count();
        $totalOutstandingBalance = \App\Models\User::sum('points_balance');

        return [
            'total_earned' => $earned,
            'total_spent' => $spent,
            'total_refunded' => $refunded,
            'total_expired' => $expired,
            'total_adjusted' => $adjusted,
            'net_points' => $earned - $spent + $refunded + $adjusted - $expired,
            'total_transactions' => $totalTransactions,
            'unique_users' => $uniqueUsers,
            'orders_with_points' => $ordersWithPoints,
            'users_with_balance' => $usersWithBalance,
            'total_outstanding_balance' => $totalOutstandingBalance,
            'avg_points_per_transaction' => $totalTransactions > 0 ? round($earned / max(1, (clone $query)->where('type', 'earn')->count()), 2) : 0,
        ];
    }

    /**
     * Get user rankings with pagination
     */
    private function getUserRankings(string $sortBy, ?string $startDate, ?string $endDate, int $perPage, int $page): array
    {
        $offset = ($page - 1) * $perPage;

        // Base query for point transactions
        $baseQuery = PointTransaction::query();
        if ($startDate) {
            $baseQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('created_at', '<=', $endDate);
        }

        switch ($sortBy) {
            case 'most_frequent':
                // Users sorted by transaction count
                $users = PointTransaction::select('user_id')
                    ->selectRaw('COUNT(*) as transaction_count')
                    ->selectRaw('SUM(CASE WHEN type = "earn" THEN points ELSE 0 END) as total_earned')
                    ->selectRaw('SUM(CASE WHEN type = "spend" THEN ABS(points) ELSE 0 END) as total_spent')
                    ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                    ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                    ->groupBy('user_id')
                    ->orderByDesc('transaction_count')
                    ->with('user:id,name,email,points_balance')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
                break;

            case 'most_spent':
                // Users sorted by points spent
                $users = PointTransaction::select('user_id')
                    ->selectRaw('SUM(CASE WHEN type = "spend" THEN ABS(points) ELSE 0 END) as total_spent')
                    ->selectRaw('SUM(CASE WHEN type = "earn" THEN points ELSE 0 END) as total_earned')
                    ->selectRaw('COUNT(*) as transaction_count')
                    ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                    ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                    ->groupBy('user_id')
                    ->orderByDesc('total_spent')
                    ->with('user:id,name,email,points_balance')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
                break;

            case 'balance':
                // Users sorted by current balance (ignores date filter)
                $users = \App\Models\User::select('id', 'name', 'email', 'points_balance')
                    ->where('points_balance', '>', 0)
                    ->orderByDesc('points_balance')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get()
                    ->map(function ($user) use ($startDate, $endDate) {
                        // Get their transaction stats within the date range
                        $stats = PointTransaction::where('user_id', $user->id)
                            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                            ->selectRaw('SUM(CASE WHEN type = "earn" THEN points ELSE 0 END) as total_earned')
                            ->selectRaw('SUM(CASE WHEN type = "spend" THEN ABS(points) ELSE 0 END) as total_spent')
                            ->selectRaw('COUNT(*) as transaction_count')
                            ->first();

                        return [
                            'user_id' => $user->id,
                            'user' => [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'points_balance' => $user->points_balance,
                            ],
                            'total_earned' => $stats->total_earned ?? 0,
                            'total_spent' => $stats->total_spent ?? 0,
                            'transaction_count' => $stats->transaction_count ?? 0,
                        ];
                    });

                // Get total count for pagination
                $totalCount = \App\Models\User::where('points_balance', '>', 0)->count();

                return [
                    'data' => $users,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $totalCount,
                        'last_page' => (int) ceil($totalCount / $perPage),
                    ],
                ];

            case 'most_points':
            default:
                // Users sorted by total earned points
                $users = PointTransaction::select('user_id')
                    ->selectRaw('SUM(CASE WHEN type = "earn" THEN points ELSE 0 END) as total_earned')
                    ->selectRaw('SUM(CASE WHEN type = "spend" THEN ABS(points) ELSE 0 END) as total_spent')
                    ->selectRaw('COUNT(*) as transaction_count')
                    ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                    ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                    ->groupBy('user_id')
                    ->orderByDesc('total_earned')
                    ->with('user:id,name,email,points_balance')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
                break;
        }

        // Transform results
        $data = $users->map(function ($item) {
            if (isset($item['user_id'])) {
                return $item; // Already transformed (balance case)
            }
            return [
                'user_id' => $item->user_id,
                'user' => $item->user ? [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'email' => $item->user->email,
                    'points_balance' => $item->user->points_balance,
                ] : null,
                'total_earned' => $item->total_earned ?? 0,
                'total_spent' => $item->total_spent ?? 0,
                'transaction_count' => $item->transaction_count ?? 0,
            ];
        });

        // Get total count for pagination
        $totalQuery = PointTransaction::select('user_id')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->groupBy('user_id');
        $totalCount = $totalQuery->get()->count();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => (int) ceil($totalCount / $perPage),
            ],
        ];
    }

    /**
     * Get top users by transaction type
     */
    private function getTopUsers(string $type, ?string $startDate, ?string $endDate, int $limit): array
    {
        return PointTransaction::select('user_id')
            ->selectRaw('SUM(ABS(points)) as total_points')
            ->selectRaw('COUNT(*) as transaction_count')
            ->where('type', $type)
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->limit($limit)
            ->with('user:id,name,email,points_balance')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->user_id,
                    'user' => $item->user ? [
                        'id' => $item->user->id,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                        'points_balance' => $item->user->points_balance,
                    ] : null,
                    'total_points' => $item->total_points,
                    'transaction_count' => $item->transaction_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get daily breakdown for charts
     */
    private function getDailyBreakdown(?string $startDate, ?string $endDate): array
    {
        // Default to last 30 days if no date range specified
        if (!$startDate) {
            $startDate = now()->subDays(30)->format('Y-m-d');
        }
        if (!$endDate) {
            $endDate = now()->format('Y-m-d');
        }

        return PointTransaction::selectRaw('DATE(created_at) as date')
            ->selectRaw('SUM(CASE WHEN type = "earn" THEN points ELSE 0 END) as earned')
            ->selectRaw('SUM(CASE WHEN type = "spend" THEN ABS(points) ELSE 0 END) as spent')
            ->selectRaw('SUM(CASE WHEN type = "refund" THEN ABS(points) ELSE 0 END) as refunded')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Get type distribution
     */
    private function getTypeDistribution(?string $startDate, ?string $endDate): array
    {
        return PointTransaction::selectRaw('type, SUM(ABS(points)) as total_points, COUNT(*) as count')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->groupBy('type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'label' => ucfirst($item->type),
                    'total_points' => $item->total_points,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get transactions for a specific date with details
     *
     * GET /api/admin/points/daily/{date}
     *
     * @param Request $request
     * @param string $date
     * @return JsonResponse
     */
    public function dailyTransactions(Request $request, string $date): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);

        // Get transactions for the specific date
        // Note: 'order' loaded without column restrictions because 'number' is an accessor
        $query = PointTransaction::with(['user:id,name,email,points_balance', 'order:id,status,total'])
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $transactions = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Get summary for the day
        $summary = [
            'date' => $date,
            'earned' => PointTransaction::whereDate('created_at', $date)->where('type', 'earn')->sum('points'),
            'spent' => abs(PointTransaction::whereDate('created_at', $date)->where('type', 'spend')->sum('points')),
            'refunded' => abs(PointTransaction::whereDate('created_at', $date)->where('type', 'refund')->sum('points')),
            'expired' => abs(PointTransaction::whereDate('created_at', $date)->where('type', 'expire')->sum('points')),
            'adjusted' => PointTransaction::whereDate('created_at', $date)->where('type', 'adjust')->sum('points'),
            'total_transactions' => $total,
            'unique_users' => PointTransaction::whereDate('created_at', $date)->distinct('user_id')->count('user_id'),
            'orders_count' => PointTransaction::whereDate('created_at', $date)->whereNotNull('order_id')->distinct('order_id')->count('order_id'),
        ];

        // Calculate net
        $summary['net'] = $summary['earned'] - $summary['spent'] + $summary['refunded'] + $summary['adjusted'] - $summary['expired'];

        // Transform transactions
        $data = $transactions->map(function ($tx) {
            return [
                'id' => $tx->id,
                'user' => $tx->user ? [
                    'id' => $tx->user->id,
                    'name' => $tx->user->name,
                    'email' => $tx->user->email,
                ] : null,
                'order' => $tx->order ? [
                    'id' => $tx->order->id,
                    'number' => $tx->order->number,
                    'status' => $tx->order->status,
                    'total' => $tx->order->total,
                ] : null,
                'type' => $tx->type,
                'points' => $tx->points,
                'balance_after' => $tx->balance_after,
                'source' => $tx->source,
                'description' => $tx->description,
                'created_at' => $tx->created_at->format('H:i:s'),
            ];
        });

        return $this->success([
            'summary' => $summary,
            'transactions' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }
}
