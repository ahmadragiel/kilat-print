# Kilat Print

**Kilat Print** adalah platform e-commerce custom printing dan production management berbasis Laravel untuk mengelola katalog, konfigurasi produk, harga, cart, checkout, pembayaran transfer bank, review desain, produksi, quality control, notification, reporting, dan invoice.

## Fitur

- Autentikasi dengan tiga role: `admin`, `operator`, dan `customer`.
- Katalog dengan pencarian, filter kategori/rentang harga, sorting, dan pagination.
- **Product Design Editor** berbasis Fabric.js: preview mockup depan/belakang, upload gambar, teks, stiker, drag/resize/rotate/flip, layer, zoom viewport, mode preview, dan simpan desain terstruktur.
- Kustomisasi ukuran, material, finishing, warna, metode produksi, quantity, catatan, dan file desain.
- Price engine server-side untuk `per_item`, `per_sqm`, `per_meter`, `fixed`, dan `additional_fee`.
- Cart dan checkout yang selalu menghitung ulang harga di server dalam database transaction.
- Transfer bank, upload bukti pembayaran, verifikasi/penolakan admin, dan upload ulang.
- Upload desain privat dengan versioning serta approval/request revision.
- Status order tervalidasi berdasarkan role dan setiap perubahan memiliki history.
- Production assignment, progress, finishing, quality check PASS/FAIL, rework, foto produksi, dan authorization file.
- Dashboard customer/admin/operator, notifikasi database, report penjualan, BI, dan invoice printable.
- Master data dan price-rule CRUD dengan soft delete untuk master data.

## Technology Stack

- Laravel 12 (monolith)
- PHP 8.2+
- Eloquent ORM
- Blade, Alpine.js, Tailwind CSS 4, Vite
- Chart.js 4
- Fabric.js 7 (design editor, dimuat lazy hanya di halaman kustomisasi)
- MySQL 8+ / MariaDB 10.4+ (SQLite untuk local fallback dan automated test)
- Laravel Storage, Notification, Authentication, Form Request, Middleware, dan Policy
- PHPUnit 11

## Requirements

Untuk Windows/Laragon:

- PHP 8.2 atau lebih baru dengan extension `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, dan `zip`
- Composer 2
- Node.js 20+ dan NPM
- MySQL 8+ atau MariaDB 10.6+
- Laragon direkomendasikan untuk development lokal

## Installation

### 1. Install dependency

```powershell
composer install
npm install
```

### 2. Environment

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

`.env.example` memakai konfigurasi MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kilat_print
DB_USERNAME=root
DB_PASSWORD=
```

Buat database melalui Laragon/phpMyAdmin atau MySQL CLI:

```powershell
mysql -u root -p -e "CREATE DATABASE kilat_print CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Local tanpa MySQL (fallback)

Untuk development lokal tanpa server MySQL, edit `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Buat file database bila belum ada:

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
```

### 4. Migration dan seed demo

```powershell
php artisan optimize:clear
php artisan migrate
php artisan db:seed
```

Untuk mengulang dataset demo pada development:

```powershell
php artisan migrate:fresh --seed
```

> Perintah `migrate:fresh` menghapus seluruh data database. Jangan gunakan pada production tanpa backup.

### 5. Storage dan frontend build

```powershell
php artisan storage:link
npm run build
```

Untuk aktifkan development Vite:

```powershell
npm run dev
```

### 6. Jalankan aplikasi

Terminal 1:

```powershell
php artisan serve
```

Terminal 2 (queue bila notification diproses asynchronous):

```powershell
php artisan queue:work
```

Atau jalankan server, queue, log, dan Vite sekaligus melalui script Composer:

```powershell
composer dev
```

Buka `http://localhost:8000`.

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@kilatprint.test` | `password` |
| Operator | `operator@kilatprint.test` | `password` |
| Customer | `customer@kilatprint.test` | `password` |

Ganti password demo sebelum aplikasi dipublikasikan.

## Roles dan Route Utama

- Customer: `/dashboard`, `/products`, `/products/{slug}/customize`, `/cart`, `/checkout`, `/orders`, `/profile`, `/addresses`, `/notifications`
- Admin: `/admin/dashboard`, `/admin/products`, `/admin/categories`, `/admin/materials`, `/admin/finishings`, `/admin/prices`, `/admin/orders`, `/admin/payments`, `/admin/designs`, `/admin/production`, `/admin/reports`, `/admin/invoices`
- Operator: `/operator/dashboard`, `/operator/jobs`

Route customer, admin, dan operator dipisahkan oleh authentication, role middleware, dan ownership/production policy.

## Business Workflow

```text
Checkout
→ PENDING_PAYMENT
→ Payment proof / PAYMENT_REVIEW
→ Admin verification / PAYMENT_CONFIRMED
→ Design review / revision / approval
→ Operator assignment / WAITING_PRODUCTION
→ IN_PRODUCTION
→ FINISHING
→ QUALITY_CHECK
→ READY
→ SHIPPED / pickup completion
→ COMPLETED
```

Customer hanya dapat mengakses order, alamat, notification, dan design miliknya. Operator hanya dapat melihat job yang ditugaskan dan mengunduh design file job tersebut. Admin melakukan verifikasi pembayaran, review desain, assignment, monitoring, status transition, reporting, dan invoice.

## Price Engine

Harga browser tidak pernah dipercaya. `PriceCalculationService`:

1. Mengambil product price rule aktif dari database.
2. Memvalidasi material/finishing yang memang terhubung ke product.
3. Menghitung unit based on `per_item`, `per_sqm`, `per_meter`, atau `fixed`.
4. Menambahkan material, finishing, serta additional fee.
5. Menyimpan `price_at_addition` ketika item masuk cart.
6. Menghitung ulang seluruh item ketika checkout.

Contoh conversion area:

```text
panjang (cm) × lebar (cm) ÷ 10.000 = luas (m²)
```

Harga Flexi dan Mata Ayam pada seeder adalah **data dummy demo**, bukan klaim harga bisnis nyata.

## Product Design Editor

Editor desain memakai Fabric.js 7 dan berada di halaman kustomisasi produk. Pustaka canvas
dimuat **lazy** (dynamic `import()`), sehingga halaman lain tidak membayar bundle Fabric.

### Konsep data

Desain disimpan sebagai konfigurasi terstruktur, bukan hanya screenshot:

```json
{
  "front": {
    "elements": [
      {
        "id": "el_1a2b", "type": "image", "asset_id": "uuid-aset",
        "x": 100, "y": 80, "width": 200, "height": 200,
        "rotation": 0, "scaleX": 1, "scaleY": 1,
        "flipX": false, "flipY": false, "opacity": 1, "visible": true,
        "side": "front", "layer": 0
      }
    ]
  },
  "back": { "elements": [] }
}
```

- Tipe elemen: `image`, `text`, `sticker`.
- `image` hanya menyimpan `asset_id`; `src` tidak pernah dipercaya dari browser dan
  selalu di-resolve server menjadi URL route yang terotorisasi.
- `sticker` hanya boleh memakai aset lokal `/images/stickers/*.svg`.
- Teks bisa diedit langsung di kanvas (double-click) maupun lewat panel kontrol.

### Tabel

- `custom_design_drafts`: draft desain per customer (UUID), berisi `specification`,
  `design`, `version`, dan `status`.
- `custom_design_assets`: file gambar privat milik sebuah draft, tersimpan di disk
  non-publik dan hanya dapat diunduh melalui `custom-designs.show` setelah policy check.
- `cart_items.custom_design_draft_id` dan `order_items.custom_design_draft_id`:
  relasi draft ke cart dan order (repeat order memakai draft yang sama).
- `products.front_mockup` / `products.back_mockup`: path mockup produk. Path dikirim
  dari database, bukan di-hardcode di frontend, sehingga admin dapat menggantinya.

### Endpoint

| Method | URI | Nama route |
| --- | --- | --- |
| POST | `/custom-designs` | `custom-designs.store` |
| PUT | `/custom-designs/{draft}` | `custom-designs.update` |
| POST | `/custom-designs/{draft}/assets` | `custom-designs.assets.store` |
| GET | `/custom-designs/assets/{asset}` | `custom-designs.show` |

Save menerima `product_id`, `specification`, dan `design`.both `POST` dan `PUT` berada di
middleware `auth` + `role:customer` dengan CSRF, dan service memverifikasi kepemilikan
draft serta asal setiap `asset_id`.

### Alur lanjut ke cart

1. Editor menyimpan draft (create/update) dan menandai bila ada perubahan belum disimpan.
2. Canvas diekspor menjadi PNG pada sisi aktif dan dilampirkan ke form sebagai `design_file`.
3. `CartController` memuat draft berdasarkan kepemilikan, lalu menyalin konfigurasi
   desain ke `custom_parameters`. Harga tetap dihitung ulang oleh `PriceCalculationService`.
4. Item di cart memiliki tombol "Edit konfigurasi & desain" untuk membuka kembali editor
   dengan draft yang sama.

### Catatan implementasi

- Boundary kanvas memakai `clipPath` Fabric, dan objek di-clamp agar tetap berada di area desain.
- Objek Fabric **tidak pernah** melewati reactive proxy Alpine, karena helper collection
  Fabric berbasis reference identity. Panel kontrol hanya menerima snapshot biasa.
- Elemen yang tidak dikenal pada payload dibuang, bukan disimpan.
- Aset gambar yang tidak lagi direferensikan setelah save berhasil ikut dihapus.

## Penyimpanan File

- `storage/app/private/designs`: design customer/revisi
- `storage/app/private/payment-proofs`: bukti pembayaran
- `storage/app/private/design-references`: referensi file saat configure cart
- `storage/app/private/custom-design-assets`: gambar yang diunggah lewat design editor
- `storage/app/private/production-photos`: foto progres produksi
- `storage/app/public`: thumbnail produk/kategori
- `public/images/mockups`: mockup produk bawaan (front/back)
- `public/images/stickers`: sticker SVG lokal untuk design editor

Seluruh file customer tidak disimpan langsung di `public`. Download design/payment selalu melalui controller dan policy. Jangan depress disk `local` atau mengubah file menjadi public.

## Project Structure

```text
app/
├── Enums/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Models/
├── Notifications/
├── Policies/
├── Services/
└── Support/
database/
├── factories/
├── migrations/
└── seeders/
resources/
├── css/
├── js/
└── views/
routes/
tests/
```

Service utama:

- `PriceCalculationService`
- `OrderService`
- `OrderStatusService`
- `PaymentVerificationService`
- `DesignReviewService`
- `ProductionService`
- `RepeatOrderService`
- `CustomDesignService`

## Testing

Test otomatis memakai in-memory SQLite:

```powershell
php artisan test
```

Suite saat ini berisi **83 test / 495 assertions** dan mencakup authentication, authorization/ownership, katalog, price calculation, cart, checkout, payment, design review, design editor persistence, status transition, master-data CRUD, repeat order, production, QC, dashboard, reporting, dan rendering markup (deteksi kebocoran komponen Blade).

Verifikasi sebelum release:

```powershell
php artisan optimize:clear
php artisan migrate
php artisan db:seed
php artisan route:list
php artisan test
npm run build
```

## Production Checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, dan URL HTTPS.
- Gunakan credential MySQL khusus aplikasi, bukan root tanpa password.
- Ganti seluruh password dan rekening demo.
- Set `SESSION_SECURE_COOKIE=true` dan `SESSION_ENCRYPT=true`.
- Gunakan private disk/managed object storage untuk design dan payment proof.
- Jalankan queue worker, scheduled task, backup database, dan log monitoring.
- Jalankan `php artisan config:cache`, `route:cache`, dan `view:cache` hanya setelah environment benar.

## License

Project ini dibuat untuk kebutuhan coursework/portfolio/internal business. Hak penggunaan mengikuti repository owner.
