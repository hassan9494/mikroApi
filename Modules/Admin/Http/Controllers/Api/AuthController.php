<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Repositories\User\UserRepositoryInterface;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Modules\Admin\Http\Resources\AuthResource;

class AuthController extends Controller
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

    /**
     * @param Request $request
     * @return AuthResource
     */
    public function me(Request $request): AuthResource
    {
        return new AuthResource($request->user());
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:6'
        ]);

        if (!\Auth::attempt($data))
        {
            return $this->error('Credentials not match', 401);
        }

        if (!\Auth::user()->hasRole('admin'))
        {
            return $this->error('You dont have the right permissions', 401);
        }

        $token = \Auth::user()->createToken('web')->plainTextToken;

        return $this->success([
            'token' => $token
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        \Auth::user()->tokens()->delete();
        return $this->success();
    }

    /**
     * @param Request $request
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        Password::sendResetLink(
            $request->only('email')
        );
    }

}
