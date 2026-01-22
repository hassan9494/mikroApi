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
        $user = \Auth::user();

        // Get user's role names from the roles collection
        // $user->roles returns a Collection of role objects, we need to get the role names
        $roleNames = $user->roles->pluck('name')->toArray(); // This gives us an array of role names

        // Check if user has ONLY the 'user' role and no other roles
        $hasOnlyUserRole = count($roleNames) === 1 && in_array('user', $roleNames);

        // Different validation rules based on user roles
        $validationRules = [
            'name' => 'required',
            'phone' => 'string|max:20',
            'city_id' => 'required',
            'is_primary' => 'boolean|nullable',
        ];

        // If user has ONLY the 'user' role, make all fields required
        if ($hasOnlyUserRole) {
            $validationRules['email'] = 'required|email';
            $validationRules['phone'] = 'required';
            $validationRules['content'] = 'required';
        } else {
            // For users with other roles (admin, manager, etc.), make them optional
            $validationRules['email'] = 'nullable|email';
            // $validationRules['phone'] = 'nullable';
            $validationRules['content'] = 'nullable';
        }

        $data = request()->validate($validationRules);
        $data['user_id'] = $user->id;

        // Set defaults for empty fields for non-'user' roles
        if (!$hasOnlyUserRole) {
            if (!isset($data['email']) || empty($data['email'])) {
                $data['email'] = $user->email ?? '';
            }

            if (!isset($data['phone']) || empty($data['phone'])) {
                $data['phone'] = '';
            }

            if (!isset($data['content']) || empty($data['content'])) {
                $data['content'] = '';
            }
        }

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
        $user = \Auth::user();

        // Get user's role names from the roles collection
        $roleNames = $user->roles->pluck('name')->toArray();

        // Check if user has ONLY the 'user' role and no other roles
        $hasOnlyUserRole = count($roleNames) === 1 && in_array('user', $roleNames);

        // Different validation rules based on user roles
        $validationRules = [
            'name' => 'required',
            'phone' => 'string|max:20',
            'city_id' => 'required',
            'is_primary' => 'boolean|nullable',
        ];

        // If user has ONLY the 'user' role, make all fields required
        if ($hasOnlyUserRole) {
            $validationRules['email'] = 'required|email';
            $validationRules['phone'] = 'required';
            $validationRules['content'] = 'required';
        } else {
            // For users with other roles (admin, manager, etc.), make them optional
            $validationRules['email'] = 'nullable|email';
            // $validationRules['phone'] = 'nullable';
            $validationRules['content'] = 'nullable';
        }

        $data = request()->validate($validationRules);

        // Set defaults for empty fields for non-'user' roles
        if (!$hasOnlyUserRole) {
            if (!isset($data['email']) || empty($data['email'])) {
                $data['email'] = '';
            }
            if (!isset($data['phone']) || empty($data['phone'])) {
                $data['phone'] = '';
            }
            if (!isset($data['content']) || empty($data['content'])) {
                $data['content'] = '';
            }
        }

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
