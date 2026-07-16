# FreeFCC Backend (Laravel + Filament)

FreeFCC Android uygulaması için üyelik/yetkilendirme sistemi. Kullanıcı adı ve
şifreyle giriş yapılır, her hesap tek bir cihaza (Android `ANDROID_ID`) kilitlenir,
ve her şey bu Laravel uygulamasındaki [Filament](https://filamentphp.com) admin
panelinden yönetilir.

## Ne içeriyor?

- **Admin panel** (`/admin`): `App\Models\User` ile giriş yapan yöneticiler için.
  Giriş alanına e-posta **veya** kullanıcı adı girilebilir (bkz.
  `app/Filament/Pages/Auth/Login.php`) — `@` içermeyen girişler `username`
  sütunuyla eşleştirilir. Üye ekleme/silme/pasif yapma, cihaz sıfırlama,
  üyelik bitiş tarihi ve giriş günlüğü buradan yönetilir.
- **API** (`/api/v1/...`): Android uygulamasının konuştuğu uçlar. Üyeler
  (`App\Models\Member`) admin panelinden giriş yapamaz; admin panel kullanıcıları
  API'den giriş yapamaz — iki kullanıcı türü kasıtlı olarak birbirinden ayrı.
- **Tek cihaz kilidi**: Bir üye ilk giriş yaptığında cihazının `ANDROID_ID`'si
  hesaba kaydedilir. Farklı bir cihazdan giriş denemesi `409 device_mismatch`
  ile reddedilir. Admin panelden "Cihazı Sıfırla" ile kilit kaldırılabilir.
- **Giriş günlüğü**: Başarılı/başarısız her giriş denemesi (kullanıcı adı, cihaz
  kimliği, IP, sebep) `login_logs` tablosuna kaydedilir ve panelde salt-okunur
  listelenir.

## Kurulum (yerel geliştirme)

Gereksinimler: PHP 8.2+ (`ext-intl` açık olmalı), Composer.

```bash
cd backend
composer install
copy .env.example .env      # Windows; Linux/Mac: cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan make:filament-user   # ilk admin panel kullanıcınızı oluşturur
php artisan serve
```

Panel: `http://127.0.0.1:8000/admin`
API taban adresi: `http://127.0.0.1:8000/api/v1`

Varsayılan veritabanı SQLite'dır (`database/database.sqlite`), ek kurulum
gerektirmez. Üretimde MySQL/PostgreSQL kullanmak isterseniz `.env`'deki
`DB_*` değerlerini güncelleyip tekrar `php artisan migrate` çalıştırın.

## Üretime (canlı sunucu) alma

1. Kodu sunucuya kopyalayın, `composer install --no-dev --optimize-autoloader` çalıştırın.
2. `.env` dosyasında en azından şunları ayarlayın:
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_URL=https://sizin-domaininiz.com` (API **mutlaka HTTPS olmalı** —
     Android uygulaması cleartext trafiğe izin vermiyor)
   - Gerçek bir veritabanı (`DB_CONNECTION=mysql` vb.)
3. `php artisan migrate --force`
4. `php artisan make:filament-user` ile admin kullanıcınızı oluşturun.
5. Web sunucunuzun (Nginx/Apache) doküman kökünü `public/` klasörüne yönlendirin.
6. `php artisan storage:link` (APK güncellemelerinin indirilebilmesi için zorunlu).
7. `php artisan config:cache && php artisan route:cache` (deploy sonrası önerilir).
8. Android tarafında `AuthApi.BASE_URL` değerini bu domaine güncelleyin
   (bkz. `app/src/main/java/com/freefcc/app/AuthApi.kt`).

## API sözleşmesi

Tüm istek/yanıtlar JSON. Kimlik doğrulaması gereken uçlar
`Authorization: Bearer <token>` header'ı bekler.

### `POST /api/v1/login`

İstek:
```json
{
  "username": "kullanici_adi",
  "password": "sifre",
  "device_id": "cihazin-android-id-degeri",
  "device_name": "Samsung SM-G991B (opsiyonel)",
  "app_version": "1.4.7"
}
```

Başarılı yanıt (`200`):
```json
{
  "status": "ok",
  "data": {
    "token": "1|abcdef...",
    "member": { "username": "kullanici_adi", "name": null, "expires_at": null }
  }
}
```

Hata yanıtları — hepsi `{"status":"error","code":"...","message":"..."}` biçiminde:

| HTTP | `code`                | Anlamı                                            |
|------|------------------------|----------------------------------------------------|
| 401  | `invalid_credentials`  | Kullanıcı adı veya şifre hatalı                    |
| 403  | `inactive`             | Hesap admin tarafından pasif yapılmış              |
| 403  | `expired`              | Üyeliğin süresi dolmuş                             |
| 409  | `device_mismatch`      | Hesap başka bir cihaza kayıtlı                     |

### `GET /api/v1/me`

Mevcut token'ın hâlâ geçerli olduğunu ve hesabın aktif olduğunu doğrular.
Uygulama her açılışta bunu çağırır. Hesap bu arada pasif/süresi dolmuş
olduysa token sunucu tarafında hemen iptal edilir ve `403` döner (aynı
`inactive`/`expired` kodlarıyla) — kullanıcı bir daha `/login` yapmak zorunda
kalır.

### `POST /api/v1/logout`

Mevcut token'ı iptal eder. Kullanıcı uygulamadan manuel çıkış yaptığında
çağrılır.

## Güvenlik notları

- Girişte IP başına dakikada 10 deneme sınırı var (`throttle:10,1`).
- Şifreler `bcrypt` ile saklanır, düz metin hiçbir yerde tutulmaz/loglanmaz.
- Her üyenin aynı anda tek bir geçerli token'ı olur — yeni bir giriş (aynı
  cihazdan da olsa) öncekini otomatik iptal eder.
- Token'lar varsayılan olarak 90 gün sonra geçersiz olur
  (`config/sanctum.php` → `SANCTUM_TOKEN_EXPIRATION_MINUTES`).
