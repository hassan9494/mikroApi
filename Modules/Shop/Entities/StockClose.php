<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockClose extends Model
{

    protected $fillable = ['user_id','date','name','notes'];


    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function stockCloseDetails()
    {
        return $this->hasMany(StockCloseDetail::class,'stock_close_id');
    }
}
