<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    use ApiResponser;

    // Define the disk to use consistently
    protected $disk = 'public';

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        $files = request()->file('files', false);

        if (!$files) {
            $files = request()->file('media', false);
        }

        if (!$files) {
            foreach([0, 1, 2, 3, 4, 5, 6] as $index) {
                if (request()->hasFile("file$index")) {
                    if (!$files) $files = [];
                    $files[] = request()->file("file$index");
                }
            }
        }

        if (!$files) {
            return response()->json(['error' => 'No files uploaded'], 400);
        }

        // Ensure $files is always an array
        if (!is_array($files)) {
            $files = [$files];
        }

        // Check maximum file limit (13 images)
        if (count($files) > 13) {
            return response()->json([
                'error' => 'Maximum 13 images allowed per product'
            ], 400);
        }

        $data = [];
        foreach($files as $file) {
            $key = $file->store('temp', $this->disk);
            $data[] = [
                'url' => Storage::disk($this->disk)->url($key),
                'key' => $key,
                'folder' => 'temp'
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
        $key = $file->store('content', $this->disk);

        return response()->json([
            'uploaded' => true,
            'url' => Storage::disk($this->disk)->url($key),
        ]);
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
        $key = $file->store('temp', $this->disk);
        return response()->json([
            'id' => request()->get('id'),
            'key' => $key,
            'name' => basename($key),
            'url' => Storage::disk($this->disk)->url($key),
            'uploaded' => true,
        ]);
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
        $key = $file->store('temp', $this->disk);
        return response()->json([
            'id' => request()->get('id'),
            'key' => $key,
            'name' => basename($key),
            'url' => Storage::disk($this->disk)->url($key),
            'uploaded' => true,
        ]);
    }
}

