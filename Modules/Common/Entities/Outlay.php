<?php

namespace Modules\Common\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Modules\Shop\Entities\PaymentMethod;
use Modules\Shop\Entities\Transaction;
use Spatie\MediaLibrary\HasMedia;

class Outlay extends Model implements HasMedia
{
    use Media;

    protected $fillable = [
        'name',
        'amount',
        'date',
        'notes',
        'invoice',
        'type',
        'sub_type',
        'total_amount',
        'tax_number',
        'payment_method_id',
        'tax'
    ];



    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class,'payment_method_id');
    }

    /**
     * NEW: Define the polymorphic relationship
     */
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

}
