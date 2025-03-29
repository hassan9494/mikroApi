<?php

namespace Modules\Auth\Http\Controllers\Api;

use App\Repositories\User\UserRepositoryInterface;
use App\Rules\MatchPassword;
use App\Traits\ApiResponser;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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
     * @return JsonResponse
     */
    public function loginAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|string|email|',
            'password' => 'required|string|min:6'
        ]);

        if (!\Auth::attempt($data)) {
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
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|string|email|',
            'password' => 'required|string|min:6'
        ]);

        if (!\Auth::attempt($data)) {
            return $this->error('Credentials not match', 401);
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
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed'
        ]);

        $data['password'] = bcrypt($data['password']);

        $user = $this->repository->create($data);

        $token = $user->createToken('web')->plainTextToken;

        return $this->success([
            'token' => $token
        ]);
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

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $data = $request->only('email', 'password', 'password_confirmation', 'token');
        $status = Password::reset(
            $data,
            function ($user, $password) {
                $user
                    ->forceFill(['password' => Hash::make($password)])
                    ->setRememberToken(Str::random(60));
                $user->save();
//                event(new PasswordReset($user));
            }
        );
        if ($status === Password::PASSWORD_RESET) {
            return  $this->success();
        }
        return $this->error('Password reset fail', 400);
    }


    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'min:8', new MatchPassword],
            'new_password' => 'required|min:8',
        ]);
        Auth::user()->update(['password'=> Hash::make($request->new_password)]);
    }

    public function resetPasswordForm(Request $request)
    {
        $token = $request->token;
        return redirect()->away(env('APP_SITE_URL') . "/password-reset?token=$token"); // The deep link
    }


    public function verify(Request $request)
    {
        $user = $this->repository->findOrFail($request->id);
        if (
            !hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))
        ) {
            return redirect()->away(env('APP_SITE_URL') . '/email/unverified');
        }
        $user->markEmailAsVerified();
//        return redirect(url('password-reset?token=$token'));
        return redirect()->away(env('APP_SITE_URL') . '/email/verified'); // The deep link
    }

}
