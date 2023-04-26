<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{


    /**
     * @var string[]
     */
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

}
