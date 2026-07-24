<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoLocationTags
{
    /**
     * @return array{country: int, city: int}
     */
    public static function resolveFromRequest(Request $request): array
    {
        return [
            'country' => (int) ($request->input('country') ?? $request->input('country_id') ?? 0),
            'city' => (int) ($request->input('city') ?? $request->input('city_id') ?? 0),
        ];
    }

    /**
     * @return array<string, list<string>>|null
     */
    public static function validateMatch(int $countryId, int $cityId): ?array
    {
        if ($countryId <= 0 || $cityId <= 0) {
            return [
                'country' => ['Country and city are required.'],
                'city' => ['Country and city are required.'],
            ];
        }

        $city = DB::table('cities')->where('id', $cityId)->first(['id', 'country_id']);

        if ($city === null) {
            return [
                'city' => ['The selected city is invalid.'],
            ];
        }

        if ((int) $city->country_id !== $countryId) {
            return [
                'country' => ['The selected country does not match the city.'],
                'city' => ['The selected city does not belong to the selected country.'],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public static function validationErrorResponse(?array $errors): ?JsonResponse
    {
        if ($errors === null) {
            return null;
        }

        return response()->json([
            'status' => false,
            'message' => __('messages.validation_failed'),
            'errors' => $errors,
        ], 422);
    }

    public static function repairMismatchedVideos(): int
    {
        return DB::update(
            'UPDATE videos v
             INNER JOIN cities c ON c.id = v.city
             SET v.country = c.country_id, v.updated_at = ?
             WHERE v.city > 0 AND c.country_id <> v.country',
            [now()]
        );
    }
}
