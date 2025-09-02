<?php


namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $fillable = ['key', 'name', 'description', 'value', 'field', 'active'];


}
