<?php


namespace Modules\Shop\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Routing\Controller;
use Modules\Shop\Repositories\Address\AddressRepositoryInterface;

class AddressController extends Controller
{

    use ApiResponser;

    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $repository;

    /**
     * AddressController constructor.
     * @param AddressRepositoryInterface $repository
     */
    public function __construct(AddressRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $data = \Auth::user()->addresses;
        return $this->success($data);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function store()
    {
        $data = request()->validate([
            'name' => 'required',
            'city_id' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'content' => 'required',
            'is_primary' => 'boolean|nullable',
        ]);
        $data['user_id'] = \Auth::user()->id;

        \Auth::user()->addresses()
            ->update([
                'is_primary' => false
            ]);

        $data['is_primary'] = true;
        $data = $this->repository->create($data);
        return $this->success($data);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id)
    {
        $data = request()->validate([
            'name' => 'required',
            'city_id' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'content' => 'required',
            'is_primary' => 'boolean|nullable',
        ]);
        $data = $this->repository->update($id, $data);
        return $this->success($data);
    }


    /**
     * @param $id
     */
    public function primary($id)
    {
        \Auth::user()->addresses()
            ->update([
                'is_primary' => false
            ]);

        \Auth::user()->addresses()
            ->where('id', $id)->update([
                'is_primary' => true
            ]);
    }

    /**
     * @param $id
     * @throws \Exception
     */
    public function destroy($id)
    {
        $this->repository->delete($id);
    }


}
