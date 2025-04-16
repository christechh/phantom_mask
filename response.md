# Phantom Mask

使用 PHP Laravel framework 來演示。

## Prerequisites

- PHP 8.2.27
- Laravel 12.0
- MariaDB 11.6.2

## Getting Started
1. Clone the Repository
2. Install Dependencies: `composer install`
3. Database Configuration `cp .env.example .env`
4. Edit the `.env`
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

5. Run Migrations: `php artisan migrate`
6. Import Data: `php artisan data:import`
7. Start the Application: `php artisan serve`

---

## 路由簡易說明

詳細見 Postman

1. 指定日期有開店的藥局 `GET /pharmacies/open`
2. 指定藥局的口罩 `GET /api/pharmacies/{id}/masks`
3. 價格範圍內，口罩產品數量多於或少於 x 種的所有藥局 `GET /api/pharmacies/masks/count`
4. 日期範圍內，用戶購買口罩總金額排名 `GET /api/top-members`
5. 日期範圍內，口罩交易金額 `GET /api/masks/summary`
6. 關鍵字搜尋藥局或口罩資料，並依與搜尋項目的相關性排序 `GET /api/search`
7. 用戶向藥局購買口罩 `POST /api/purchase`
