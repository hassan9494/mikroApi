<?php
namespace Modules\Shop\Support\Enums;

// a Laravel specific base class
use Spatie\Enum\Laravel\Enum;

/**
 * @method static self DRAFT()
 * @method static self COMPLETED()
 * @method static self CANCELED()
 */
final class InvoiceStatus extends Enum
{

}
