<?php

namespace Modules\Common\Providers;

use Illuminate\Support\ServiceProvider;

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
            'Modules\Common\Repositories\Dept\DeptRepositoryInterface',
            'Modules\Common\Repositories\Dept\DeptRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\Outlay\OutlayRepositoryInterface',
            'Modules\Common\Repositories\Outlay\OutlayRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\CustomsStatement\CustomsStatementRepositoryInterface',
            'Modules\Common\Repositories\CustomsStatement\CustomsStatementRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\Receipt\ReceiptRepositoryInterface',
            'Modules\Common\Repositories\Receipt\ReceiptRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\Course\CourseRepositoryInterface',
            'Modules\Common\Repositories\Course\CourseRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\CourseStudent\CourseStudentRepositoryInterface',
            'Modules\Common\Repositories\CourseStudent\CourseStudentRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\GraduationProject\GraduationProjectRepositoryInterface',
            'Modules\Common\Repositories\GraduationProject\GraduationProjectRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\City\CityRepositoryInterface',
            'Modules\Common\Repositories\City\CityRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\Slide\SlideRepositoryInterface',
            'Modules\Common\Repositories\Slide\SlideRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\Tag\TagRepositoryInterface',
            'Modules\Common\Repositories\Tag\TagRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\Promotion\PromotionRepositoryInterface',
            'Modules\Common\Repositories\Promotion\PromotionRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\Links\LinksRepositoryInterface',
            'Modules\Common\Repositories\Links\LinksRepository'
        );
        $this->app->singleton(
            'Modules\Common\Repositories\File\FileRepositoryInterface',
            'Modules\Common\Repositories\File\FileRepository'
        );
    }

}
