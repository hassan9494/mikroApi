<?php


namespace Modules\Website\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Blog\Entities\Article;
use Modules\Common\Entities\City;
use Modules\Common\Entities\Link;
use Modules\Common\Entities\Promotion;
use Modules\Common\Entities\Slide;
use Modules\Shop\Entities\Setting;

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

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function links()
    {
        $model = Link::find(1);
        $data = [];

                $data = [
                'id' => $model->id,
                'location' => $model->location,
                'email' => $model->email,
                'facebook' => $model->facebook,
                'instagram' => $model->instagram,
                'telegram' => $model->telegram,
                'whatsapp' => $model->whatsapp,
                'youtube' => $model->youtube,
                'call' => $model->call,
            ];
        return $this->success($data);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function setting()
    {
        $model = Setting::find(2);
        $data = [];

                $data = [
                'value' => $model->value,
            ];
        return $this->success($data);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function articles(Request $request)
    {
        $type = $request->get('type', null);
        $models = Article::where(['type' => $type])->get()->sortBy('order');
        $data = $models->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'image' => $item->getFirstMediaUrl(),
            ];
        });
        return $this->success($data);
    }


    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function article($id)
    {
        $model = Article::findOrFail($id);
        $image = $model->getFirstMediaUrl();
        return $this->success([
            'id' => $model->id,
            'type' => $model->type,
            'title' => $model->title,
            'content' => $model->content,
            'video_url' => $model->video_url,
            'order' => $model->order,
            'image' => $image,
        ]);
    }

}
