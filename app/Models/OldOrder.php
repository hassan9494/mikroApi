<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldOrder extends Model
{
    use HasFactory;

    protected $table = 'old_orders';

    protected $fillable = [
        'items',
        'user_id',
        'phone_number',
        'email',
        'order_notes',
        'shipping_address',
        'status',
        'create_date',
        'comlete_date',
        'AssignTo',
        'SubTotalPrice',
        'TotalRealPrice',
        'TaxStatus',
        'TaxNumber',
        'shipping_cost',
        'name',
        'PaymentMethod',
        'AccountNumber',
        'discount',
        'supplierCommissionPaidDate',
        'isForceNoTax',
        'isReceivables',
        'taxDiscount',
        'invoiceNote',
        'to_bank',
    ];
}
