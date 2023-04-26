<?php

namespace App\Traits;

/*
|--------------------------------------------------------------------------
| Api Responser Trait
|--------------------------------------------------------------------------
|
| This trait will be used for any response we sent to clients.
|
*/

use Illuminate\Http\JsonResponse;

trait ApiResponser
{
    /**
     * @param $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function success(
        $data = [],
        string $message = null,
        int $code = 200
    ): JsonResponse
    {
        return response()->json([
            'status' => 'Success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * @param string $message
     * @param int $code
     * @param null $data
     * @return JsonResponse
     */
    protected function error(
        string $message,
        int $code,
        $data = null
    ): JsonResponse
    {
        return response()->json([
            'status' => 'Error',
            'message' => $message,
            'data' => $data
        ], $code);
    }

}
