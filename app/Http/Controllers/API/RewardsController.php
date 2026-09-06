<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\RewardDealException;
use App\Support\RewardDealService;
use App\Support\RewardQrToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RewardsController extends Controller
{
    public function __construct(private readonly RewardDealService $deals)
    {
    }

    public function myCode(Request $request): JsonResponse
    {
        $user = Auth::user();
        $issued = RewardQrToken::issue((string) $user->id);

        return response()->json([
            'status' => true,
            'payload' => $issued['payload'],
            'expires_in' => $issued['expires_in'],
            'eligible' => true,
        ]);
    }

    public function currentDeal(Request $request): JsonResponse
    {
        try {
            $user = $this->partner();
        } catch (RewardDealException $e) {
            return $this->error($e);
        }

        $deal = $this->deals->latestDeal((string) $user->id);
        if ($deal === null) {
            return $this->fail('no_active_deal');
        }

        return response()->json([
            'status' => true,
            'deal' => $this->deals->dealArray($deal),
        ]);
    }

    public function storeDeal(Request $request): JsonResponse
    {
        $input = $this->validatedDealInput($request, titleRequired: true);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $user = $this->partner();
            $deal = $this->deals->create((string) $user->id, $input[0], $input[1]);
        } catch (RewardDealException $e) {
            return $this->error($e);
        }

        return response()->json([
            'status' => true,
            'error_code' => 'success',
            'deal' => $deal,
        ]);
    }

    public function renewDeal(Request $request): JsonResponse
    {
        $input = $this->validatedDealInput($request, titleRequired: false);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $user = $this->partner();
            $deal = $this->deals->renew((string) $user->id, $input[0], $input[1]);
        } catch (RewardDealException $e) {
            return $this->error($e);
        }

        return response()->json([
            'status' => true,
            'error_code' => 'success',
            'deal' => $deal,
        ]);
    }

    public function pauseDeal(Request $request): JsonResponse
    {
        try {
            $user = $this->partner();
            $deal = $this->deals->pause((string) $user->id);
        } catch (RewardDealException $e) {
            return $this->error($e);
        }

        return response()->json([
            'status' => true,
            'error_code' => 'success',
            'deal' => $deal,
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:2048',
        ]);
        if ($validator->fails()) {
            return $this->fail('invalid_or_expired_token');
        }

        try {
            $user = $this->partner();
            $result = $this->deals->scan((string) $user->id, (string) $request->input('token'));
        } catch (RewardDealException $e) {
            return $this->error($e);
        }

        return response()->json([
            'status' => true,
            'error_code' => 'success',
            'deal_id' => $result['deal_id'],
            'title' => $result['title'],
            'quantity_total' => $result['quantity_total'],
            'quantity_remaining' => $result['quantity_remaining'],
            'status' => $result['status'],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        try {
            $user = $this->partner();
        } catch (RewardDealException $e) {
            return $this->error($e);
        }

        $deals = array_map(
            fn ($deal) => array_merge($this->deals->dealArray($deal), [
                'redemption_count' => (int) ($deal->redemption_count ?? 0),
            ]),
            $this->deals->history((string) $user->id)
        );

        return response()->json([
            'status' => true,
            'deals' => $deals,
        ]);
    }

    private function partner(): object
    {
        $user = Auth::user();
        $this->deals->requirePartner($user);

        return $user;
    }

    /**
     * @return array{0: ?string, 1: int}|JsonResponse
     */
    private function validatedDealInput(Request $request, bool $titleRequired): array|JsonResponse
    {
        $rules = [
            'quantity' => 'required|integer|min:1|max:100000',
            'title' => ($titleRequired ? 'required' : 'nullable').'|string|max:160',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error_code' => 'validation_failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $title = trim((string) $request->input('title', ''));
        if ($titleRequired && $title === '') {
            return response()->json([
                'status' => false,
                'error_code' => 'validation_failed',
                'message' => 'title is required',
            ], 422);
        }

        return [$title !== '' ? $title : null, (int) $request->input('quantity')];
    }

    private function error(RewardDealException $e): JsonResponse
    {
        return $this->fail($e->errorCode, (int) $e->getCode() ?: 422, $e->getMessage());
    }

    private function fail(string $code, int $http = 422, ?string $message = null): JsonResponse
    {
        return response()->json([
            'status' => false,
            'error_code' => $code,
            'message' => $message && $message !== $code ? $message : $this->messageFor($code),
        ], $http);
    }

    private function messageFor(string $code): string
    {
        return match ($code) {
            'already_redeemed' => 'This client already redeemed this deal.',
            'deal_exhausted' => 'This deal has no remaining quantity.',
            'deal_paused' => 'This deal is paused.',
            'no_active_deal' => 'No deal is available.',
            'invalid_or_expired_token' => 'The QR code is invalid or expired.',
            'not_a_partner' => 'Only business accounts can manage rewards.',
            'cannot_redeem_own_qr' => 'You cannot redeem your own QR code.',
            'active_deal_exists' => 'An active deal already exists.',
            'partner_blocked' => 'Rewards are blocked for this partner.',
            default => 'Request failed.',
        };
    }
}
