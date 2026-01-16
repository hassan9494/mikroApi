<?php

namespace Modules\Shop\Traits\Product;

use Illuminate\Support\Facades\DB;

trait Stock {

    /**
     * @param int $quantity
     * @return bool
     */
    public function checkStock(int $quantity = 1): bool
    {
        if ($this->options->kit)
        {
            // First check if kit itself has enough stock
            $kitTotalStock = ($this->store_available ?? 0) + ($this->stock_available ?? 0);
            if ($kitTotalStock < $quantity) {
                return false;
            }

            // Then check all kit components
            foreach ($this->kit()->get() as $item){
                $totalQty = $item->pivot->quantity * $quantity;
                if (!$item->checkStock($totalQty)) return false;
            }
            return true;
        }

        // Check total stock (sum of store_available and stock_available)
        $totalStock = ($this->store_available ?? 0) + ($this->stock_available ?? 0);
        return $totalStock >= $quantity;
    }


    /**
     * @param int $quantity
     * @param bool $reduce
     */
    public function updateStock(int $quantity = 1, bool $reduce = false)
    {
        if ($reduce && !$this->checkStock($quantity))
        {
            abort(400, $this->name . ' has insufficient quantity');
        }

        // Use transaction to ensure data consistency
        DB::transaction(function () use ($quantity, $reduce) {
            if ($this->options->kit)
            {
                // Update kit's own stock with distribution
                $this->updateStockWithDistribution($quantity, $reduce);

                // Handle kit components - ALSO use distribution for each component
                foreach ($this->kit()->get() as $item){
                    $totalQty = $item->pivot->quantity * $quantity;
                    $item->updateStock($totalQty, $reduce);
                }
            } else {
                $this->updateStockWithDistribution($quantity, $reduce);
            }
        });
    }

    /**
     * Update stock with distribution between store_available and stock_available
     */
    private function updateStockWithDistribution(int $quantity = 1, bool $reduce = false)
    {
        // Ensure defaults
        $storeAvailable = $this->store_available ?? 0;
        $stockAvailable = $this->stock_available ?? 0;
        $totalStock = $storeAvailable + $stockAvailable;

        if ($reduce) {
            // Reducing stock (order processing/completed)
            if ($storeAvailable >= $quantity) {
                // Take all from store_available
                $this->store_available = $storeAvailable - $quantity;
                $this->stock_available = $stockAvailable; // No change
            } else {
                // Take what we can from store_available, rest from stock_available
                $remaining = $quantity - $storeAvailable;
                $this->store_available = 0;
                $this->stock_available = $stockAvailable - $remaining;
            }

            // Update total stock
            $this->stock = ($this->store_available + $this->stock_available);
        } else {
            // Increasing stock (order cancelled/returned)
            // Default: add to store_available
            $this->store_available = $storeAvailable + $quantity;
            $this->stock_available = $stockAvailable; // No change

            // Update total stock
            $this->stock = $this->store_available + $this->stock_available;
        }

        $this->save();
    }
}
