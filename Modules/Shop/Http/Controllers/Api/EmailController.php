<?php

namespace Modules\Shop\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Modules\Shop\Emails\SendOrderDetailsEmail;
use Modules\Shop\Emails\SendToUserEmail;
use Modules\Shop\Entities\Order;


class EmailController extends Controller
{
    public function sendEmailToUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|integer',
            'message' => 'required|string|min:6',
            'subject' => 'required|string|min:2',
            'title' => 'required|string|min:2',
            'to_all' => 'nullable|boolean'
        ]);
        if ($data['user_id'] != null) {
            $user = User::find($data['user_id']);

            $details = [
                'subject' => $data['subject'],
                'title' => $data['title'],
                'body' => $data['message']
            ];

            Mail::to($user->email)->send(new SendToUserEmail($details));
        }


        return response()->json([
            'message' => 'Email Sent Successfully',
        ],200);
    }
    public function sendOrderDetails(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|integer',
            'message' => 'required|string|min:6',
            'subject' => 'required|string|min:2',
            'title' => 'required|string|min:2',
            'to_all' => 'nullable|boolean'
        ]);
        if ($data['user_id'] != null) {
            $user = User::find($data['user_id']);

            $details = [
                'subject' => $data['subject'],
                'title' => $data['title'],
                'body' => $data['message']
            ];
            $order = Order::find(209);

            Mail::to($user->email)->send(new SendOrderDetailsEmail($details,$order));
        }


        return response()->json([
            'message' => 'Email Sent Successfully',
        ],200);
    }

}
