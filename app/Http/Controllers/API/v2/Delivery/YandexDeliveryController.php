<?php

namespace App\Http\Controllers\API\v2\Delivery;

use App\Http\Controllers\Controller;
use App\Services\YandexDeliveryService\YaDeliveryService;
use Illuminate\Http\JsonResponse;

class YandexDeliveryController extends Controller
{
    protected YaDeliveryService $yaDeliveryService;

    /**
     * @param YaDeliveryService $deliveryService
     */
    public function __construct(YaDeliveryService $deliveryService)
    {
        $this->yaDeliveryService = $deliveryService;
    }

    public function claimInfo($claimId): JsonResponse
    {
        $claimInfo = $this->yaDeliveryService->claimInfo($claimId);
        return new JsonResponse($claimInfo);
    }
}
