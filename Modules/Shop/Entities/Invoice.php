<?php

namespace Modules\Shop\Entities;

use App\Traits\Media;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Modules\Shop\Support\Enums\InvoiceStatus;


/**
 * Class Order
 * @property mixed status
 * @property mixed isDraft
 * @property mixed isCompleted
 * @property mixed id
 * @property Carbon completed_at
 * @property mixed products
 * @package Modules\Shop\Entities
 */
class Invoice extends Model implements HasMedia
{
    use Media;

    protected $fillable = ['name','number','date','status','completed_at','note','source_id'];


    /**
     * @return bool
     */
    public function getIsDraftAttribute(): bool
    {
        return $this->status == InvoiceStatus::DRAFT()->value;
    }



    /**
     * @return bool
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status == InvoiceStatus::COMPLETED()->value;
    }

    /**********************************************************************************************/
    /* Relations **********************************************************************************/
    /**********************************************************************************************/
    /**
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'invoice_products',
            'invoice_id',
            'product_id'
        )->withTrashed()->withPivot('purchases_price','distributer_price', 'quantity','source_sku','normal','sale_price','product_name');
    }

    public function source()
    {
        return $this->belongsTo(Source::class,'source_id');
    }
}
