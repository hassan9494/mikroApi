<?php
namespace Modules\Shop\Support\Enums;

// a Laravel specific base class
use Spatie\Enum\Laravel\Enum;

/**
 * @method static self PENDING()
 * @method static self PROCESSING()
 * @method static self COMPLETED()
 * @method static self CANCELED()
 * @method static self DRAFT()
 */
final class OrderStatus extends Enum
{

}
