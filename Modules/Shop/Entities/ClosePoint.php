<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClosePoint extends Model
{
    use SoftDeletes;

    protected $fillable = ['close_date', 'closed_by'];

    protected $casts = [
        'close_date' => 'datetime',
    ];

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function previous()
    {
        return self::where('close_date', '<', $this->close_date)
            ->orderBy('close_date', 'desc')
            ->first();
    }
}
