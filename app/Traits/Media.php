<?php

namespace App\Traits;

use Spatie\MediaLibrary\InteractsWithMedia;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;

trait Media {

    use InteractsWithMedia;

    /**
     * Convert and add media to collection
     *
     * @param string $path
     * @param string $collection
     * @return void
     */
    protected function convertAndAddMedia($path, $collection)
    {
        $image = Image::make(Storage::path($path));
        $filename = pathinfo($path, PATHINFO_FILENAME) . '.webp';
        $webpPath = 'temp/' . $filename;

        Storage::put($webpPath, $image->encode('webp', 80));

        $this->addMediaFromDisk($webpPath, 'local')
            ->usingFileName($filename)
            ->toMediaCollection($collection);

        Storage::delete($webpPath);
    }

    /**
     * @param array $data
     * @param string $collection
     */
    public function syncMedia($data = [], $collection = 'default')
    {
        foreach ($data as $media)
        {
            if (\Arr::get($media, 'new')) {
                $this->convertAndAddMedia($media['key'], $collection);
            } else if (\Arr::get($media, 'deleted')) {
                $this->media()->where('id', $media['id'])->delete();
            }
        }
    }

    /**
     * @param string $collection
     */
    public function syncOneFile($collection = 'default')
    {
        if (request()->hasFile('file')) {
            $this->media()->delete();
            $path = request()->file('file')->store('temp');
            $this->convertAndAddMedia($path, $collection);
            Storage::delete($path);
        }
    }
}
