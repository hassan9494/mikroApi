<?php


namespace Modules\Website\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Routing\Controller;
use Modules\Common\Entities\City;
use Modules\Common\Entities\Promotion;
use Modules\Common\Entities\Slide;

class WebsiteController extends Controller
{

    use ApiResponser;

    /**
     * WebsiteController constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function offer()
    {
        $models = Promotion::all()->sortBy('order');
        $data = [];
        foreach ($models as $model) {
            if (!($image = $model->getFirstMediaUrl())) continue;
            $data[] = [
                'id' => $model->id,
                'name' => $model->name,
                'order' => $model->order,
                'link' => $model->link,
                'image' => $image,
            ];
        }
        return $this->success($data);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function slide()
    {
        $models = Slide::all()->sortBy('order');
        $data = [];
        foreach ($models as $model) {
            if (!($image = $model->getFirstMediaUrl())) continue;
                $data[] = [
                'id' => $model->id,
                'name' => $model->name,
                'order' => $model->order,
                'image' => $image,
            ];
        }
        return $this->success($data);
    }


}
