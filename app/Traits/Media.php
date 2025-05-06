<?php
//
//namespace App\Traits;
//
//use Spatie\MediaLibrary\InteractsWithMedia;
//
//trait Media {
//
//    use InteractsWithMedia;
//
//    /**
//     * @param array $data
//     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
//     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
//     */
//    public function syncMedia($data = [], $collection = 'default')
//    {
//        foreach ($data as $media)
//        {
//            if (\Arr::get($media, 'new'))
//                $this->addMediaFromDisk($media['key'])->toMediaCollection($collection);
//            else if (\Arr::get($media, 'deleted'))
//                $this->media()->where('id', $media['id'])->delete();
//        }
//    }
//
//    /**
//     * @param array $data
//     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
//     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
//     */
//    public function syncOneFile($collection = 'default')
//    {
//        if (request()->hasFile('file')) {
//            $this->media()->delete();
//            $this->addMedia(request()->file('file'))->toMediaCollection($collection);
//        }
//    }
//
//}


namespace App\Traits;

use Spatie\MediaLibrary\InteractsWithMedia;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;

trait Media
{
    use InteractsWithMedia;

    /**
     * Convert and add media to collection (only for images)
     * For non-images, save as-is
     */
    protected function addMediaToCollection($path, $collection)
    {
        $mimeType = Storage::mimeType($path);
        $filename = pathinfo($path, PATHINFO_BASENAME);

        // Check if the file is an image (GD/Intervention supports these)
        $isImage = in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp',
        ]);

        if ($isImage) {
            // Convert images to WebP
            $image = Image::make(Storage::path($path));
            $filename = pathinfo($path, PATHINFO_FILENAME) . '.webp';
            $webpPath = 'temp/' . $filename;

            Storage::put($webpPath, $image->encode('webp', 80));
            $this->addMediaFromDisk($webpPath, 'local')
                ->usingFileName($filename)
                ->toMediaCollection($collection);

            Storage::delete($webpPath);
        } else {
            // For non-images (PDF, DOCX, etc.), save the original file
            $this->addMediaFromDisk($path, 'local')
                ->usingFileName($filename)
                ->toMediaCollection($collection);
        }
    }

    /**
     * Sync media files (images + non-images)
     */
    public function syncMedia($data = [], $collection = 'default')
    {
        foreach ($data as $media) {
            if (\Arr::get($media, 'new')) {
                $this->addMediaToCollection($media['key'], $collection);
            } elseif (\Arr::get($media, 'deleted')) {
                $this->media()->where('id', $media['id'])->delete();
            }
        }
    }

    /**
     * Sync a single file
     */
    public function syncOneFile($collection = 'default')
    {
        if (request()->hasFile('file')) {
            $this->media()->delete();
            $path = request()->file('file')->store('temp');
            $this->addMediaToCollection($path, $collection);
            Storage::delete($path);
        }
    }
}
