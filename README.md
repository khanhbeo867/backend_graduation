# Diamond API

Backend API cho hệ thống quản lý vận hành nội bộ, tập trung vào các nhóm nghiệp vụ khách hàng, nhân sự, sự kiện, kho vật tư và lương thưởng. Repository hiện đã có nền tảng xác thực JWT, chuẩn response JSON thống nhất, middleware phân quyền theo vai trò và bộ migration cho các module cốt lõi.

## Mục tiêu hiện tại

- Dùng Laravel 12 làm nền tảng cho API nội bộ.
- Xác thực người dùng bằng JWT (`tymon/jwt-auth`).
- Chuẩn hóa response/error response cho toàn bộ API.
- Quản lý role và trạng thái người dùng bằng enum.
- Chuẩn bị schema dữ liệu cho các module nghiệp vụ chính.

Lưu ý: tại thời điểm hiện tại, API nghiệp vụ mới triển khai phần auth. Các bảng domain đã có migration nhưng chưa có đầy đủ controller/service/resource cho từng module.

## Công nghệ sử dụng

- PHP `^8.2`
- Laravel `^12.0`
- JWT Auth `tymon/jwt-auth`
- PHPUnit `^11.5`
- SQLite mặc định cho local/test
- Vite + Tailwind CSS cho phần asset mặc định của Laravel

## Các module dữ liệu đã có schema

- `users`, `password_reset_tokens`, `sessions`
- `customers`, `customer_contacts`
- `employees`, `employee_bank_accounts`
- `contracts`
- `event_categories`, `events`, `event_staff_requirements`, `event_assignments`
- `payrolls`, `payroll_details`
- `item_categories`, `items`, `inventory_transactions`, `item_maintenances`
- `event_item_requests`, `event_item_request_details`
- `notifications`

## Cấu trúc thư mục đáng chú ý

```text
app/
  Enums/                    Enum cho role, status, loại nghiệp vụ
  Http/
    Controllers/Api/        API controllers
    Middleware/             Middleware phân quyền
    Requests/               Form request validate input
  Models/                   Eloquent models
  Support/Concerns/         Trait response dùng chung

database/
  migrations/               Toàn bộ schema hệ thống
  seeders/                  Seed tài khoản mặc định
  factories/                Factory cho test

routes/
  api.php                   Định nghĩa API routes

tests/
  Feature/Api/              Feature test cho API
```

## Thiết lập môi trường local

### Yêu cầu

- PHP 8.2+
- Composer
- Node.js + npm
- SQLite hoặc MySQL/MariaDB nếu muốn đổi DB local

### Thiết lập nhanh

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item database/database.sqlite -ItemType File -Force
php artisan jwt:secret
php artisan migrate --seed
npm install
composer dev
```

API mặc định sẽ chạy tại `http://127.0.0.1:8000/api`.

### Nếu dùng lệnh bootstrap sẵn

Repository đã có script:

```powershell
composer setup
```

Tuy nhiên sau đó vẫn nên kiểm tra 2 việc sau:

- Đã tạo file `database/database.sqlite` nếu dùng SQLite local.
- Đã sinh `JWT_SECRET` bằng `php artisan jwt:secret`.

### Nếu dùng MySQL/MariaDB

Sửa các biến `DB_*` trong `.env`, ví dụ:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=diamond_api
DB_USERNAME=root
DB_PASSWORD=
```

Sau đó chạy lại:

```powershell
php artisan migrate --seed
```

## Biến môi trường quan trọng

- `APP_API_PREFIX`: prefix cho toàn bộ API, mặc định là `api`
- `AUTH_GUARD`: guard mặc định, hiện dùng `api`
- `JWT_SECRET`: secret ký JWT, bắt buộc cho môi trường local/staging/production
- `DB_CONNECTION`: mặc định là `sqlite`

## Tài khoản seed mặc định

Chạy `php artisan db:seed` hoặc `php artisan migrate --seed` sẽ tạo sẵn:

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `password` | `system_admin` |
| `operator` | `password` | `event_operator` |

Chỉ dùng các tài khoản này cho môi trường development/demo.

## API hiện đã có

Base URL mặc định: `http://127.0.0.1:8000/api`

| Method | Endpoint | Mô tả | Auth |
| --- | --- | --- | --- |
| `POST` | `/auth/login` | Đăng nhập bằng `username` hoặc `email` + `password` | Không |
| `GET` | `/auth/me` | Lấy thông tin người dùng hiện tại | Bearer token |
| `POST` | `/auth/logout` | Đăng xuất và blacklist token | Bearer token |
| `GET` | `/auth/refresh` | Làm mới access token | Bearer token cũ |

### Ví dụ đăng nhập

```bash
curl --request POST "http://127.0.0.1:8000/api/auth/login" \
  --header "Content-Type: application/json" \
  --data "{\"username\":\"admin\",\"password\":\"password\"}"
```

### Format response

Tất cả API đang hướng tới cùng một cấu trúc response:

```json
{
  "message": "Thành công.",
  "statusCode": 200,
  "metadata": {},
  "path": "/api/auth/me",
  "timestamp": "2026-04-12T16:00:00Z"
}
```

Quy ước hiện tại:

- Dữ liệu chính nằm trong `metadata`
- Lỗi validate trả chi tiết field trong `metadata`
- API exception được chuẩn hóa tại `bootstrap/app.php`
- Trait dùng chung cho controller là `App\Support\Concerns\ApiResponse`

## Phân quyền và xác thực

- Guard mặc định là `auth:api`
- User chỉ đăng nhập được khi:
  - `status = active`
  - `is_active = true`
- Middleware alias `role` được đăng ký trong `bootstrap/app.php`
- `system_admin` hiện có quyền đi qua các rule allow-role trong middleware

Ví dụ bảo vệ route:

```php
Route::middleware(['auth:api', 'role:system_admin'])->group(function () {
    // protected routes
});
```

## Test và chất lượng code

Chạy test:

```powershell
composer test
```

Hoặc:

```powershell
php artisan test
```

Lưu ý:

- Test dùng SQLite in-memory, không phụ thuộc DB local
- `phpunit.xml` đã cấu hình `JWT_SECRET` riêng cho môi trường test
- Feature test auth hiện nằm tại `tests/Feature/Api/AuthTest.php`

## Quy ước khi phát triển tiếp

- Ưu tiên dùng enum trong `app/Enums` thay vì hardcode string cho role/status/type.
- Giữ format API response thống nhất, không trả JSON tự phát theo từng controller.
- Khi thêm endpoint mới, nên có feature test tương ứng.
- Route API nên khai báo trong `routes/api.php` và gắn `auth:api`/`role` khi cần.
- Nếu mở rộng schema, cập nhật migration/enum/seed/test đồng bộ để tránh lệch dữ liệu.

## Gợi ý backlog gần nhất

- Hoàn thiện CRUD cho các module nghiệp vụ chính.
- Tách business logic ra service/action layer khi số lượng endpoint tăng.
- Bổ sung request/resource cho từng module.
- Viết thêm test cho middleware phân quyền, refresh token và logout flow.
- Bổ sung tài liệu OpenAPI hoặc Postman collection khi số lượng API tăng.

## Cộng tác viên đã ghi nhận trong Git

Theo lịch sử Git hiện tại của repository, tác giả đã commit là:

- `hieu92264`

Nếu sau này có thêm contributor, nên cập nhật mục này hoặc chuyển sang dùng badge/liên kết contributors tự động.
