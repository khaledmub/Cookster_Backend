<?php

namespace App\Support;

class PublicUserProfile
{
    /** @var list<string> */
    private const PRIVATE_FIELDS = [
        'password',
        'email',
        'phone',
        'uuid',
        'sd_email',
        'email_verified_at',
        'current_subscription_id',
        'total_loyalty_points',
        'total_outstanding_balance',
        'is_one_time_discount_given',
        'is_soft_delete',
        'remember_token',
        'system_id',
    ];

    /**
     * @return list<string>
     */
    public static function selectColumns(string $tableAlias): array
    {
        return [
            "{$tableAlias}.id",
            "{$tableAlias}.name",
            "{$tableAlias}.user_name",
            "{$tableAlias}.image",
            "{$tableAlias}.entity",
        ];
    }

    public static function sanitize(object $user): object
    {
        foreach (self::PRIVATE_FIELDS as $field) {
            unset($user->$field);
        }

        return $user;
    }

    public static function sanitizeIterable($users): void
    {
        if ($users instanceof \Illuminate\Support\Collection) {
            $users->transform(fn ($user) => is_object($user) ? self::sanitize($user) : $user);

            return;
        }

        if (is_array($users)) {
            foreach ($users as $key => $user) {
                if (is_object($user)) {
                    $users[$key] = self::sanitize($user);
                }
            }
        }
    }

    /**
     * Case-insensitive partial match on display name and unique username.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function applyNameOrUsernameSearch($query, string $keywords, string $tableAlias = ''): void
    {
        $prefix = $tableAlias !== '' ? $tableAlias.'.' : '';
        $like = '%'.strtolower($keywords).'%';

        $query->where(function ($q) use ($prefix, $like) {
            $q->whereRaw('LOWER('.$prefix.'name) LIKE ?', [$like])
                ->orWhereRaw('LOWER('.$prefix.'user_name) LIKE ?', [$like]);
        });
    }
}
