<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AccountStatement extends Model
{
    protected $fillable = [
        'user_id',
        'account_number',
        'account_name',
        'address',
        'phone',
        'from_date',
        'to_date',
        'total_debtor',
        'total_creditor',
        'notes',
    ];

    protected $casts = [
        'from_date' => 'datetime',
        'to_date'   => 'datetime',
        'total_debtor'  => 'decimal:3',
        'total_creditor' => 'decimal:3',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(AccountStatementDetail::class);
    }
}
