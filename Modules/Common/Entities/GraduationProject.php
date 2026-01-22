<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GraduationProject extends Model
{

    protected $fillable = [
        'name',
        'cost',
        'deadline',
        'description',
        'students',
        'notes',
        'completed',
        'implementer_name'
    ];


    protected $appends = [
        'total_paid'
    ];

    /**
     * @return mixed
     */
    public function getTotalPaidAttribute(): mixed
    {
        return $this->payments()->sum('amount');
    }

    /**
     * @return HasMany
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ProjectReceipt::class);
    }

}
