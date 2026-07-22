<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'subscription_name' => $this->subscription_name,
            'description' => $this->description,
            'price' => $this->price,
            'validity' => $this->validity,
            'type' => $this->type,
            'extra' => $this->extra, // This will be cast to an array
        ];
    }
}
