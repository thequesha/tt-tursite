# Daily Grow — Интеграция отзывов Яндекс Карт

SPA-приложение на Laravel 10 + Vue 3 для отображения отзывов и рейтингов организаций с Яндекс Карт.

## Стек технологий

- **Бэкенд:** Laravel 10 (PHP 8.1), Laravel Sanctum
- **Фронтенд:** Vue 3, Vue Router 4, Pinia, Tailwind CSS 3
- **Сборка:** Vite 4
- **База данных:** MySQL

## Функциональность

- Авторизация/выход через сессионную аутентификацию (Sanctum SPA)
- Страница настроек — сохранение ссылки на организацию в Яндекс Картах
- Страница отзывов — парсинг и отображение отзывов по сохранённой ссылке
- Виджет общего рейтинга и количества отзывов
- Архитектура SOLID/DRY: интерфейсы, сервисный слой, паттерн репозиторий

## Требования

- PHP 8.1+
- Composer
- Node.js 16+
- MySQL 5.7+

## Установка

```bash
# 1. Клонировать репозиторий
git clone https://github.com/YOUR_USERNAME/tt-tursite.git
cd tt-tursite

# 2. Установить PHP-зависимости
composer install

# 3. Установить JS-зависимости
npm install

# 4. Скопировать конфигурацию окружения
cp .env.example .env

# 5. Сгенерировать ключ приложения
php artisan key:generate

# 6. Настроить базу данных в .env
# DB_DATABASE=tt_tursite
# DB_USERNAME=root
# DB_PASSWORD=

# 7. Создать базу данных
mysql -u root -e "CREATE DATABASE IF NOT EXISTS tt_tursite"

# 8. Выполнить миграции и сидер
php artisan migrate --seed

# 9. Собрать фронтенд
npm run build

# 10. Запустить сервер
php artisan serve
```

## Разработка

```bash
# Запустить Vite dev-сервер (горячая перезагрузка)
npm run dev

# Запустить Laravel-сервер
php artisan serve
```

Откройте http://localhost:8000 в браузере.

## Учётные данные по умолчанию

| Поле   | Значение           |
|--------|--------------------|
| Email  | admin@example.com  |
| Пароль | password           |

## Использование

1. Авторизуйтесь с указанными выше данными
2. Перейдите в **Настройки**
3. Вставьте ссылку на отзывы Яндекс Карт, например: `https://yandex.ru/maps/org/company_name/1234567890/reviews/`
4. Нажмите **Сохранить**
5. Перейдите в **Отзывы** для просмотра спарсенных отзывов и рейтинга

## Архитектура

```
app/
├── Http/Controllers/Api/     # AuthController, SettingsController, ReviewController
├── Http/Requests/            # LoginRequest, SaveSettingsRequest
├── Models/                   # User, Setting
├── Repositories/             # SettingRepository (интерфейс + реализация)
├── Services/                 # YandexReviewService (интерфейс + реализация)
└── Providers/                # AppServiceProvider (DI-привязки)

resources/js/
├── router/                   # Конфигурация Vue Router
├── stores/                   # Pinia-хранилища (auth, settings)
├── layouts/                  # AuthLayout, DashboardLayout
├── pages/                    # LoginPage, ReviewsPage, SettingsPage
└── components/               # AppSidebar, ReviewCard, RatingWidget, StarRating
```

## Лицензия

MIT
your_secure_password
ttuser
tt_tursite 