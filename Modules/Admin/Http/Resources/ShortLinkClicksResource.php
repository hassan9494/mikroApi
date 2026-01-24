<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Common\Entities\ShortLink;

class ShortLinkClicksResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'shortLink' => $this->shortLink,
            'ip_address' => $this->ip_address,
            'country' => $this->country,
            'click_count' => $this->click_count,
            'individualClicks' => $this->individualClicks,
            'clickTimes' => $this->getClickTimes(),
        ];
    }

}
