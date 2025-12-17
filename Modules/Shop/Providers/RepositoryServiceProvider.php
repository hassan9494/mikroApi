<?php

namespace Modules\Shop\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shop\Entities\PaymentMethod;

/**
 * Class RepositoryServiceProvider
 * @package Modules\Other\Providers
 */
class RepositoryServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'Modules\Shop\Repositories\Category\CategoryRepositoryInterface',
            'Modules\Shop\Repositories\Category\CategoryRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Brand\BrandRepositoryInterface',
            'Modules\Shop\Repositories\Brand\BrandRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Location\LocationRepositoryInterface',
            'Modules\Shop\Repositories\Location\LocationRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Source\SourceRepositoryInterface',
            'Modules\Shop\Repositories\Source\SourceRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\TaxExempt\TaxExemptRepositoryInterface',
            'Modules\Shop\Repositories\TaxExempt\TaxExemptRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Product\ProductRepositoryInterface',
            'Modules\Shop\Repositories\Product\ProductRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\ProductVariants\ProductVariantsRepositoryInterface',
            'Modules\Shop\Repositories\ProductVariants\ProductVariantsRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Order\OrderRepositoryInterface',
            'Modules\Shop\Repositories\Order\OrderRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\ReturnOrder\ReturnOrderRepositoryInterface',
            'Modules\Shop\Repositories\ReturnOrder\ReturnOrderRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Invoice\InvoiceRepositoryInterface',
            'Modules\Shop\Repositories\Invoice\InvoiceRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Address\AddressRepositoryInterface',
            'Modules\Shop\Repositories\Address\AddressRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\ShippingProvider\ShippingProviderRepositoryInterface',
            'Modules\Shop\Repositories\ShippingProvider\ShippingProviderRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Coupon\CouponRepositoryInterface',
            'Modules\Shop\Repositories\Coupon\CouponRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\PaymentMethod\PaymentMethodRepositoryInterface',
            'Modules\Shop\Repositories\PaymentMethod\PaymentMethodRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\Transaction\TransactionRepositoryInterface',
            'Modules\Shop\Repositories\Transaction\TransactionRepository'
        );
        $this->app->singleton(
            'Modules\Shop\Repositories\StockAdjustment\StockAdjustmentRepositoryInterface',
            'Modules\Shop\Repositories\StockAdjustment\StockAdjustmentRepository'
        );

    }

}
