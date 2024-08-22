<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{

    use ApiResponser;

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        $files = request()->file('files', false);
        if (!$files) {
            foreach([0, 1, 2, 3, 4, 5, 6] as $index) {
                if (request()->hasFile("file$index")) {
                    if (!$files) $files = [];
                    $files[] = request()->file("file$index");
                }
            }
        }
        if (!$files) abort(400);
        $data = [];
        foreach($files as $file)
        {
            $key = $file->store('temp');
            $data[] = [
                'url' => Storage::url($key), 'key' => $key, 'folder' => 'temp'
            ];
        }
        return $this->success(count($data) > 1 ? $data : $data[0]);
    }

    /**
     * @return JsonResponse
     */
    public function content(): JsonResponse
    {
        request()->validate([
            'upload' => 'required|image'
        ]);
        $file = request()->file('upload');
        $key = $file->store('content');
        return response()->json(
            [
                'uploaded' => true,
                'url' => Storage::url($key),
            ]
        );
    }

    /**
     * @return JsonResponse
     */
    public function order(): JsonResponse
    {
        request()->validate([
            'file' => 'required|file'
        ]);
        $file = request()->file('file');
        $key = $file->store('temp');
        return response()->json(
            [
                'id' => request()->get('id'),
                'key' => $key,
                'name' => basename($key),
                'url' => \Storage::url($key),
                'uploaded' => true,
            ]
        );
    }

    /**
     * @return JsonResponse
     */
    public function invoice(): JsonResponse
    {
        request()->validate([
            'file' => 'required|file'
        ]);
        $file = request()->file('file');
        $key = $file->store('temp');
        return response()->json(
            [
                'id' => request()->get('id'),
                'key' => $key,
                'name' => basename($key),
                'url' => \Storage::url($key),
                'uploaded' => true,
            ]
        );
    }


}
