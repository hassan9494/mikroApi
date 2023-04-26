<?php

namespace Modules\Auth\Http\Controllers\Api;

use App\Repositories\User\UserRepositoryInterface;
use App\Rules\MatchPassword;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserController extends Controller
{

    use ApiResponser;

    /**
     * @var UserRepositoryInterface
     */
    private UserRepositoryInterface $repository;

    /**
     * AuthController constructor.
     * @param UserRepositoryInterface $repository
     */
    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function profile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|min:3',
        ]);
        $this->repository->update(Auth::user()->id, $data);
    }

    public function password(Request $request)
    {
        $request->validate([
            'password' => ['required', 'min:8', new MatchPassword],
            'new_password' => 'required|min:8',
        ]);
        $this->repository->update(Auth::user()->id, ['password'=> Hash::make($request->new_password)]);
    }

}
