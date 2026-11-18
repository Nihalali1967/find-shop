<?php

namespace App\Support\ApiDocs;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Groups the API docs (/docs/api) into role-based sections and marks every
 * endpoint as either Public (no token) or Token required.
 */
class ApiDocSections
{
    private const SUPER_ADMIN = '1. Super Admin';

    private const COMPANY_ADMIN = '2. Company Admin (Shop)';

    private const USER = '3. User (Client)';

    private const COMMON = '4. Common (User & Company Admin)';

    private const PUBLIC = 'Public (No Token)';

    private const PROTECTED = 'Token Required';

    private const SECTIONS = [
        self::SUPER_ADMIN => [
            'about' => 'Platform super admin endpoints.',
            'token' => 'Super admin token from `POST /v1/admin/auth/login`.',
        ],
        self::COMPANY_ADMIN => [
            'about' => 'Shop owner endpoints: onboarding, profile, products and shop inbox.',
            'token' => 'Token from `POST /v1/auth/otp/verify` with `purpose = shop_login`. Most endpoints also require the shop to be active.',
        ],
        self::USER => [
            'about' => 'Client endpoints: browse the catalog and start conversations with shops.',
            'token' => 'Token from `POST /v1/auth/otp/verify` with `purpose = client_login`.',
        ],
        self::COMMON => [
            'about' => 'Endpoints shared by users and company admins: OTP login, profile, chat messages and notifications.',
            'token' => 'User or company admin token from `POST /v1/auth/otp/verify`.',
        ],
    ];

    /** Tags each operation with "<Section> — Public (No Token)" or "<Section> — Token Required". */
    public static function operation(Operation $operation, RouteInfo $routeInfo): void
    {
        $route = $routeInfo->route;
        $section = self::sectionFor($route->uri());
        $isPublic = ! collect($route->gatherMiddleware())
            ->contains(fn (string $m) => Str::is(['auth', 'auth:*'], $m));

        $operation->setTags([self::tagName($section, $isPublic)]);

        $auth = $isPublic
            ? '🔓 **Public** — no token required.'
            : '🔒 **Token required** — send `Authorization: Bearer <token>`. '.self::SECTIONS[$section]['token'];

        $operation->description(trim($auth."\n\n".$operation->description));
    }

    /** Declares the tags in display order, with descriptions. */
    public static function document(OpenApi $openApi): void
    {
        $openApi->tags = [];

        foreach (self::SECTIONS as $section => $info) {
            $openApi->tags[] = new Tag(
                self::tagName($section, true),
                $info['about'].' These endpoints are public and need **no token**.',
            );
            $openApi->tags[] = new Tag(
                self::tagName($section, false),
                $info['about'].' These endpoints need a **Bearer token**: '.$info['token'],
            );
        }
    }

    private static function sectionFor(string $uri): string
    {
        $path = Str::after($uri, 'api/v1/');

        return match (true) {
            Str::startsWith($path, 'admin') => self::SUPER_ADMIN,
            Str::startsWith($path, 'shop/') => self::COMPANY_ADMIN,
            Str::startsWith($path, ['client/', 'categories', 'colors', 'units', 'products', 'shops']) => self::USER,
            default => self::COMMON,
        };
    }

    private static function tagName(string $section, bool $isPublic): string
    {
        return $section.' — '.($isPublic ? self::PUBLIC : self::PROTECTED);
    }
}
