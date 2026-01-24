<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Common\Entities\ShortLink;

class ShortLinkResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'link' => $this->link,
            'short_id' => $this->short_id,
            'total_clicks' => $this->total_clicks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'state' => $this->getClickStats(),
        ];
    }

}
