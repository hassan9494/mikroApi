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
