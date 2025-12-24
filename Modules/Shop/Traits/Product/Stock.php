<?php

namespace Modules\Shop\Traits\Product;

trait Stock {

    /**
     * @param int $quantity
     * @return bool
     */
    public function checkStock(int $quantity = 1): bool
    {
        if ($this->options->kit)
        {
            foreach ($this->kit()->get() as $item){
                $totalQty = $item->pivot->quantity * $quantity;
                if (!$item->checkStock($totalQty)) return false;
            }
            return true;
        }
        return $this->stock >= $quantity;
    }


    /**
     * @param int $quantity
     * @param false $reduce
     */
    public function updateStock(int $quantity = 1, bool $reduce = false)
    {
        if ($reduce && !$this->checkStock($quantity))
        {
            abort(400, $this->name . ' has insufficient quantity');
        }
        if ($this->options->kit)
        {
            $this->stock += $reduce ? -$quantity: +$quantity;
            $this->save();
            foreach ($this->kit()->get() as $item){
                $totalQty = $item->pivot->quantity * $quantity;
                $item->updateStock($totalQty, $reduce);
            }
        } else {
            $this->stock += $reduce ? -$quantity: +$quantity;
            $this->save();
        }
    }

}
