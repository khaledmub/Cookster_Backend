<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsernameService
{
    public static function normalize(?string $username): ?string
    {
        if ($username === null) {
            return null;
        }

        return strtolower(trim($username));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(?string $ignoreUserId = null, bool $required = true): array
    {
        $unique = Rule::unique('front_users', 'user_name');
        if ($ignoreUserId !== null) {
            $unique->ignore($ignoreUserId);
        }

        $rules = [
            'string',
            'min:3',
            'max:30',
            'regex:/^[a-z0-9_]+$/',
            $unique,
        ];

        array_unshift($rules, $required ? 'required' : 'sometimes');

        return ['user_name' => $rules];
    }

    /**
     * @return array<string, string>
     */
    public static function customMessages(): array
    {
        return [
            'user_name.required' => 'Username is required.',
            'user_name.regex' => 'Username may only contain lowercase letters, numbers, and underscores.',
            'user_name.min' => 'Username must be at least 3 characters.',
            'user_name.max' => 'Username must not exceed 30 characters.',
            'user_name.unique' => 'This username is already taken.',
        ];
    }

    public static function isAvailable(string $username, ?string $ignoreUserId = null): bool
    {
        $normalized = self::normalize($username);
        if ($normalized === null || $normalized === '') {
            return false;
        }

        $query = DB::table('front_users')->where('user_name', $normalized);
        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return ! $query->exists();
    }

    public static function generateFromSeed(string $seed, ?string $excludeUserId = null): string
    {
        $base = self::slugifySeed($seed);
        if (strlen($base) < 3) {
            $base = 'user_'.str_pad($base, 2, '0', STR_PAD_LEFT);
        }
        $base = substr($base, 0, 26);

        $candidate = $base;
        $suffix = 0;
        while (! self::isAvailable($candidate, $excludeUserId)) {
            $suffix++;
            $candidate = substr($base, 0, max(1, 30 - strlen((string) $suffix) - 1)).'_'.$suffix;
        }

        return $candidate;
    }

    private static function slugifySeed(string $seed): string
    {
        $s = strtolower(trim($seed));
        $s = preg_replace('/[^a-z0-9_]+/', '_', $s) ?? '';
        $s = trim($s, '_');
        $s = preg_replace('/_+/', '_', $s) ?? '';

        return $s;
    }

    public static function backfillForUser(object $user): string
    {
        $seed = (string) ($user->name ?? '');
        if ($seed === '') {
            $seed = Str::before((string) ($user->email ?? ''), '@');
        }
        if ($seed === '') {
            $seed = 'user';
        }

        return self::generateFromSeed($seed, (string) $user->id);
    }
}
