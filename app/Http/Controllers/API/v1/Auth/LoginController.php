<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Http\Requests\Auth\PhoneVerifyRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ProvideLoginRequest;
use App\Http\Requests\Auth\ReSendVerifyRequest;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\UserResource;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserByCode;
use App\Services\AuthService\AuthByMobilePhone;
use App\Services\EmailSettingService\EmailSendService;
use App\Services\UserServices\UserWalletService;
use App\Traits\ApiResponse;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LoginController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        if ($request->input('phone')) {
            return $this->loginByPhone($request);
        }

        if (!auth()->attempt($request->only(['email', 'password']))) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_102,
                'message' => __('errors.' . ResponseError::ERROR_102, locale: $this->language)
            ]);
        }

        $token = auth()->user()->createToken('api_token')->plainTextToken;

        return $this->successResponse('User successfully login', [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make(auth('sanctum')->user()->loadMissing(['shop', 'model'])),
        ]);
    }

    protected function loginByPhone($request): JsonResponse
    {
        if (!auth()->attempt($request->only('phone', 'password'))) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_102,
                'message' => __('errors.' . ResponseError::ERROR_102, locale: $this->language)
            ]);
        }

        $token = auth()->user()->createToken('api_token')->plainTextToken;

        return $this->successResponse('User successfully login', [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make(auth('sanctum')->user()->loadMissing(['shop', 'model'])),
        ]);

    }

    /**
     * Redirect get code
     * @param $isMobileOrWeb
     * @return Application|JsonResponse|Redirector|RedirectResponse
     */
    public function redirectToProId($isMobileOrWeb): JsonResponse|Redirector|Application|RedirectResponse
    {
        $client_id_map = [
            'web_client' => '9',
            'web_admin' => '11',
            'web_qr' => '14',
            'mobile_client' => '8',
            'mobile_partner' => '10',
        ];

        $client_id = $client_id_map[$isMobileOrWeb] ?? '';
        $callbackUri = "https://api.cheeff.uz/api/v1/oauth/proid/$isMobileOrWeb/callback";

        if (!empty($client_id) && !empty($callbackUri)) {
            $query = http_build_query([
                'client_id' => $client_id,
                'redirect_uri' => $callbackUri,
                'response_type' => 'code',
                'scope' => '',
                'state' => 'abcdabcdabcdabcdabcd',
                'prompt' => 'consent',
            ]);
            return redirect('https://id.in-pro.net/oauth/authorize?' . $query);
        } else {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_404,
                'message' => __('errors client does not have permission')
            ]);
        }
    }


    /**
     * Change code to access_token and get token and update or create user data
     * @param $isMobileOrWeb
     * @param ProvideLoginRequest $request
     * @return Application|JsonResponse|RedirectResponse|Redirector
     */
    public function handleProIdCallback($isMobileOrWeb, Request $request): JsonResponse|Redirector|Application|RedirectResponse
    {
        $clientData = [
            'web_client' => [
                'client_id' => 9,
                'client_secret' => 'l5T1qJCYCWXvyuKaB2picQ4b3DnigH2LWkN1QJDw',
            ],
            'web_admin' => [
                'client_id' => 11,
                'client_secret' => 'EQQ3l7RZGlZjFYPrjlWnI0ao9DyY82UfUMRjECih',
            ],
            'web_qr' => [
                'client_id' => 14,
                'client_secret' => 'Eb7mrsFPUcfSGVEeGICQpkgV6BRRBPmrePP9edzv',
            ],
            'mobile_client' => [
                'client_id' => 8,
                'client_secret' => 'wZr97TvktXjd83CBW3bjWF49RNLdXI7O0BxzTn6O',
                'package_name' => 'com.procconnect.cheeff.customer',
                'callback' => 'callback'
            ],
            'mobile_partner' => [
                'client_id' => 10,
                'client_secret' => '52Te7ttrt5wZh8JAdDhLAwZGKmWKid9CgXitISJ4',
                'package_name' => 'com.proconnect.cheeff.partneir',
                'callback' => 'callbackpartner'
            ],
        ];

        $clientSecret = $clientData[$isMobileOrWeb]['client_secret'];
        $clientId = $clientData[$isMobileOrWeb]['client_id'];
        $callbackUri = "https://api.cheeff.uz/api/v1/oauth/proid/$isMobileOrWeb/callback";

        //getting user data
        $response = Http::asForm()->post('https://id.in-pro.net/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $callbackUri,
            'code' => $request->code,
        ]);

        // Decode the JSON response into an associative array
        $responseData = $response->json();

        // Access the access token from the decoded response
        $accessToken = $responseData['access_token'];

        //fetching user data from proid
        $userRes = Http::withToken($accessToken)->get('https://id.in-pro.net/api/user');

        //setting user data
        $userData = $userRes->json();

        $provider = 'proid';

        $userRole = '';
        try {
            $result = DB::transaction(function () use ($userData, $provider) {

                $firstname = $userData['name'];
                $lastname = $userData['surname'];

                $user = User::updateOrCreate(['phone' => $userData['phone_number']], [
                    'email' => $userData['email'],
                    'gender' => $userData['gender'],
                    'phone' => $userData['phone_number'],
                    'email_verified_at' => now(),
                    //'referral' => $request['referral'],
                    'active' => true,
                    'firstname' => !empty($firstname) ? $firstname : $userData['email'],
                    'lastname' => $lastname,
                    'deleted_at' => null,
                ]);


                if ($userData['avatar']) {
                    $user->update(['img' => $userData['avatar']]);
                }

                $user->socialProviders()->updateOrCreate([
                    'provider' => $provider,
                    'provider_id' => $userData['id'],
                ], [
                    'avatar' => $userData['avatar']
                ]);

                if (!$user->hasAnyRole(Role::query()->pluck('name')->toArray())) {
                    $user->syncRoles('user');
                }

                $ids = Notification::pluck('id')->toArray();

                if ($ids) {
                    $user->notifications()->sync($ids);
                } else {
                    $user->notifications()->forceDelete();
                }

                $user->emailSubscription()->updateOrCreate([
                    'user_id' => $user->id
                ], [
                    'active' => true
                ]);

                if (empty($user->wallet?->uuid)) {
                    $user = (new UserWalletService)->create($user);
                }

                return [
                    'token' => $user->createToken('api_token')->plainTextToken,
                    'user' => UserResource::make($user),
                ];
            });

            // User data
            $responseData = [
                'access_token' => data_get($result, 'token'),
                'token_type' => 'Bearer',
                'user' => data_get($result, 'user'),
            ];

            $serializedData = urlencode(json_encode($responseData));

            if (isset($clientData[$isMobileOrWeb]['package_name'])) {
                $package = $clientData[$isMobileOrWeb]['package_name'];
                $callbackMobileUrl = $clientData[$isMobileOrWeb]['callback'];

                $isIOS = strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strpos($_SERVER['HTTP_USER_AGENT'], 'iPad');
            }

            if ($isMobileOrWeb == "mobile_client") {
                if ($isIOS) {
                    $redirectUrl = "callback://callback?data=$serializedData";
                } else {
                    $redirectUrl = "intent://callback?data=$serializedData#Intent;scheme=$callbackMobileUrl;package=$package;end";
                }

                return redirect()->away($redirectUrl);
            } else if ($isMobileOrWeb == "mobile_partner") {
                if ($isIOS) {
                    $redirectUrl = "callbackpartner://callback?data=$serializedData";
                } else {
                    $redirectUrl = "intent://callback?data=$serializedData#Intent;scheme=$callbackMobileUrl;package=$package;end";
                }

                return redirect()->away($redirectUrl);
            } else if ($isMobileOrWeb == "web_client") {
                $code = rand(100000, 999999);

                UserByCode::query()->create([
                    'user' => json_encode($responseData),
                    'code' => $code
                ]);

                return redirect()->to('https://cheeff.uz?code=' . $code);
            } else if ($isMobileOrWeb == "web_admin") {
                $code = rand(100000, 999999);

                UserByCode::query()->create([
                    'user' => json_encode($responseData),
                    'code' => $code
                ]);

                return redirect()->to('https://admin.cheeff.uz?code=' . $code);

            } else if ($isMobileOrWeb == "web_qr") {
                $code = rand(100000, 999999);

                UserByCode::query()->create([
                    'user' => json_encode($responseData),
                    'code' => $code
                ]);

                return redirect()->to('https://qr.cheeff.uz?code=' . $code . '&role=' . $userRole);

            } else {
                return $this->onErrorResponse([
                    'code' => ResponseError::ERROR_404,
                    'message' => 'can not redirect'
                ]);
            }

        } catch (Throwable $e) {
            $this->error($e);
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::USER_IS_BANNED, locale: $this->language)
            ]);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function changeCodeToUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|int'
        ]);

        $userData = UserByCode::query()
            ->where('code', $validated['code'])
            ->latest()
            ->first('user');

        return new JsonResponse(['user' => json_decode($userData['user'])]);
    }

    /**
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    public function checkPhone(FilterParamsRequest $request): JsonResponse
    {
        $user = User::with('shop')
            ->where('phone', $request->input('phone'))
            ->first();

        if (!$user) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_102,
                'message' => __('errors.' . ResponseError::ERROR_102, locale: $this->language)
            ]);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->successResponse('User successfully login', [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        try {
            /** @var User $user */
            /** @var PersonalAccessToken $current */
            $user = auth('sanctum')->user();
            $firebaseToken = collect($user->firebase_token)
                ->reject(fn($item) => (string)$item == (string)request('firebase_token') || empty($item))
                ->toArray();

            $user->update([
                'firebase_token' => $firebaseToken
            ]);

            try {
                $token = str_replace('Bearer ', '', request()->header('Authorization'));

                $current = PersonalAccessToken::findToken($token);
                $current->delete();

            } catch (Throwable $e) {
                $this->error($e);
            }
        } catch (Throwable $e) {
            $this->error($e);
        }

        return $this->successResponse('User successfully logout');
    }

    /**
     * @param $idToken
     * @param $provider
     * @return JsonResponse|void
     */
    protected function validateProvider($idToken, $provider)
    {
        //        $serverKey = Settings::where('key', 'api_key')->first()?->value;
        //        $clientId  = Settings::where('key', 'client_id')->first()?->value;
        //
        //        $response  = Http::get("https://oauth2.googleapis.com/tokeninfo?id_token=$idToken");

        //        dd($response->json(), $clientId, $serverKey);

        //        $response = Http::withHeaders([
        //            'Content-Type' => 'application/x-www-form-urlencoded',
        //        ])
        //            ->post('http://your-laravel-app.com/oauth/token');

        if (!in_array($provider, ['facebook', 'github', 'google', 'apple'])) { //$response->ok()
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_107,
                'http' => Response::HTTP_UNAUTHORIZED,
                'message' => __('errors.' . ResponseError::INCORRECT_LOGIN_PROVIDER, locale: $this->language)
            ]);
        }

    }

    public function forgetPassword(ForgetPasswordRequest $request): JsonResponse
    {
        return (new AuthByMobilePhone)->authentication($request->validated());
    }

    public function forgetPasswordEmail(ReSendVerifyRequest $request): JsonResponse
    {
        $user = User::withTrashed()->where('email', $request->input('email'))->first();

        if (!$user) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ]);
        }

        $token = mb_substr(time(), -6, 6);

        Cache::put($token, $token, 900);

        $result = (new EmailSendService)->sendEmailPasswordReset($user, $token);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse('Verify code send');
    }

    public function forgetPasswordVerifyEmail(int $hash, Request $request): JsonResponse
    {
        $token = Cache::get($hash);

        if (!$token) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_215,
                'message' => __('errors.' . ResponseError::ERROR_215, locale: $this->language)
            ]);
        }

        $user = User::withTrashed()->where('email', $request->input('email'))->first();

        if (!$user) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::USER_NOT_FOUND, locale: $this->language)
            ]);
        }

        if (!$user->hasAnyRole(Role::query()->pluck('name')->toArray())) {
            $user->syncRoles('user');
        }

        $token = $user->createToken('api_token')->plainTextToken;

        $user->update([
            'active' => true,
            'deleted_at' => null
        ]);

        session()->forget([$request->input('email') . '-' . $hash]);

        return $this->successResponse('User successfully login', [
            'token' => $token,
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * @param PhoneVerifyRequest $request
     * @return JsonResponse
     */
    public function forgetPasswordVerify(PhoneVerifyRequest $request): JsonResponse
    {
        return (new AuthByMobilePhone)->forgetPasswordVerify($request->validated());
    }


}
