<?php

namespace Modules\Shop\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Shop\Support\Enums\OrderStatus;

class OrderProduct extends Pivot
{

    protected $table = 'order_products';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function completedOrder()
    {
        return $this->order()->where('status', '=',OrderStatus::COMPLETED()->value);
    }


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function sales($product, $from, $to)
    {
        $record = OrderProduct::where('product_id', $product)
            ->whereHas('completedOrder', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('inspection_date', '>=', $from);
                if ($to) $q->whereDate('inspection_date', '<=', $to);
            })
            ->selectRaw("SUM(quantity) as sold")
            ->groupBy('product_id')
            ->get()
            ->first();
        return $record?->sold ?? 0;
    }

}
