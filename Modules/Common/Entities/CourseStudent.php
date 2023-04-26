<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseStudent extends Model
{

    protected $fillable = [
        'course_id',
        'name',
        'phone',
        'email',
        'notes',
    ];

    /**
     * @return BelongsTo
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }


    /**
     * @return HasMany
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

}
