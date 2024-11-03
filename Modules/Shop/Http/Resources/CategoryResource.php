<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $media = $this->getMedia();
        $image = count($media) > 0 ? $media[0]->getFullUrl() : '';
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'parent' => $this->parent,
            'order' => $this->order,
            'children' => CategoryResource::collection($this->children()->where('available',true)->orderBy('order','asc')->get()),
            'image' => $image
        ];
    }
}
