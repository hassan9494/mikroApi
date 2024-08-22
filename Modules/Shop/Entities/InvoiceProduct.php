<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Shop\Support\Enums\InvoiceStatus;

class InvoiceProduct extends Pivot
{
    protected $table = 'invoice_products';

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
