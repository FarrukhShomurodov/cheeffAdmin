<?php

namespace App\Services\YandexDeliveryService;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YaDeliveryService
{
    private array $headers = [
        'Accept-Language' => 'ru',
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer y0_AgAAAAB0LiLaAAc6MQAAAAD71x7gAABckubl22hGUZPWE3MCIa4yRsn_Ug',
    ];

    public function createClaim($orderData): mixed
    {
//        $orderData = $orderData['data'];
//            return $orderData

        $sellerEmail = $orderData['shop']['seller']['email'] ?? "uamerike@gmail.com";
        $body = '{
          "client_requirements": {
            "cargo_options": [
              "thermobag"
            ],
            "pro_courier": false,
            "taxi_class": "courier"
          },
          "comment": "some comment",
          "emergency_contact": {
            "name": "' . $orderData['user']['firstname'] . '",
            "phone": "' . $orderData['user']['phone'] . '",
            "phone_additional_code": "' . $orderData['user']['phone'] . '"
          },
          "items": [
            {
              "cost_currency": "UZS",
              "cost_value": "' . $orderData['total_price'] . '",
              "droppof_point": 2,
              "pickup_point": 1,
              "quantity": 1,
              "title": "еда"
            }
          ],
          "optional_return": false,
          "route_points": [
            {
              "address": {
               "city": "Tashkent",
                "coordinates": [
                  ' . $orderData['shop']['location']['longitude'] . ',
                  ' . $orderData['shop']['location']['latitude'] . '
                ],
                "country": "Uzbekistan",
                "fullname": "address"
              },
              "contact": {
                "email": "' . $sellerEmail . '",
                "name": "' . $orderData['shop']['seller']['firstname'] . '",
                "phone": "' . $orderData['shop']['seller']['phone'] . '"
              },
              "external_order_cost": {
                "currency": "UZS",
                "currency_sign": "som",
                "value": "' . $orderData['total_price'] . '"
              },
              "point_id": 1,
              "skip_confirmation": false,
              "type": "source",
              "visit_order": 1
            },
            {
              "address": {
                "city": "Tashkent",
                "comment": "string",
                "coordinates": [
                  ' . $orderData['location']['longitude'] . ',
                  ' . $orderData['location']['latitude'] . '
                ],
                "country": "Uzbekistan",
                "fullname": "' . $orderData['address']['address'] . '"
              },
              "contact": {
                "name": "' . $orderData['user']['firstname'] . '",
                "phone": "' . $orderData['user']['phone'] . '"
              },
              "external_order_cost": {
                "currency": "UZS",
                "currency_sign": "som",
                "value": "' . $orderData['total_price'] . '"
              },
              "point_id": 2,
              "skip_confirmation": false,
              "type": "destination",
              "visit_order": 2
            }
          ],
          "skip_client_notify": false,
          "skip_emergency_notify": false
        }';

        $request_id = Str::uuid();

        $res = Http::withHeaders($this->headers)->post('https://b2b.taxi.yandex.net/b2b/cargo/integration/v2/claims/create?request_id=' . $request_id, json_decode($body, true));

        return $res->json();
    }

    public function estimateCost($addresses): mixed
    {

        $body = '{
          "items": [
            {
              "dropoff_point": 2,
              "pickup_point": 1,
              "quantity": 1
            }
          ],
          "requirements": {
            "cargo_options": [
              "thermobag"
            ],
            "pro_courier": true,
            "taxi_class": "courier"
          },
          "route_points": [
             {
              "coordinates": [
                ' . $addresses['shopAddress']['longitude'] . ',
                ' . $addresses['shopAddress']['latitude'] . '
              ],
              "fullname": "address",
              "id": 1
            },
            {
              "coordinates": [
                ' . $addresses['userAddress']['longitude'] . ',
                ' . $addresses['userAddress']['latitude'] . '
              ],
              "fullname": "address",
              "id": 2
            }
          ],
          "skip_door_to_door": false
        }';

        $res = Http::withHeaders($this->headers)->post('https://b2b.taxi.yandex.net/b2b/cargo/integration/v2/check-price', json_decode($body, true));

        return $res->json();
    }

    public function claimInfo($claim_id): mixed
    {
        $res = Http::withHeaders($this->headers)->post('https://b2b.taxi.yandex.net/b2b/cargo/integration/v2/claims/info?claim_id=' . $claim_id);

        return $res->json();
    }

    public function cancelClaim($claim_id)
    {
        $body = '{
          "cancel_state": "free",
          "version": 1
        }';

        $res = Http::withHeaders($this->headers)->post('https://b2b.taxi.yandex.net/b2b/cargo/integration/v2/claims/cancel?claim_id=' . $claim_id, json_decode($body, true));

        return $res->json();
    }

    public function acceptClaim($claim_id)
    {
        $body = '{
          "version": 1
        }';
        $res = Http::withHeaders($this->headers)->post('https://b2b.taxi.yandex.net/b2b/cargo/integration/v2/claims/accept?claim_id=' . $claim_id, json_decode($body, true));

        return $res->json();
    }

    public function courierPhoneNumber($claim_id)
    {
        $body = '{
            "claim_id": "' . $claim_id . '"
        }';

        $res = Http::withHeaders($this->headers)->post('https://b2b.taxi.yandex.net/b2b/cargo/integration/v2/driver-voiceforwarding', json_decode($body, true));

        return $res->json();
    }

    public function courierLocation($claim_id)
    {
        $res = Http::withHeaders($this->headers)->get('https://b2b.taxi.yandex.net/b2b/cargo/integration/v2/claims/performer-position', ["claim_id" => $claim_id]);

        return $res->json();
    }
}
