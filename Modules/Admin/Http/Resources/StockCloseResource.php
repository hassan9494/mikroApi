<?php

namespace Modules\Admin\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCloseResource extends JsonResource
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
            'date' => Carbon::parse($this->date)->format('Y-m-d'),
            'updated_at' => $this->updated_at,
            'notes' => $this->notes,
            'user' => $this->user,
        ];
    }

}
