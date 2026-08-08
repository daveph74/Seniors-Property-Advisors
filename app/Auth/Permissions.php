<?php

namespace App\Auth;

use App\Models\User;

/**
 * Scope section 2, expressed once. Every gate, route and sidebar item reads from
 * these two maps, so a role's reach can be audited in one place.
 *
 * Client administrators create, edit, publish and unpublish content. Only super
 * administrators delete content, restore archived content, manage CMS users and
 * reach system settings.
 */
class Permissions
{
    public const ABILITIES = [
        'content.manage' => [User::SUPER_ADMIN, User::CLIENT_ADMIN],
        'content.delete' => [User::SUPER_ADMIN],
        'media.upload_svg' => [User::SUPER_ADMIN],
        'content.restore' => [User::SUPER_ADMIN],
        'users.manage' => [User::SUPER_ADMIN],
        'settings.manage' => [User::SUPER_ADMIN],
    ];

    public const MODULES = [
        'dashboard' => 'content.manage',
        'pages' => 'content.manage',
        'blog' => 'content.manage',
        'faqs' => 'content.manage',
        'testimonials' => 'content.manage',
        'enquiries' => 'content.manage',
        'media' => 'content.manage',
        'activity' => 'content.manage',
        'deleted' => 'content.restore',
        'navigation' => 'content.manage',
        'global' => 'content.manage',
        'users' => 'users.manage',
        'settings' => 'settings.manage',
    ];

    public static function allows(?User $user, string $ability): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }

        return in_array($user->role, self::ABILITIES[$ability] ?? [], true);
    }

    public static function abilities(?User $user): array
    {
        $abilities = [];

        foreach (array_keys(self::ABILITIES) as $ability) {
            $abilities[$ability] = self::allows($user, $ability);
        }

        return $abilities;
    }

    public static function modules(?User $user): array
    {
        return array_map(
            fn (string $ability) => self::allows($user, $ability),
            self::MODULES,
        );
    }
}
