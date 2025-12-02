<?php

namespace Modules\Admin\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'course_student_id' => $this->course_student_id,
            'graduation_project_id' => $this->graduation_project_id,
            'name' => $this->name,
            'amount' => $this->amount,
            'date' => Carbon::parse($this->date)->format('Y-m-d'),
            'explanation' => $this->explanation,
            'type' => $this->paymentMethod->name,
            'check_number' => $this->check_number,
            'taxed' => $this->taxed,
            'tax_number' => $this->tax_number,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'payment_method_id' => $this->payment_method_id,
            'transaction_id' => $this->transaction_id,
            'number' => $this->number,

        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'id' => $product->id,
                'name' => $product->pivot->product_name,
                'image' => $product->getFirstMediaUrl()
            ];
        }
        return $items;
    }
    private function getBrands()
    {
        $items = [];
        foreach ($this->brands as $brand)
        {
            $items[] = [
                'id' => $brand->id,
                'name' => $brand->pivot->brand_name
            ];
        }
        return $items;
    }

}
