# Material / Space — marketplace conventions

Laravel 13 / PHP 8.3+ marketplace: client catalog, shop panel and admin console.
No Node.js, npm, Vue, React, Tailwind build or Vite. Front-end assets are plain CSS
and plain browser JavaScript in `public/assets`, loaded with `asset()`.

## Commands

- `php artisan serve` — web server
- `php artisan queue:listen` — queue worker
- `php artisan migrate` — never `migrate:fresh` against real data
- `php artisan test` — PHPUnit feature/unit tests (SQLite in-memory)
- `php artisan admin:create` — provision an administrator interactively

## Rules that are easy to get wrong

- **Money** is stored as integer paise (`price_paise`, `offer_price_paise`). Convert with
  `App\Support\Money`, never with floating point. Price filters sort on
  `COALESCE(offer_price_paise, price_paise)`.
- **Shop names** are unique on a normalized `name_key` (`App\Support\NameNormalizer`).
  Concurrent duplicates must return a field conflict, not a 500.
- **Products** store `subcategory_id` only; category is derived through the parent.
  Changing category must clear an incompatible subcategory.
- **Visibility** is always: published product + active shop + active subcategory + active
  category. Re-check status on every protected shop request, never only at login.
- **Ownership** comes from the authenticated identity, never from a posted `shop_id`.
- **Two guards**: `web` (users, OTP) and `admin` (admins, password). Customer OTP must never
  grant admin access. API uses Sanctum tokens with abilities.
- **Chat** sends are authenticated HTTP POSTs, idempotent via `client_message_id`.
  Never insert message content with `innerHTML`.
- Migrations pin the MySQL engine to InnoDB in `config/database.php`.
