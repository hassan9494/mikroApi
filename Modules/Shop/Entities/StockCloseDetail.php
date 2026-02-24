<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

class StockCloseDetail extends Model
{

    protected $fillable = ['stock_close_id','product_id','product_name','stock','price',
        'real_price','purchases_quantity','price_all','product_real_price_all','air_source_sku',
        'sea_source_sku','local_source_sku','stock_location','store_location','link',
        'air_source','sea_source','local_source'
        ];

    public function stockClose()
    {
        return $this->belongsTo(StockClose::class,'stock_close_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function airSource()
    {
        return $this->belongsTo(Source::class,'air_source');
    }

    public function seaSource()
    {
        return $this->belongsTo(Source::class,'sea_source');
    }

    public function localSource()
    {
        return $this->belongsTo(Source::class,'local_source');
    }
}
