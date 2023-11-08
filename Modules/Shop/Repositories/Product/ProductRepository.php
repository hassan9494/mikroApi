<?php

namespace Modules\Shop\Repositories\Product;

use App\Repositories\Base\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Modules\Shop\Entities\Product;

/**
 * Class EloquentDevice
 * @package App\Repositories\Device
 */
class ProductRepository extends EloquentRepository implements ProductRepositoryInterface
{

    /**
     * @var Product
     */
    protected $model;

    /**
     * ProductRepository constructor.
     * @param Product $model
     */
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function create($data)
    {
        $model = parent::create($data);
        $model->categories()->attach($data['categories']);
        $model->syncMedia($data['media'] ?? []);
    }

    public function update($id, $data)
    {
        $model = parent::update($id, $data);

        if ($data['categories'] ?? false)
            $model->categories()->sync($data['categories']);

        $kit = [];
        if ($data['kit'] ?? false) {
            foreach ($data['kit'] as $item) {
                $kit[$item['id']] = Arr::only($item, 'quantity');
            }
        }
        if (count($kit) != 0)
        $model->kit()->sync($kit);
        $model->syncMedia($data['media'] ?? []);
    }

    /**
     * @param $searchWord
     * @param $category
     * @param int $limit
     * @return LengthAwarePaginator
     */
    public function search($searchWord, $category, $limit = 20)
    {
        $query = Product::query();
        if ($searchWord) {
            return Product::search($searchWord)->paginate($limit);
        } else if ($category) {
            $query->whereHas('categories', function (Builder $q) use ($category) {
                $q->where('slug', $category);
            });
        } else {
            $query->where(['options->featured' => true]);
        }
        return $query->paginate($limit);
    }

    public function autocomplete($searchWord, $limit = 20)
    {

        $inpStrArray = explode(" ", $searchWord); #converting string to array
        $revArr = array_reverse($inpStrArray); #reversing the array
        $revStr = implode(" ", $revArr); #joining the array back to string

        return $this->model->where('sku', 'like', "%$searchWord%")
            ->orWhere('name', 'like', "%$searchWord%")
            ->orWhere('short_description', 'like', "%$searchWord%")
            ->orwhere('sku', 'like', "%$revStr%")
            ->orwhere('name', 'like', "%$revStr%")
            ->orwhere('short_description', 'like', "%$revStr%")
            ->limit($limit)
            ->get();
    }

}
