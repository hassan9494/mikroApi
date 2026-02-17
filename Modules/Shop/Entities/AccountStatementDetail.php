<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

class AccountStatementDetail extends Model
{
    protected $fillable = [
        'account_statement_id',
        'order_id',
        'transaction_number',
        'description',
        'transaction_type',
        'debtor',
        'creditor',
        'balance',
        'transaction_date',
    ];

    protected $casts = [
        'debtor'   => 'decimal:3',
        'creditor' => 'decimal:3',
        'balance'  => 'decimal:3',
        'transaction_date' => 'datetime',
    ];

    public function accountStatement()
    {
        return $this->belongsTo(AccountStatement::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
