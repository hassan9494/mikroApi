<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubCategoryResource extends JsonResource
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
            'order' => $this->order,
            'parent' => $this->parentCategory,
            'children' => CategoryResource::collection($this->children),
            'image' => $image
        ];
    }
}
