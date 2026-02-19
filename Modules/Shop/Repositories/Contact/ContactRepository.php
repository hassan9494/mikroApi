<?php

namespace Modules\Shop\Repositories\Contact;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Contact;

class ContactRepository extends EloquentRepository implements ContactRepositoryInterface
{
    protected $model;

    public function __construct(Contact $model)
    {
        parent::__construct($model);
    }
}
