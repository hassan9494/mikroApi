<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'contacts';
    protected $fillable = [
        'id', 'name', 'phone', 'email', 'fax', 'sub', 'address', 'other', 'note'
    ];

    // Optional relationships (if needed later)
    // public function invoices() { return $this->hasMany(Invoice::class); }
}
