<?php

namespace App\Support;

use App\Helpers\AppHelper;
use App\Models\FrontUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrationService
{
    public const OTP_TTL_MINUTES = 30;

    public const OTP_MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const RESEND_HOURLY_LIMIT = 5;

    public const PENDING_EXPIRY_HOURS = 72;

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function baseValidationRules(?string $ignoreUserId = null): array
    {
        return [
            'name' => ['required'],
            'email' => [
                'required',
                'email',
                self::activeUniqueRule('email', $ignoreUserId),
            ],
            'phone' => [
                'nullable',
                self::activeUniqueRule('phone', $ignoreUserId),
            ],
            'password' => ['required', 'string'],
            'entity' => ['required'],
            'uuid' => ['required'],
        ];
    }

    public static function activeUniqueRule(string $column, ?string $ignoreUserId = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('front_users', $column)
            ->where(fn ($query) => $query
                ->where('registration_status', RegistrationStatus::ACTIVE)
                ->where('is_soft_delete', 0));

        if ($ignoreUserId !== null) {
            $rule->ignore($ignoreUserId);
        }

        return $rule;
    }

    public static function isUsernameAvailable(string $username, ?string $ignoreUserId = null): bool
    {
        $normalized = UsernameService::normalize($username);
        if ($normalized === null || $normalized === '') {
            return false;
        }

        $query = DB::table('front_users')
            ->where('user_name', $normalized)
            ->where('registration_status', RegistrationStatus::ACTIVE)
            ->where('is_soft_delete', 0);

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return ! $query->exists();
    }

    public static function usernameAvailabilityReason(string $username, ?string $ignoreUserId = null): string
    {
        $normalized = UsernameService::normalize($username);
        if ($normalized === null || $normalized === '') {
            return 'format';
        }

        if (self::isUsernameAvailable($normalized, $ignoreUserId)) {
            if (self::findPendingByUsername($normalized, $ignoreUserId) !== null) {
                return 'pending_resume_allowed';
            }

            return 'available';
        }

        return 'taken';
    }

    public static function findPendingByEmail(string $email, ?string $ignoreUserId = null): ?object
    {
        $query = DB::table('front_users')
            ->where('email', $email)
            ->where('registration_status', RegistrationStatus::PENDING_VERIFICATION)
            ->where('is_soft_delete', 0);

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->first();
    }

    public static function findPendingByUsername(string $username, ?string $ignoreUserId = null): ?object
    {
        $normalized = UsernameService::normalize($username);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        $query = DB::table('front_users')
            ->where('user_name', $normalized)
            ->where('registration_status', RegistrationStatus::PENDING_VERIFICATION)
            ->where('is_soft_delete', 0);

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->first();
    }

    public static function findActiveByEmail(string $email): ?object
    {
        return DB::table('front_users')
            ->where('email', $email)
            ->where('registration_status', RegistrationStatus::ACTIVE)
            ->where('is_soft_delete', 0)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function resumePendingUser(object $user, array $input, Request $request): FrontUser
    {
        $updates = [
            'name' => $input['name'],
            'user_name' => UsernameService::normalize($input['user_name']),
            'password' => Hash::make($input['password']),
            'phone' => $input['phone'] ?? null,
            'dob' => isset($input['dob']) ? date('Y-m-d', strtotime((string) $input['dob'])) : null,
            'country' => $input['country'] ?? 0,
            'city' => $input['city'] ?? 0,
            'uuid' => $input['uuid'],
            'entity' => $input['entity'],
            'registration_status' => RegistrationStatus::PENDING_VERIFICATION,
            'status' => 1,
        ];

        DB::table('front_users')->where('id', $user->id)->update($updates);
        self::syncEntityAdditionalData((string) $user->id, $request);

        return FrontUser::findOrFail($user->id);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function createPendingUser(array $input, Request $request): FrontUser
    {
        $frontUserData = [
            'id' => (string) Str::uuid(),
            'name' => $input['name'],
            'user_name' => UsernameService::normalize($input['user_name']),
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'password' => Hash::make($input['password']),
            'dob' => isset($input['dob']) ? date('Y-m-d', strtotime((string) $input['dob'])) : null,
            'country' => $input['country'] ?? 0,
            'state' => 0,
            'city' => $input['city'] ?? 0,
            'uuid' => $input['uuid'],
            'entity' => $input['entity'],
            'registration_status' => RegistrationStatus::PENDING_VERIFICATION,
            'status' => 1,
        ];

        $settings = DB::table('settings')->where('id', 1)->first();
        if ($settings && $settings->allow_one_time_qr_reward == 1 && (int) $request->input('entity') === 1) {
            $frontUserData['is_one_time_discount_given'] = 0;
        }

        $user = FrontUser::create($frontUserData);
        self::syncEntityAdditionalData((string) $user->id, $request);

        return $user;
    }

    public static function syncEntityAdditionalData(string $userId, Request $request): void
    {
        $entity = (int) $request->input('entity');

        if ($entity === 1) {
            DB::table('personal_account_additional_data')->updateOrInsert(
                ['front_user_id' => $userId],
                ['website' => $request->input('website')]
            );
        }

        if ($entity === 2) {
            DB::table('business_account_additional_data')->updateOrInsert(
                ['front_user_id' => $userId],
                [
                    'business_type' => $request->input('business_type'),
                    'contact_phone' => $request->input('contact_phone'),
                    'contact_email' => $request->input('contact_email'),
                    'website' => $request->input('website'),
                    'location' => $request->input('location'),
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                ]
            );
        }

        if ($entity === 3) {
            DB::table('chef_account_additional_data')->updateOrInsert(
                ['front_user_id' => $userId],
                [
                    'country' => 194,
                    'state' => $request->input('state'),
                    'city' => 0,
                    'contact_phone' => $request->input('contact_phone'),
                    'contact_email' => $request->input('contact_email'),
                ]
            );
        }

        if ($entity === 8) {
            DB::table('sponsored_account_additional_data')->updateOrInsert(
                ['front_user_id' => $userId],
                ['type_of_account' => $request->input('type_of_account')]
            );
        }
    }

    /**
     * @return array{
     *     success: bool,
     *     otp_sent: bool,
     *     otp_delivery: string,
     *     verification_code: ?string,
     *     error_message: ?string
     * }
     */
    public static function sendRegistrationOtp(object $user): array
    {
        $dispatch = AppHelper::send_verification_code(1, $user, 'register');
        $otpSent = (bool) ($dispatch['success'] ?? false);
        $delivery = (string) ($dispatch['dispatch_mode'] ?? 'sync');

        if ($otpSent) {
            $code = (string) ($dispatch['verification_code'] ?? '');
            if ($code !== '') {
                self::storeRegistrationOtp((string) $user->id, $code, $delivery);
            }

            Cache::put(self::resendHourlyKey((string) $user->id), (int) Cache::get(self::resendHourlyKey((string) $user->id), 0) + 1, now()->addHour());
            Cache::put(self::resendCooldownKey((string) $user->id), 1, now()->addSeconds(self::RESEND_COOLDOWN_SECONDS));
        }

        if (! $otpSent) {
            $delivery = 'failed';
        } elseif ($delivery === 'queued') {
            $delivery = 'queued';
        } else {
            $delivery = 'sent';
        }

        return [
            'success' => $otpSent,
            'otp_sent' => $otpSent,
            'otp_delivery' => $delivery,
            'verification_code' => $dispatch['verification_code'] ?? null,
            'error_message' => $dispatch['error_message'] ?? null,
        ];
    }

    public static function storeRegistrationOtp(string $userId, string $code, string $deliveryMode): void
    {
        $now = Carbon::now();

        DB::table('verification_codes')
            ->where('medium', 1)
            ->where('front_user_id', $userId)
            ->where('purpose', 'register')
            ->delete();

        $payload = [
            'medium' => 1,
            'front_user_id' => $userId,
            'code' => $code,
            'purpose' => 'register',
            'expires_at' => $now->copy()->addMinutes(self::OTP_TTL_MINUTES),
            'attempts' => 0,
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('verification_codes', 'status')) {
            $payload['status'] = 1;
        }

        DB::table('verification_codes')->insert($payload);
    }

    /**
     * @return array{ok: bool, message: string, status: int, user?: FrontUser, token?: string}
     */
    public static function verifyRegistrationOtp(string $userId, string $code): array
    {
        $user = FrontUser::find($userId);
        if ($user === null || (int) $user->is_soft_delete === 1) {
            return [
                'ok' => false,
                'message' => __('messages.user_not_found'),
                'status' => 404,
            ];
        }

        if (RegistrationStatus::isActive($user->registration_status ?? RegistrationStatus::ACTIVE)) {
            return [
                'ok' => false,
                'message' => __('messages.email_already_verified'),
                'status' => 400,
            ];
        }

        $otpRow = DB::table('verification_codes')
            ->where('medium', 1)
            ->where('front_user_id', $userId)
            ->where('purpose', 'register')
            ->first();

        if ($otpRow === null) {
            return [
                'ok' => false,
                'message' => __('messages.otp_expired'),
                'status' => 422,
            ];
        }

        if ($otpRow->expires_at !== null && Carbon::parse($otpRow->expires_at)->isPast()) {
            DB::table('verification_codes')->where('id', $otpRow->id)->delete();

            return [
                'ok' => false,
                'message' => __('messages.otp_expired'),
                'status' => 422,
            ];
        }

        if ((int) ($otpRow->attempts ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            return [
                'ok' => false,
                'message' => __('messages.otp_invalid'),
                'status' => 422,
            ];
        }

        if (! hash_equals((string) $otpRow->code, $code)) {
            DB::table('verification_codes')
                ->where('id', $otpRow->id)
                ->update([
                    'attempts' => (int) ($otpRow->attempts ?? 0) + 1,
                    'updated_at' => Carbon::now(),
                ]);

            return [
                'ok' => false,
                'message' => __('messages.otp_invalid'),
                'status' => 422,
            ];
        }

        $now = Carbon::now();
        DB::table('verification_codes')->where('id', $otpRow->id)->delete();

        $user->registration_status = RegistrationStatus::ACTIVE;
        $user->email_verified_at = $now;
        $user->otp_verified_at = $now;
        $user->save();

        $token = $user->createToken('FrontUserToken')->plainTextToken;

        return [
            'ok' => true,
            'message' => __('messages.email_verified_successfully', ['default' => 'Email verified successfully.']),
            'status' => 200,
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @return array{ok: bool, message: string, status: int, otp?: array<string, mixed>}
     */
    public static function resendRegistrationOtp(object $user): array
    {
        if (! RegistrationStatus::isPending($user->registration_status ?? null)) {
            return [
                'ok' => false,
                'message' => __('messages.email_already_verified'),
                'status' => 400,
            ];
        }

        $userId = (string) $user->id;
        if (Cache::has(self::resendCooldownKey($userId))) {
            return [
                'ok' => false,
                'message' => __('messages.otp_resend_rate_limited'),
                'status' => 429,
            ];
        }

        $hourlyCount = (int) Cache::get(self::resendHourlyKey($userId), 0);
        if ($hourlyCount >= self::RESEND_HOURLY_LIMIT) {
            return [
                'ok' => false,
                'message' => __('messages.otp_resend_rate_limited'),
                'status' => 429,
            ];
        }

        $otp = self::sendRegistrationOtp($user);
        Cache::put(self::resendCooldownKey($userId), 1, now()->addSeconds(self::RESEND_COOLDOWN_SECONDS));
        Cache::put(self::resendHourlyKey($userId), $hourlyCount + 1, now()->addHour());

        if (! $otp['otp_sent']) {
            return [
                'ok' => false,
                'message' => __('messages.otp_send_failed'),
                'status' => 500,
                'otp' => $otp,
            ];
        }

        return [
            'ok' => true,
            'message' => __('messages.registration_otp_resent'),
            'status' => 200,
            'otp' => $otp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function registrationUserPayload(object $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_name' => $user->user_name ?? null,
            'status' => $user->registration_status ?? RegistrationStatus::ACTIVE,
            'email_verified' => $user->email_verified_at !== null,
            'entity' => $user->entity ?? null,
        ];
    }

    /**
     * @return array{otp_sent: bool, otp_delivery: string, message: string}
     */
    public static function otpResponseMeta(array $otp): array
    {
        return [
            'otp_sent' => (bool) ($otp['otp_sent'] ?? false),
            'otp_delivery' => (string) ($otp['otp_delivery'] ?? 'failed'),
            'message' => ($otp['otp_sent'] ?? false)
                ? __('messages.registration_otp_sent')
                : __('messages.otp_send_failed'),
        ];
    }

    public static function expireStalePendingRegistrations(): int
    {
        $cutoff = Carbon::now()->subHours(self::PENDING_EXPIRY_HOURS);

        $stale = DB::table('front_users')
            ->where('registration_status', RegistrationStatus::PENDING_VERIFICATION)
            ->where('is_soft_delete', 0)
            ->where('created_at', '<', $cutoff)
            ->get(['id', 'email', 'user_name']);

        foreach ($stale as $user) {
            DB::table('verification_codes')
                ->where('front_user_id', $user->id)
                ->delete();

            DB::table('front_users')->where('id', $user->id)->update([
                'registration_status' => RegistrationStatus::EXPIRED,
                'is_soft_delete' => 1,
                'email' => 'expired.'.$user->id.'.'.Str::slug((string) $user->email).'@invalid.cookster.local',
                'user_name' => 'expired_'.substr((string) $user->id, 0, 8),
                'updated_at' => Carbon::now(),
            ]);
        }

        return $stale->count();
    }

    private static function resendCooldownKey(string $userId): string
    {
        return 'registration:otp:cooldown:'.$userId;
    }

    private static function resendHourlyKey(string $userId): string
    {
        return 'registration:otp:hourly:'.$userId;
    }
}
