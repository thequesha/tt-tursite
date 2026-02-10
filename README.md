# Daily Grow — Yandex Maps Reviews Integration

Laravel 10 + Vue 3 SPA application that integrates with Yandex Maps to display business reviews and ratings.

## Tech Stack

- **Backend:** Laravel 10 (PHP 8.1), Laravel Sanctum
- **Frontend:** Vue 3, Vue Router 4, Pinia, Tailwind CSS 3
- **Build:** Vite 4
- **Database:** MySQL

## Features

- Login/logout with session-based authentication (Sanctum SPA)
- Settings page — save a Yandex Maps business URL
- Reviews page — live-scrape and display reviews from the saved URL
- Overall rating and total review count widget
- SOLID/DRY architecture: interfaces, service layer, repository pattern

## Requirements

- PHP 8.1+
- Composer
- Node.js 16+
- MySQL 5.7+

## Installation

```bash
# 1. Clone the repository
git clone https://github.com/YOUR_USERNAME/tt-tursite.git
cd tt-tursite

# 2. Install PHP dependencies
composer install

# 3. Install JS dependencies
npm install

# 4. Copy environment config
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Configure database in .env
# DB_DATABASE=tt_tursite
# DB_USERNAME=root
# DB_PASSWORD=

# 7. Create the database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS tt_tursite"

# 8. Run migrations and seed
php artisan migrate --seed

# 9. Build frontend assets
npm run build

# 10. Start the server
php artisan serve
```

## Development

```bash
# Run Vite dev server (hot reload)
npm run dev

# Run Laravel server
php artisan serve
```

Open http://localhost:8000 in the browser.

## Default Credentials

| Field    | Value              |
|----------|--------------------|
| Email    | admin@example.com  |
| Password | password           |

## Usage

1. Log in with the credentials above
2. Go to **Настройки** (Settings)
3. Paste a Yandex Maps reviews URL, e.g.: `https://yandex.ru/maps/org/company_name/1234567890/reviews/`
4. Click **Сохранить** (Save)
5. Go to **Отзывы** (Reviews) to see the scraped reviews and rating

## Architecture

```
app/
├── Http/Controllers/Api/     # AuthController, SettingsController, ReviewController
├── Http/Requests/            # LoginRequest, SaveSettingsRequest
├── Models/                   # User, Setting
├── Repositories/             # SettingRepository (interface + implementation)
├── Services/                 # YandexReviewService (interface + implementation)
└── Providers/                # AppServiceProvider (DI bindings)

resources/js/
├── router/                   # Vue Router configuration
├── stores/                   # Pinia stores (auth, settings)
├── layouts/                  # AuthLayout, DashboardLayout
├── pages/                    # LoginPage, ReviewsPage, SettingsPage
└── components/               # AppSidebar, ReviewCard, RatingWidget, StarRating
```

## License

MIT
