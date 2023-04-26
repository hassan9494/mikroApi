<?php
namespace Modules\Shop\Support\Enums;

// a Laravel specific base class
use Spatie\Enum\Laravel\Enum;

/**
 * @method static self WAITING()
 * @method static self SHIPPED()
 * @method static self DELIVERED()
 */
final class OrderShippingStatus extends Enum
{

}
