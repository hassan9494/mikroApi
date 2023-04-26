<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 *
 */
class Kit extends Model
{

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'kit_id',
        'product_id',
        'quantity',
    ];

    /**
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_id');
    }

}

