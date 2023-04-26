<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DatatableFileResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'link' => $this->getFirstMediaUrl(),
            'created_at' => $this->created_at,
        ];

    }
}
