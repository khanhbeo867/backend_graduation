# Deploy Laravel trên Windows VPS với Caddy + PHP-CGI + NSSM

Tài liệu này ghi lại cách deploy backend Laravel trên **Windows VPS** theo stack:

- **Caddy** làm web server
- **PHP 8.2.3** chạy bằng `php-cgi.exe`
- **NSSM** để biến `php-cgi` và `queue worker` thành Windows service
- **Task Scheduler** để chạy `php artisan schedule:run`

Mục tiêu là có thể lặp lại trên VPS Windows khác mà không phải cấu hình lại từ đầu.

---

## 1) Cấu trúc đề xuất

Ví dụ đường dẫn:

```txt
C:\caddy\caddy.exe
C:\caddy\Caddyfile

C:\php\php.exe
C:\php\php-cgi.exe

C:\nssm\nssm.exe

C:\www\api\diamond-api
```

Laravel app ở:

```txt
C:\www\api\diamond-api
```

Web root đúng của Laravel là:

```txt
C:\www\api\diamond-api\public
```

---

## 2) Cài Caddy

Tải bản Windows amd64 của Caddy, đặt tại:

```txt
C:\caddy\caddy.exe
```

Tạo file:

```txt
C:\caddy\Caddyfile
```

Nội dung cấu hình cho Laravel:

```caddy
:8000 {
    root * C:\www\api\diamond-api\public
    php_fastcgi 127.0.0.1:9000
    file_server
}
```

Test config:

```bat
C:\caddy\caddy.exe validate --config C:\caddy\Caddyfile
```

Reload config:

```bat
C:\caddy\caddy.exe reload --config C:\caddy\Caddyfile
```

---

## 3) Mở firewall cho Caddy

Ví dụ đang dùng port 8000 để test:

```bat
netsh advfirewall firewall add rule name="Caddy HTTP 8000" dir=in action=allow protocol=TCP localport=8000
```

Nếu sau này dùng domain chuẩn thì mở thêm 80 và 443:

```bat
netsh advfirewall firewall add rule name="Caddy HTTP 80" dir=in action=allow protocol=TCP localport=80
netsh advfirewall firewall add rule name="Caddy HTTPS 443" dir=in action=allow protocol=TCP localport=443
```

Ngoài Windows Firewall, nhớ mở port tương ứng trong firewall của nhà cung cấp VPS nếu có.

---

## 4) Biến Caddy thành service

Tạo service:

```bat
sc.exe create caddy start= auto binPath= "C:\caddy\caddy.exe run --config C:\caddy\Caddyfile"
```

Start:

```bat
sc.exe start caddy
```

Stop:

```bat
sc.exe stop caddy
```

Kiểm tra:

```bat
sc.exe query caddy
sc.exe qc caddy
```

Đặt autostart nếu cần:

```bat
sc.exe config caddy start= auto
```

---

## 5) Chuẩn bị PHP 8.2.3

Yêu cầu có sẵn:

```txt
C:\php\php.exe
C:\php\php-cgi.exe
```

Không dùng FrankenPHP trong phương án này. Dùng trực tiếp PHP 8.2.3 trên máy qua `php-cgi.exe`.

Test chạy tay:

```bat
C:\php\php-cgi.exe -b 127.0.0.1:9000
```

Kiểm tra port 9000:

```bat
netstat -ano | findstr :9000
```

Nếu Laravel lên ở `http://localhost:8000` thì cấu hình đúng.

---

## 6) Dùng NSSM để biến php-cgi thành service

Tải NSSM và đặt tại:

```txt
C:\nssm\nssm.exe
```

### Cách bằng giao diện

Mở CMD/PowerShell bằng quyền Administrator:

```bat
C:\nssm\nssm.exe install php-cgi
```

Điền:

- **Path**: `C:\php\php-cgi.exe`
- **Startup directory**: `C:\php`
- **Arguments**: `-b 127.0.0.1:9000`

Bấm **Install service**.

### Cách bằng command line

```bat
C:\nssm\nssm.exe install php-cgi C:\php\php-cgi.exe -b 127.0.0.1:9000
C:\nssm\nssm.exe set php-cgi AppDirectory C:\php
```

Start service:

```bat
sc.exe start php-cgi
```

Kiểm tra:

```bat
sc.exe query php-cgi
sc.exe qc php-cgi
```

Bật autostart:

```bat
sc.exe config php-cgi start= auto
```

### Một số lệnh NSSM hữu ích

Sửa service:

```bat
C:\nssm\nssm.exe edit php-cgi
```

Stop/start/restart:

```bat
C:\nssm\nssm.exe stop php-cgi
C:\nssm\nssm.exe start php-cgi
C:\nssm\nssm.exe restart php-cgi
```

Xóa service:

```bat
C:\nssm\nssm.exe remove php-cgi confirm
```

---

## 7) Chuẩn bị Laravel project

Vào thư mục project:

```bat
cd C:\www\api\diamond-api
```

Cài dependencies:

```bat
composer install --no-dev --optimize-autoloader
```

Tạo `.env` nếu chưa có:

```bat
copy .env.example .env
php artisan key:generate
```

Thiết lập cơ bản trong `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://IP-VPS:8000
```

Khi dùng domain thật:

```env
APP_URL=https://api.tenmien.com
```

Đảm bảo config database đúng.

---

## 8) Tối ưu Laravel cho production

```bat
cd C:\www\api\diamond-api
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Khi deploy code mới, nên restart queue:

```bat
php artisan queue:restart
```

---

## 9) Tạo service cho queue worker bằng NSSM

Nếu app có queue, không nên chạy `php artisan queue:work` trong terminal VS Code vì khi đóng terminal, disconnect RDP hoặc reboot thì worker sẽ chết.

### Cài bằng GUI

```bat
C:\nssm\nssm.exe install laravel-queue
```

Điền:

- **Path**: `C:\php\php.exe`
- **Startup directory**: `C:\www\api\diamond-api`
- **Arguments**: `artisan queue:work --tries=3 --timeout=120`

Bấm **Install service**.

### Hoặc command line

```bat
C:\nssm\nssm.exe install laravel-queue C:\php\php.exe artisan queue:work --tries=3 --timeout=120
C:\nssm\nssm.exe set laravel-queue AppDirectory C:\www\api\diamond-api
sc.exe config laravel-queue start= auto
sc.exe start laravel-queue
```

Kiểm tra:

```bat
sc.exe query laravel-queue
sc.exe qc laravel-queue
```

---

## 10) Tạo Task Scheduler cho Laravel scheduler

Lệnh `php artisan schedule:run` chỉ chạy **một lượt** rồi thoát, nên không để chạy tay trong terminal mãi được.

### Cách làm

Mở **Task Scheduler** -> **Create Task...**

### Tab General

- Name: `Laravel Scheduler`
- Chọn:
  - `Run whether user is logged on or not`
  - `Run with highest privileges`

### Tab Triggers

Tạo trigger chạy lặp lại theo lịch.
Nếu giao diện không cho chọn `1 minute`, có thể để mức nhỏ nhất mà máy cho phép hoặc tạo task theo khả năng hệ điều hành của bạn.

### Tab Actions

- **Program/script**:
  ```txt
  C:\Windows\System32\cmd.exe
  ```

- **Add arguments**:
  ```txt
  /c "cd /d C:\www\api\diamond-api && C:\php\php.exe artisan schedule:run"
  ```

Lưu task.

---

## 11) Kiểm tra sau reboot

Sau khi hoàn tất, nên reboot VPS một lần để kiểm chứng thật:

Kiểm tra các service:

```bat
sc.exe query caddy
sc.exe query php-cgi
sc.exe query laravel-queue
```

Kiểm tra port PHP:

```bat
netstat -ano | findstr :9000
```

Kiểm tra web:

```txt
http://localhost:8000
http://IP-VPS:8000
```

Nếu mọi thứ vẫn lên sau reboot thì hệ thống đã ổn.

---

## 12) Chuyển sang domain và HTTPS

Khi đã có domain:

1. Trỏ A record về IP VPS
2. Sửa `Caddyfile` từ `:8000` sang domain

Ví dụ:

```caddy
api.tenmien.com {
    root * C:\www\api\diamond-api\public
    php_fastcgi 127.0.0.1:9000
    file_server
}
```

3. Reload Caddy:

```bat
C:\caddy\caddy.exe reload --config C:\caddy\Caddyfile
```

4. Mở port 80 và 443 trong Windows Firewall và firewall của nhà cung cấp VPS

---

## 13) Checklist deploy lại trên VPS Windows khác

### Hạ tầng
- [ ] Cài Caddy
- [ ] Tạo `C:\caddy\Caddyfile`
- [ ] Mở firewall port 8000 hoặc 80/443
- [ ] Biến Caddy thành service

### PHP
- [ ] Cài PHP 8.2.3
- [ ] Xác nhận có `php.exe` và `php-cgi.exe`
- [ ] Dùng NSSM tạo service `php-cgi`
- [ ] Xác nhận `127.0.0.1:9000` đang LISTENING

### Laravel
- [ ] Copy source code vào `C:\www\api\diamond-api`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] Tạo `.env`
- [ ] `php artisan key:generate`
- [ ] Cấu hình DB
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`

### Service nền
- [ ] Tạo service `laravel-queue` bằng NSSM nếu app có queue
- [ ] Tạo `Task Scheduler` cho `php artisan schedule:run`

### Kiểm tra
- [ ] Test `http://localhost:8000`
- [ ] Test `http://IP-VPS:8000`
- [ ] Test lại sau reboot

---

## 14) Lệnh deploy nhanh khi cập nhật code

```bat
cd C:\www\api\diamond-api
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

## 15) Những chỗ cần kiểm tra khi có lỗi

### Caddy
```bat
sc.exe query caddy
```

### PHP-CGI
```bat
sc.exe query php-cgi
netstat -ano | findstr :9000
```

### Queue
```bat
sc.exe query laravel-queue
```

### Log Laravel
```txt
C:\www\api\diamond-api\storage\logs\laravel.log
```

### Thư mục cần ghi
- `C:\www\api\diamond-api\storage`
- `C:\www\api\diamond-api\bootstrap\cache`

---

## Ghi chú cuối

Phương án này phù hợp khi **đã lỡ dùng Windows VPS** và muốn stack Laravel tương đối gọn, ít lỗi hơn IIS:

- Caddy
- PHP-CGI
- NSSM
- Task Scheduler

Nếu sau này chuyển sang Linux, stack chuẩn phổ biến hơn vẫn là:

- Nginx
- PHP-FPM
- Supervisor
- Cron
