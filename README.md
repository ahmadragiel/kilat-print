# Kilat Print

**Kilat Print** adalah platform e-commerce custom printing dan production management berbasis Laravel untuk mengelola katalog, konfigurasi produk, harga, cart, checkout, pembayaran QRIS dan transfer bank, desain, produksi, quality control, notification, reporting, dan invoice.

## Fitur

- Autentikasi dengan tiga role: `admin`, `operator`, dan `customer`.
- Katalog dengan pencarian, filter kategori/rentang harga, sorting, dan pagination.
- **Product Design Editor** berbasis Fabric.js: preview mockup depan/belakang, upload gambar, teks, stiker, drag/resize/rotate/flip, layer, zoom viewport, mode preview, dan simpan desain terstruktur.
- Kustomisasi ukuran, warna, metode produksi, quantity, dan catatan. Customer **tidak** memilih bahan maupun finishing.
- Price engine server-side untuk `per_item`, `per_sqm`, `per_meter`, `fixed`, dan `additional_fee`.
- Cart dan checkout yang selalu menghitung ulang harga di server dalam database transaction.
- Pembayaran **QRIS** dan **transfer bank virtual account** melalui payment gateway, dikonfirmasi otomatis lewat webhook bertanda tangan. Tanpa unggah bukti pembayaran dan tanpa verifikasi manual.
- Upload desain privat dengan versioning; desain langsung siap produksi tanpa tahap review admin.
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

- Customer: `/dashboard`, `/products`, `/products/{slug}/customize`, `/cart`, `/checkout`, `/orders`, `/orders/{number}/payment`, `/profile`, `/addresses`, `/notifications`
- Admin: `/admin/dashboard`, `/admin/products`, `/admin/categories`, `/admin/materials`, `/admin/finishings`, `/admin/prices`, `/admin/orders`, `/admin/payments`, `/admin/designs`, `/admin/production`, `/admin/reports`, `/admin/invoices`
- Operator: `/operator/dashboard`, `/operator/jobs`
- Provider: `POST /payments/webhook/midtrans`

Route customer, admin, dan operator dipisahkan oleh authentication, role middleware, dan ownership/production policy.

## Business Workflow

```text
Checkout
→ PENDING_PAYMENT
→ Customer membayar QRIS / transfer bank ke virtual account
→ Penyedia pembayaran mengonfirmasi (webhook bertanda tangan)
→ PAYMENT_CONFIRMED
→ Operator assignment / IN_PRODUCTION
   (detail: IN_DESIGN → PRINTING → FINISHING → PACKING → QUALITY_CONTROL)
→ QC PASS / COMPLETED
→ atau QC FAIL / rework kembali ke produksi
```

Customer hanya dapat mengakses order, alamat, notification, dan design miliknya. Operator hanya dapat melihat job yang ditugaskan dan mengunduh design file job tersebut. Admin mengelola assignment, monitoring, status transition, reporting, dan invoice, serta **melihat** status pembayaran secara read-only.

Tidak ada lagi tahap `PAYMENT_REVIEW`, `DESIGN_REVIEW`, `DESIGN_REVISION`, dan `DESIGN_APPROVED`. Review desain manual dan verifikasi pembayaran manual sudah dihapus. Harga **`PAID`** hanya dapat ditulis setelah backend menerima konfirmasi valid dari penyedia pembayaran; ADMIN, OPERATOR, dan CUSTOMER tidak memiliki endpoint untuk memaksa status tersebut.

## Payment Gateway

Kilat Print memakai **Midtrans Snap/Core API** (`payment_type: qris` dan `payment_type: bank_transfer`) sebagai penyedia pembayaran. Driver HTTP berada di `App\Services\Payment\Drivers\MidtransGateway` dan memakai `Http::asJson()->withBasicAuth($serverKey, '')`. SDK tidak ditambahkan sebagai dependency.

### Konfigurasi

```dotenv
PAYMENT_PROVIDER=midtrans        # midtrans | null
PAYMENT_SERVER_KEY=              # server key Midtrans (JANGAN di commit)
PAYMENT_CLIENT_KEY=              # hanya untuk integrasi frontend Snap.js
PAYMENT_MERCHANT_ID=             # identifikasi akun, tidak dipakai Core API
PAYMENT_SANDBOX=true             # true = api.sandbox.midtrans.com
PAYMENT_EXPIRY_HOURS=24
PAYMENT_BANK_TRANSFER_BANK=bca   # bca | bni | bri | permata | cimb
PAYMENT_CURRENCY=IDR
```

Semua variabel dibaca di `config/payments.php`. `PAYMENT_SERVER_KEY` hanya dipakai di
header `Authorization` untuk request server-to-server dan **tidak pernah** dirender ke
Blade, JavaScript, log, atau respons browser.

Untuk memasukkan server key tanpa menampilkannya di terminal, riwayat shell, atau
transkrip percakapan:

```powershell
php artisan payments:key          # prompt tersembunyi, hanya menulis ke .env
php artisan optimize:clear
php artisan payments:check        # verifikasi konfigurasi + konektivitas
```

Selama `PAYMENT_SERVER_KEY` kosong, driver menolak membuat transaksi
(`GatewayException`) dan halaman pembayaran menampilkan instruksi konfigurasi —
aplikasi tidak pernah menampilkan centang berhasil palsu.

### Endpoint

| Environment | Base URL |
| --- | --- |
| Sandbox | `https://api.sandbox.midtrans.com` |
| Production | `https://api.midtrans.com` |

| Method | Path | Fungsi |
| --- | --- | --- |
| POST | `{base}/v2/charge` | Membuat transaksi (QRIS / virtual account) |
| GET | `{base}/v2/{order_id}/status` | Membaca status transaksi |
| POST | `/payments/webhook/midtrans` | Notifikasi masuk dari Midtrans |

Di dashboard Midtrans, atur **Payment Notification URL** ke
`https://<domain-publik>/payments/webhook/midtrans`. `localhost` tidak dapat menerima
callback Midtrans; gunakan HTTPS tunnel atau staging.

### Alur QRIS

1. Checkout memvalidasi `payment_method=QRIS` dan membuat order `PENDING_PAYMENT`
   plus payment `PENDING`.
2. Server memanggil `POST {base}/v2/charge` dengan `payment_type: qris`,
   `order_id` = nomor pesanan, `gross_amount` = total server (integer IDR),
   `customer_details`, dan `custom_expiry`.
3. Respons berisi `actions` dengan `generate-qr-code`, yaitu endpoint PNG. URL-nya
   disimpan pada `payments.qr_image` dan ditampilkan apa adanya sebagai `<img>`.
4. Halaman `/orders/{number}/payment` menampilkan QR, nominal, referensi transaksi,
   dan status menunggu.
5. Customer membayar; Midtrans mengirim notifikasi; status menjadi `PAID`.

### Alur transfer bank

1. Checkout memvalidasi `payment_method=BANK_TRANSFER`.
2. Driver mengirim `payment_type: bank_transfer` dengan bank **di dalam objek**
   `bank_transfer`. Field `bank` di level atas akan diabaikan oleh Midtrans:

   ```json
   { "payment_type": "bank_transfer", "bank_transfer": { "bank": "bca" } }
   ```

3. Midtrans mengembalikan virtual account di dalam array `va_numbers`:

   ```json
   { "va_numbers": [ { "bank": "bca", "va_number": "91019021579" } ] }
   ```

   Nilai tersebut disimpan pada `payments.virtual_account_number` dan
   `payments.virtual_account_bank`.
4. Customer transfer ke virtual account itu. Midtrans mengonfirmasi otomatis —
   **bukan** transfer ke rekening statis yang tidak bisa dideteksi.
5. Notifikasi diterima → status `PAID`.

### Mekanisme callback/webhook

`POST /payments/webhook/midtrans` ditangani oleh `Webhook\MidtransNotificationController`:

1. **Autentikasi** — Midtrans menaruh `signature_key` di **body** notifikasi,
   bernilai `sha512(order_id + status_code + gross_amount + server_key)`. Nilai itu
   dibandingkan dengan `hash_equals`. Header `X-Konexity-Signature` diterima sebagai
   fallback untuk reverse proxy yang memaparkannya. Signature salah atau payload
   dimanipulasi → `403`.
2. **Lookup** — payment dicari berdasarkan `provider` + `provider_reference`.
   Tidak ditemukan → `404`.
3. **Pemetaan status** — `capture`/`settlement` → `PAID`, `deny` → `FAILED`,
   `expire`/`cancel` → `EXPIRED`, selain itu → `PENDING`.
4. **`status_code`** — hanya `200` yang dianggap sukses. Transaksi yang dibuat tapi
   belum dibayar melaporkan `201`, dan yang kedaluwarsa melaporkan `202`.
5. **Validasi nominal dan mata uang** — `gross_amount` harus sama dengan
   `payments.amount` dan `currency` harus `IDR`.
6. **Fraud check** — `fraud_status` harus kosong atau `accept`.
7. **Idempotensi** — payment yang sudah `PAID` mengembalikan `already_paid` tanpa
   efek samping; transaksi `FAILED`/`EXPIRED`/`CANCELLED` tidak pernah dihidupkan
   kembali (`final_state`).
8. **Dampak** — hanya `PaymentSettlementService` yang boleh menulis `PAID`. Service
   ini mengunci baris payment dan order (`lockForUpdate`), mengisi `paid_at`,
   memindahkan order `PENDING_PAYMENT → PAYMENT_CONFIRMED` melalui
   `OrderStatusService::systemTransition()` (history ditulis dengan `changed_by = null`),
   membuat record produksi berstatus `IN_DESIGN`, lalu mengirim notifikasi.

Tombol "Periksa status pembayaran" di halaman customer hanya memicu
`GET {base}/v2/{order_id}/status` ke penyedia dari server. Parameter dari browser
tidak pernah dipercaya.

### Alur pemindaian QRIS

Halaman `/orders/{number}/payment` menampilkan QRIS asli dari penyedia. Setelah
customer memindai QR, halaman melakukan polling ke
`GET /orders/{number}/payment/status` setiap 6 detik sehingga hasilnya muncul
otomatis tanpa refresh manual.

Endpoint tersebut **hanya melaporkan** status yang sudah tercatat di aplikasi.
Endpoint ini tidak pernah menulis status; satu-satunya penentu tetap
`PaymentSettlementService` berdasarkan konfirmasi penyedia. Pembacaan penyedia
dibatasi maksimal sekali per 8 detik per transaksi agar polling tidak membebani
gateway.

Saat transaksi dibuat, `qr_image` berisi endpoint PNG milik Midtrans
(`{base}/v2/qris/{transaction_id}/qr-code`). Endpoint itu dapat diambil tanpa
kredensial apa pun, sehingga `<img>` boleh menunjuk URL tersebut secara langsung
tanpa pernah mengirim server key ke browser. Host URL tetap dikunci ke domain
Midtrans agar respons penyedia yang tidak terduga tidak bisa memuat gambar dari
host lain.

### Menguji pembayaran di sandbox

Halaman `/admin/payment-sandbox` (khusus sandbox) menyediakan dua aksi. Keduanya
berbicara dengan penyedia, tidak pernah menulis status secara langsung:

| Aksi | Yang terjadi |
| --- | --- |
| `Cek ke penyedia` | Membaca `GET {base}/v2/{order_id}/status`, lalu hasilnya diproses alur settlement biasa. |
| `Paksa kedaluwarsa` | Memanggil `POST {base}/v2/{order_id}/expire` milik Midtrans, sehingga penyedia yang memindahkan status. |

Seluruh halaman dan aksi ditolak dengan `403` bila gateway tidak mengarah ke
lingkungan uji Midtrans.

**Bayar berhasil (QRIS)**

1. Buat transaksi uji: `php artisan payments:sandbox-transaction --method=qris`.
2. Salin tautan gambar QRIS dari halaman pembayaran customer.
3. Buka [Midtrans QRIS Simulator](https://simulator.sandbox.midtrans.com/v2/qris/index),
   tempel tautan tersebut, klik **Scan QR**, lalu selesaikan dengan e-wallet.
4. Halaman pembayaran customer berubah menjadi **Pembayaran Berhasil** secara
   otomatis; pada produksi hal ini terjadi lewat webhook.

**Bayar gagal**

Klik `Paksa kedaluwarsa`. Midtrans mengubah statusnya menjadi `expire`
(`status_code` 407) dan aplikasi menandai transaksi `EXPIRED`, tanpa
`paid_at` dan tanpa record produksi.

### Perintah bantu

```powershell
php artisan payments:check                  # konfigurasi + konektivitas, tanpa membuat transaksi
php artisan payments:key                    # simpan server key ke .env (input disembunyikan)
php artisan payments:sandbox-transaction    # buat transaksi uji nyata di Sandbox
```

`payments:sandbox-transaction` membuat order uji sungguhan, memanggil API Sandbox,
lalu mencetak reference, QR atau VA dari provider, dan memverifikasi bahwa transaksi
masih `PENDING` serta belum ada record produksi.

### Status pembayaran

| Status | Artinya |
| --- | --- |
| `PENDING` | Transaksi dibuat, belum dikonfirmasi penyedia. |
| `PAID` | Dikonfirmasi penyedia lewat webhook bertanda tangan atau status endpoint. |
| `FAILED` | Penyedia melaporkan `deny`. |
| `EXPIRED` | Penyedia melaporkan `expire`/`cancel`, atau lewat pay window. |
| `CANCELLED` | Dibatalkan lokal. |

Kolom `proof_*` pada tabel `payments` tetap ada untuk transaksi lama yang sudah
tercatat sebelumnya, dan tetap bisa diunduh admin lewat `admin.payments.proof`.
Tidak ada alur baru yang menulis kolom tersebut.

### Driver

`App\Services\Payment\PaymentGateway` adalah kontrak yang dipakai seluruh aplikasi.
Driver yang tersedia:

- `MidtransGateway` — integrasi HTTP nyata.
- `NullGateway` — menolak membuat transaksi; dipakai saat kredensial belum ada.

`PaymentGatewayFactory` memilih driver dari `config('payments.driver')`, dan
`AppServiceProvider` mengikat kontrak ke driver tersebut sehingga test dapat
mengikat stub tanpa menyentuh jaringan.

## Price Engine

Harga browser tidak pernah dipercaya. `PriceCalculationService`:

1. Mengambil product price rule aktif dari database.
2. Menghitung unit based on `per_item`, `per_sqm`, `per_meter`, atau `fixed`.
3. Menambahkan `additional_fee` bila ada.
4. Menyimpan `price_at_addition` ketika item masuk cart.
5. Menghitung ulang seluruh item ketika checkout.

Bahan dan finishing tidak lagi menjadi input customer maupun komponen harga.
Kolom `order_items.material_*` dan `order_items.finishing_*` tetap ada (nullable)
agar data historis tidak hilang, tetapi tidak lagi diisi oleh alur pemesanan baru.

Contoh conversion area:

```text
panjang (cm) × lebar (cm) ÷ 10.000 = luas (m²)
```

Harga dan aturan harga produk Kilat Print pada seeder adalah
**data dummy demo** untuk kalkulator aplikasi, bukan klaim tarif resmi perusahaan.

## Katalog Produk

Katalog seed mengikuti produk yang disebut pada laporan Kerja Praktek Kilat Print.
Tidak ada produk generik percetakan (banner, spanduk, brosur, flyer, poster, stiker,
kartu nama, undangan, nota, kop surat) di katalog aktif.

| Produk | Slug | Kategori |
| --- | --- | --- |
| Mug Custom | `mug-custom` | Produk Custom |
| Bando Tuning Custom | `bando-tuning-custom` | Produk Custom |
| Paper Bag Custom | `paper-bag-custom` | Printing Custom |
| Kertas Kado | `kertas-kado` | Printing Custom |
| Apotek Mini | `apotek-mini` | Printing Custom |
| Topper Cake | `topper-cake` | Produk Custom |
| Topeng Muka | `topeng-muka` | Produk Custom |

Semua produk memakai satuan `per_item`, minimum pesan 1, dan deskripsi singkat yang
mengikuti istilah laporan. `Paper Bag Custom` mewakili produk paper bag, jadi tidak
ada duplikasi `Paper Bag`.

Visual katalog memakai ilustrasi SVG generik per produk di `public/images/products`.
Ilustrasi tersebut bukan foto produk asli Kilat Print.

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
- `storage/app/private/payment-proofs`: bukti pembayaran (historis, read-only)
- `storage/app/private/design-references`: referensi file saat configure cart
- `storage/app/private/custom-design-assets`: gambar yang diunggah lewat design editor
- `storage/app/private/production-photos`: foto progres produksi
- `storage/app/public`: thumbnail produk/kategori
- `public/images/mockups`: mockup produk bawaan (front/back)
- `public/images/products`: ilustrasi katalog Kilat Print (SVG, satu file per produk)
- `public/images/logo.png`: logo Kilat Print merah tua + kilat kuning (latar terang, dipakai `<x-brand>` + favicon)
- `public/images/logo-white.png`: versi knockout putih + kilat kuning untuk panel merah / footer gelap
- `public/images/stickers`: sticker SVG lokal untuk design editor

Seluruh file customer tidak disimpan langsung di `public`. Download design selalu melalui controller dan policy. Jangan depress disk `local` atau mengubah file menjadi public.

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
- `ProductionService`
- `RepeatOrderService`
- `CustomDesignService`
- `DesignUploadService`
- `Payment\PaymentTransactionService` — membuat transaksi yang payable
- `Payment\PaymentSettlementService` — satu-satunya tempat yang boleh menulis `PAID`
- `Payment\PaymentGatewayFactory` + `Payment\Drivers\*` — driver penyedia pembayaran

## Testing

Test otomatis memakai in-memory SQLite:

```powershell
php artisan test
```

Suite saat ini berisi **138 test / 1030+ assertions** dan mencakup authentication, authorization/ownership, katalog Kilat Print, price calculation, cart, checkout tanpa bahan/finishing, payment gateway (webhook, signature, idempotensi, nominal, mata uang, fraud, status final), design upload tanpa review admin, design editor persistence, status transition, master-data CRUD, repeat order, production, QC, dashboard, reporting, dan rendering markup.

Test payment memakai **HTTP stub** (`Tests\Fakes\FakeMidtransApi`) terhadap endpoint Midtrans, sehingga driver, controller, service, dan webhook berjalan sungguhan tanpa jaringan. Stub ini bukan akun sandbox Midtrans: test ini **tidak** membuktikan pembayaran live berhasil.

Verifikasi sebelum release:

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list
php artisan test
npm run build
```

## Production Checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, dan URL HTTPS.
- Gunakan credential MySQL khusus aplikasi, bukan root tanpa password.
- Ganti seluruh password dan rekening demo.
- Set `PAYMENT_PROVIDER=midtrans`, `PAYMENT_SERVER_KEY=<server key live>`, dan `PAYMENT_SANDBOX=false`.
- Daftarkan `https://<domain>/payments/webhook/midtrans` sebagai notification URL di Midtrans.
- Set `SESSION_SECURE_COOKIE=true` dan `SESSION_ENCRYPT=true`.
- Gunakan private disk/managed object storage untuk design.
- Jalankan queue worker, scheduled task, backup database, dan log monitoring.
- Jalankan `php artisan config:cache`, `route:cache`, dan `view:cache` hanya setelah environment benar.

## License

Project ini dibuat untuk kebutuhan coursework/portfolio/internal business. Hak penggunaan mengikuti repository owner.
