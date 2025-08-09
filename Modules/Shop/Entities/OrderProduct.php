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

    public function proccessingAndCompletedOrders()
    {
        return $this->order()->whereIn('status', [OrderStatus::PROCESSING()->value,OrderStatus::COMPLETED()->value]);
    }


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function sales($product, $from, $to)
    {
        $record = OrderProduct::where('product_id', $product)
            ->whereHas('completedOrder', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('completed_at', '>=', $from);
                if ($to) $q->whereDate('completed_at', '<=', $to);
            })
            ->selectRaw("SUM(quantity) as sold")
            ->groupBy('product_id')
            ->get()
            ->first();
        return $record?->sold ?? 0;
    }

    public static function allSales($product, $from, $to)
    {
        $record = OrderProduct::where('product_id', $product)
            ->whereHas('proccessingAndCompletedOrders', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('updated_at', '>=', $from);
                if ($to) $q->whereDate('updated_at', '<=', $to);
            })
            ->selectRaw("SUM(quantity) as sold")
            ->groupBy('product_id')
            ->get()
            ->first();
        return $record?->sold ?? 0;
    }


    public static function taxed_sales($product, $from, $to)
    {
        $record = OrderProduct::where('product_id', $product)
            ->whereHas('completedOrder', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('completed_at', '>=', $from);
                if ($to) $q->whereDate('completed_at', '<=', $to);
            })->whereHas('order', function ($q) {
                $q->where('options->taxed',false);
            })
            ->selectRaw("SUM(quantity) as sold")
            ->groupBy('product_id')
            ->get()
            ->first();
        return $record?->sold ?? 0;
    }


    public static function untaxed_sales($product, $from, $to)
    {
        $record = OrderProduct::where('product_id', $product)
            ->whereHas('completedOrder', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('completed_at', '>=', $from);
                if ($to) $q->whereDate('completed_at', '<=', $to);
            })->whereHas('order', function ($q) {
                 $q->where('options->taxed',true);
            })
            ->selectRaw("SUM(quantity) as sold")
            ->groupBy('product_id')
            ->get()
            ->first();
        return $record?->sold ?? 0;
    }

}
