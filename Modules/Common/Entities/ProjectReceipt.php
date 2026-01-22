<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shop\Entities\PaymentMethod;
use Modules\Shop\Entities\Transaction;

class ProjectReceipt extends Model
{

    protected $fillable = [
        'course_student_id',
        'graduation_project_id',
        'name',
        'amount',
        'date',
        'notes',
        'explanation',
        'type',
        'check_number',
        'payment_method_id',
        'transaction_id',
    ];

    /**
     * @var string[]
     */
    protected $appends = [
        'number',
    ];

    /**
     * @return string
     */
    public function getNumberAttribute(): string
    {
        return sprintf("%05d", $this->id);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class,'payment_method_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class,'transaction_id');
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }
}
