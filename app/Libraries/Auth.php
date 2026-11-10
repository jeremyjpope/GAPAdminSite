<?php

namespace App\Libraries;

/**
 * Session-backed current user helper.
 */
class Auth
{
    public static function user(): ?array
    {
        return session()->get('user');
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isSuper(): bool
    {
        return (bool) (self::user()['is_superadmin'] ?? false);
    }

    public static function can(string $area): bool
    {
        $u = self::user();
        if (! $u) {
            return false;
        }
        if ($u['is_superadmin']) {
            return true;
        }

        return in_array($area, $u['areas'] ?? [], true);
    }

    public static function login(array $userRow, array $areas): void
    {
        session()->regenerate();
        session()->set('user', [
            'id'            => (int) $userRow['id'],
            'fullname'      => $userRow['fullname'],
            'email'         => $userRow['email'],
            'username'      => $userRow['username'],
            'moodle_userid' => $userRow['moodle_userid'],
            'is_superadmin' => (bool) $userRow['is_superadmin'],
            'areas'         => $areas,
        ]);
    }

    public static function logout(): void
    {
        session()->destroy();
    }
}
