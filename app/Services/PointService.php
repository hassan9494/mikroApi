<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\PointTransaction;
use Modules\Shop\Entities\Setting;

class PointService
{
    /**
     * Cache key for points settings
     */
    const SETTINGS_CACHE_KEY = 'points_settings';
    const SETTINGS_CACHE_TTL = 3600; // 1 hour

    /**
     * Get all points settings
     *
     * @return array
     */
    public function getSettings(): array
    {
        return Cache::remember(self::SETTINGS_CACHE_KEY, self::SETTINGS_CACHE_TTL, function () {
            $settings = Setting::whereIn('key', [
                'points_earning_percentage',
                'points_expiry_days',
                'points_exchange_rate',
                'points_min_order_total',
                'points_max_per_order',
                'points_enabled',
            ])->pluck('value', 'key')->toArray();

            return [
                'earning_percentage' => json_decode($settings['points_earning_percentage'] ?? '10'),
                'expiry_days' => json_decode($settings['points_expiry_days'] ?? '365'),
                'exchange_rate' => json_decode($settings['points_exchange_rate'] ?? '0.10'),
                'min_order_total' => json_decode($settings['points_min_order_total'] ?? '1.00'),
                'max_per_order' => json_decode($settings['points_max_per_order'] ?? '100'),
                'enabled' => json_decode($settings['points_enabled'] ?? 'true'),
            ];
        });
    }

    /**
     * Check if points system is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->getSettings()['enabled'] ?? false;
    }

    /**
     * Calculate how many points to earn for an order total
     *
     * @param float $orderTotal The order total (after discounts, before shipping)
     * @return int Points to earn (floored)
     */
    public function calculateEarnedPoints(float $orderTotal): int
    {
        if (!$this->isEnabled() || $orderTotal <= 0) {
            return 0;
        }

        $settings = $this->getSettings();
        $percentage = $settings['earning_percentage'];

        // Calculate points: floor(total * percentage / 100)
        return (int) floor($orderTotal * $percentage / 100);
    }

    /**
     * Calculate the discount value for given points
     *
     * @param int $points Number of points to redeem
     * @return float Discount amount
     */
    public function calculateDiscount(int $points): float
    {
        $settings = $this->getSettings();
        return round($points * $settings['exchange_rate'], 3);
    }

    /**
     * Calculate how many points are needed for a given discount amount
     *
     * @param float $discountAmount Desired discount
     * @return int Points needed
     */
    public function calculatePointsNeeded(float $discountAmount): int
    {
        $settings = $this->getSettings();
        return (int) ceil($discountAmount / $settings['exchange_rate']);
    }

    /**
     * Get user's available (non-expired) points balance
     *
     * @param int $userId
     * @return int
     */
    public function getAvailableBalance(int $userId): int
    {
        // Get sum of earned points that haven't expired
        $earned = PointTransaction::forUser($userId)
            ->where('type', PointTransaction::TYPE_EARN)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('points');

        // Get sum of spent/expired points (these are negative values)
        $used = PointTransaction::forUser($userId)
            ->whereIn('type', [
                PointTransaction::TYPE_SPEND,
                PointTransaction::TYPE_EXPIRE,
            ])
            ->sum('points');

        // Refunded points add back to balance (positive values)
        $refunded = PointTransaction::forUser($userId)
            ->where('type', PointTransaction::TYPE_REFUND)
            ->sum('points');

        // Adjusted points can be positive or negative
        // Negative adjust = reversal of earned points (reduces balance)
        // Positive adjust = admin adjustment (increases balance)
        $adjusted = PointTransaction::forUser($userId)
            ->where('type', PointTransaction::TYPE_ADJUST)
            ->sum('points');

        return max(0, $earned - abs($used) + $refunded + $adjusted);
    }

    /**
     * Get user's total earned points (all time)
     *
     * @param int $userId
     * @return int
     */
    public function getTotalEarned(int $userId): int
    {
        // Get total earned
        $earned = (int) PointTransaction::forUser($userId)
            ->where('type', PointTransaction::TYPE_EARN)
            ->sum('points');

        // Subtract negative adjustments (reversals of earned points)
        $negativeAdjustments = (int) abs(PointTransaction::forUser($userId)
            ->where('type', PointTransaction::TYPE_ADJUST)
            ->where('points', '<', 0)
            ->sum('points'));

        return max(0, $earned - $negativeAdjustments);
    }

    /**
     * Get user's total spent points (all time)
     *
     * @param int $userId
     * @return int
     */
    public function getTotalSpent(int $userId): int
    {
        return (int) abs(PointTransaction::forUser($userId)
            ->where('type', PointTransaction::TYPE_SPEND)
            ->sum('points'));
    }

    /**
     * Calculate maximum points that can be used on an order
     *
     * @param float $orderTotal Order total before points discount
     * @param int $userBalance User's available points
     * @return int Maximum usable points
     */
    public function calculateMaxUsablePoints(float $orderTotal, int $userBalance, float $existingDiscount = 0): int
    {
        if (!$this->isEnabled() || $orderTotal <= 0 || $userBalance <= 0) {
            return 0;
        }

        $settings = $this->getSettings();

        // Calculate max discount allowed (order total - existing discount - minimum order total)
        $maxDiscount = $orderTotal - $existingDiscount - $settings['min_order_total'];
        if ($maxDiscount <= 0) {
            return 0;
        }

        // Calculate how many points that equals
        $pointsForMaxDiscount = $this->calculatePointsNeeded($maxDiscount);

        // Apply constraints
        $maxPoints = min(
            $userBalance,                     // User's available points
            $settings['max_per_order'],       // Max points per order setting
            $pointsForMaxDiscount             // Max based on minimum order total
        );

        return max(0, $maxPoints);
    }

    /**
     * Award points to a user
     *
     * @param int $userId
     * @param int $points
     * @param int|null $orderId
     * @param string $source
     * @param string|null $description
     * @return PointTransaction
     */
    public function awardPoints(
        int $userId,
        int $points,
        ?int $orderId = null,
        string $source = PointTransaction::SOURCE_ORDER,
        ?string $description = null
    ): PointTransaction {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Points must be positive');
        }

        $settings = $this->getSettings();
        $expiresAt = now()->addDays($settings['expiry_days']);

        return DB::transaction(function () use ($userId, $points, $orderId, $source, $description, $expiresAt) {
            // Get current balance
            $currentBalance = $this->getAvailableBalance($userId);
            $newBalance = $currentBalance + $points;

            // Create transaction
            $transaction = PointTransaction::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => PointTransaction::TYPE_EARN,
                'points' => $points,
                'balance_after' => $newBalance,
                'expires_at' => $expiresAt,
                'source' => $source,
                'description' => $description ?? "Earned {$points} points",
            ]);

            // Update user's cached balance
            $this->updateUserBalance($userId, $newBalance);

            return $transaction;
        });
    }

    /**
     * Use (spend) points from a user
     *
     * @param int $userId
     * @param int $points
     * @param int|null $orderId
     * @param string|null $description
     * @return PointTransaction
     */
    public function usePoints(
        int $userId,
        int $points,
        ?int $orderId = null,
        ?string $description = null
    ): PointTransaction {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Points must be positive');
        }

        $currentBalance = $this->getAvailableBalance($userId);
        if ($currentBalance < $points) {
            throw new \Exception('Insufficient points balance');
        }

        return DB::transaction(function () use ($userId, $points, $orderId, $description, $currentBalance) {
            $newBalance = $currentBalance - $points;

            // Create transaction (negative points for spending)
            $transaction = PointTransaction::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => PointTransaction::TYPE_SPEND,
                'points' => -$points,
                'balance_after' => $newBalance,
                'expires_at' => null,
                'source' => PointTransaction::SOURCE_ORDER,
                'description' => $description ?? "Spent {$points} points",
            ]);

            // Update user's cached balance
            $this->updateUserBalance($userId, $newBalance);

            return $transaction;
        });
    }

    /**
     * Refund spent points for an order (return points_used to user)
     *
     * @param int $orderId
     * @return PointTransaction|null
     */
    public function refundSpentPointsForOrder(int $orderId): ?PointTransaction
    {
        // Find the spend transaction for this order
        $spendTransaction = PointTransaction::where('order_id', $orderId)
            ->where('type', PointTransaction::TYPE_SPEND)
            ->first();

        if (!$spendTransaction) {
            return null;
        }

        $pointsToRefund = abs($spendTransaction->points);
        $userId = $spendTransaction->user_id;

        return $this->refundPoints($userId, $pointsToRefund, $orderId, "Refunded spent points for order #{$orderId}");
    }

    /**
     * Reverse earned points for an order (remove points_earned from user)
     * Used when order status changes from COMPLETED back to another status
     *
     * @param int $orderId
     * @return PointTransaction|null
     */
    public function reverseEarnedPointsForOrder(int $orderId): ?PointTransaction
    {
        // Find the earn transaction for this order
        $earnTransaction = PointTransaction::where('order_id', $orderId)
            ->where('type', PointTransaction::TYPE_EARN)
            ->first();

        if (!$earnTransaction) {
            return null;
        }

        $pointsToReverse = $earnTransaction->points;
        $userId = $earnTransaction->user_id;
        $currentBalance = $this->getAvailableBalance($userId);

        // Only reverse if user has enough balance
        $pointsToDeduct = min($pointsToReverse, $currentBalance);

        if ($pointsToDeduct <= 0) {
            return null;
        }

        return DB::transaction(function () use ($userId, $pointsToDeduct, $orderId, $currentBalance) {
            $newBalance = $currentBalance - $pointsToDeduct;

            // Create a negative adjustment to reverse the earned points
            $transaction = PointTransaction::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => PointTransaction::TYPE_ADJUST,
                'points' => -$pointsToDeduct,
                'balance_after' => $newBalance,
                'expires_at' => null,
                'source' => PointTransaction::SOURCE_ORDER,
                'description' => "Reversed earned points for order #{$orderId} (order status changed)",
            ]);

            // Update user's cached balance
            $this->updateUserBalance($userId, $newBalance);

            return $transaction;
        });
    }

    /**
     * Refund points for a cancelled/returned order (auto-detect from order)
     * @deprecated Use refundSpentPointsForOrder instead
     *
     * @param int $orderId
     * @return PointTransaction|null
     */
    public function refundPointsForOrder(int $orderId): ?PointTransaction
    {
        return $this->refundSpentPointsForOrder($orderId);
    }

    /**
     * Refund points to a user
     *
     * @param int $userId
     * @param int $points
     * @param int|null $orderId
     * @param string|null $description
     * @return PointTransaction
     */
    public function refundPoints(
        int $userId,
        int $points,
        ?int $orderId = null,
        ?string $description = null
    ): PointTransaction {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Points must be positive');
        }

        $currentBalance = $this->getAvailableBalance($userId);

        return DB::transaction(function () use ($userId, $points, $orderId, $description, $currentBalance) {
            $newBalance = $currentBalance + $points;

            // Create refund transaction
            $transaction = PointTransaction::create([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => PointTransaction::TYPE_REFUND,
                'points' => $points,
                'balance_after' => $newBalance,
                'expires_at' => null,
                'source' => PointTransaction::SOURCE_ORDER,
                'description' => $description ?? "Refunded {$points} points" . ($orderId ? " for order #{$orderId}" : ""),
            ]);

            // Update user's cached balance
            $this->updateUserBalance($userId, $newBalance);

            return $transaction;
        });
    }

    /**
     * Manual adjustment of points (admin action)
     *
     * @param int $userId
     * @param int $points (positive to add, negative to subtract)
     * @param string|null $description
     * @return PointTransaction
     */
    public function adjustPoints(
        int $userId,
        int $points,
        ?string $description = null
    ): PointTransaction {
        $currentBalance = $this->getAvailableBalance($userId);
        $newBalance = $currentBalance + $points;

        if ($newBalance < 0) {
            throw new \Exception('Adjustment would result in negative balance');
        }

        $settings = $this->getSettings();

        return DB::transaction(function () use ($userId, $points, $description, $newBalance, $settings) {
            $transaction = PointTransaction::create([
                'user_id' => $userId,
                'order_id' => null,
                'type' => PointTransaction::TYPE_ADJUST,
                'points' => $points,
                'balance_after' => $newBalance,
                'expires_at' => $points > 0 ? now()->addDays($settings['expiry_days']) : null,
                'source' => PointTransaction::SOURCE_ADMIN,
                'description' => $description ?? "Manual adjustment of {$points} points",
            ]);

            // Update user's cached balance
            $this->updateUserBalance($userId, $newBalance);

            return $transaction;
        });
    }

    /**
     * Update the cached balance on the users table
     *
     * @param int $userId
     * @param int $balance
     * @return void
     */
    private function updateUserBalance(int $userId, int $balance): void
    {
        User::where('id', $userId)->update(['points_balance' => $balance]);
    }

    /**
     * Get user's transaction history
     *
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTransactionHistory(int $userId, int $limit = 20)
    {
        return PointTransaction::forUser($userId)
            ->with('order:id,status,total')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Clear settings cache
     *
     * @return void
     */
    public function clearSettingsCache(): void
    {
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    /**
     * Update points settings
     *
     * @param array $settings
     * @return void
     */
    public function updateSettings(array $settings): void
    {
        DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => "points_{$key}"],
                    ['value' => json_encode($value)]
                );
            }
        });

        $this->clearSettingsCache();
    }

    /**
     * Get points summary for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserSummary(int $userId): array
    {
        return [
            'available_balance' => $this->getAvailableBalance($userId),
            'total_earned' => $this->getTotalEarned($userId),
            'total_spent' => $this->getTotalSpent($userId),
            'exchange_rate' => $this->getSettings()['exchange_rate'],
        ];
    }
}
