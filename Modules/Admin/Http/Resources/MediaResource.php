<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'url' => $this->getFullUrl(),
            'name' => $this->file_name,
            'size' => $this->size,
        ];
    }
}
