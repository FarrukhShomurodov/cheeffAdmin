<?php

namespace App\Services\YandexDeliveryService;

use Illuminate\Support\Facades\Http;

class YaDeliveryCourier
{
    public function DeliveryLocation($claimId)
    {
        $response = Http::yandexDelivery()->get('b2b/cargo/integration/v2/claims/performer-position', [
            "claim_id" => $claimId
        ]);

        return $response;
    }

    public function CourierTrackingLink($claimId)
    {
        $response = Http::yandexDelivery()->get('b2b/cargo/integration/v2/claims/performer-position', [
            "claim_id" => $claimId
        ]);

        return $response;
    }

    public function CourierArrivalTime($claimId)
    {
        $response = Http::yaDelivery()->get('b2b/cargo/integration/v2/claims/points-eta', [
            "claim_id" => $claimId
        ]);

        return $response;
    }
}
