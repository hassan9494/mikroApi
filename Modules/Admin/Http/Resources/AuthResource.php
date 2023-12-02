<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $x = [];
        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $x[] = ['subject'=>$permission->name,'action' =>'read'];
            }
        }
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->roles->map(function ($item, $key) {
                return $item->name;
            }),
            'permissions' => $x
        ];
    }
}
