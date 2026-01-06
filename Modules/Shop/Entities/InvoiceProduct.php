<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Shop\Support\Enums\InvoiceStatus;

class InvoiceProduct extends Pivot
{
    protected $table = 'invoice_products';
    protected $fillable = [
        'purchases_price',
        'distributer_price',
        'quantity',
        'allocation',
        'source_sku',
        'normal',
        'sale_price',
        'product_name',
        'base_purchases_price',
        'exchange_factor',
        'stock_available_qty',
        'store_available_qty'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }



    public function completedInvoice()
    {
        return $this->invoice()->where('status', '=',InvoiceStatus::COMPLETED()->value);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
