<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{

    protected $fillable = [
        'name',
        'cost',
        'start_at',
        'end_at',
        'description',
    ];


    /**
     * @return HasMany
     */
    public function students(): HasMany
    {
        return $this->hasMany(CourseStudent::class);
    }

    /**
     * @return HasManyThrough
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Receipt::class, CourseStudent::class);
    }

}
