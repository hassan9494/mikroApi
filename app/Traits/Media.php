<?php

namespace App\Traits;

use Spatie\MediaLibrary\InteractsWithMedia;

trait Media {

    use InteractsWithMedia;

    /**
     * @param array $data
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     */
    public function syncMedia($data = [], $collection = 'default')
    {
        foreach ($data as $media)
        {
            if (\Arr::get($media, 'new'))
                $this->addMediaFromDisk($media['key'])->toMediaCollection($collection);
            else if (\Arr::get($media, 'deleted'))
                $this->media()->where('id', $media['id'])->delete();
        }
    }

    /**
     * @param array $data
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     */
    public function syncOneFile($collection = 'default')
    {
        if (request()->hasFile('file')) {
            $this->media()->delete();
            $this->addMedia(request()->file('file'))->toMediaCollection($collection);
        }
    }

}

//
//namespace App\Traits;
//
//use Spatie\MediaLibrary\InteractsWithMedia;
//use Intervention\Image\Facades\Image;
//use Illuminate\Support\Facades\Storage;
//
//trait Media
//{
//
//    use InteractsWithMedia;
//
//    /**
//     * Convert and add media to collection
//     *
//     * @param string $path
//     * @param string $collection
//     * @return void
//     */
//    protected function convertAndAddMedia($path, $collection)
//    {
//        $image = Image::make(Storage::path($path));
//        $filename = pathinfo($path, PATHINFO_FILENAME) . '.webp';
//        $webpPath = 'temp/' . $filename;
//
//        Storage::put($webpPath, $image->encode('webp', 80));
//
//        $this->addMediaFromDisk($webpPath, 'local')
//            ->usingFileName($filename)
//            ->toMediaCollection($collection);
//
//        Storage::delete($webpPath);
//    }
//
//    /**
//     * @param array $data
//     * @param string $collection
//     */
//    public function syncMedia($data = [], $collection = 'default')
//    {
//        foreach ($data as $media) {
//            if (\Arr::get($media, 'new')) {
//                $this->convertAndAddMedia($media['key'], $collection);
//            } else if (\Arr::get($media, 'deleted')) {
//                $this->media()->where('id', $media['id'])->delete();
//            }
//        }
//    }
//
//    /**
//     * @param string $collection
//     */
//    public function syncOneFile($collection = 'default')
//    {
//        if (request()->hasFile('file')) {
//            $this->media()->delete();
//            $path = request()->file('file')->store('temp');
//            $this->convertAndAddMedia($path, $collection);
//            Storage::delete($path);
//        }
//    }
//}
