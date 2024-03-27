<?php

namespace App\Http\Resources;

use App\Services\YandexDeliveryService\YaDeliveryService;
use Illuminate\Http\Resources\Json\JsonResource;

class YandexDeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $yandexDelivery = new YaDeliveryService();
        $claimInfo =  $yandexDelivery->claimInfo($this->ya_delivery_uuid);
        $courierPhoneNumber = $yandexDelivery->courierPhoneNumber($this->ya_delivery_uuid);
        $courierLocation = $yandexDelivery->courierLocation($this->ya_delivery_uuid);
//        return parent::toArray($request);
        return [
            'claimInfo' => $claimInfo,
            'courierInfo' => [
                'phoneNumber' => $courierPhoneNumber,
                'location' => $courierLocation
            ]
        ];
    }
}
