<?php

namespace Modules\Blog\Repositories\Article;

use App\Repositories\Base\EloquentRepository;
use Modules\Blog\Entities\Article;

/**
 * Class ArticleRepository
 * @package Modules\Blog\Repositories\Article
 */
class ArticleRepository extends EloquentRepository implements ArticleRepositoryInterface
{

    /**
     * ArticleRepository constructor.
     * @param Article $model
     */
    public function __construct(Article $model)
    {
        parent::__construct($model);
    }

}
