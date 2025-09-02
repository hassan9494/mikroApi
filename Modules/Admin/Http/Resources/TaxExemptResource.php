<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaxExemptResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user ?->id,
                'name' => $this->user ?->name,
                'email' => $this->user ?->email,
                'phone' => $this->user ?->phone,
            ],
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'identity_number_type' => $this->identity_number_type,
            'identity_number' => $this->identity_number,

            'tax_exempt' => $this->tax_exempt,
            'tax_zero' => $this->tax_zero,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'exemption_expiration_date' => $this->exemption_expiration_date,
            'media' => MediaResource::collection($this->media)


        ];
    }

}
