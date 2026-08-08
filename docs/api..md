API Documentation — Sistem Arsip

Base URL

/api/v1
1. Authentication
Method	Endpoint	Fungsi
POST	/auth/login	Login
POST	/auth/logout	Logout
GET	/auth/me	Data user aktif

Service: AuthService

2. Dashboard
Method	Endpoint	Fungsi
GET	/dashboard	Statistik & aktivitas dashboard

Service: DashboardService

3. Mitra
Method	Endpoint	Fungsi
GET	/mitras	List & search mitra
POST	/mitras	Tambah mitra
GET	/mitras/{id}	Detail mitra
PUT	/mitras/{id}	Update mitra
DELETE	/mitras/{id}	Hapus/nonaktifkan mitra
GET	/mitras/{id}/documents	Dokumen milik mitra

Service: MitraService

4. Dokumen
Method	Endpoint	Fungsi
GET	/documents	Inventory dokumen
POST	/documents	Simpan metadata dokumen
GET	/documents/{id}	Detail dokumen
PUT	/documents/{id}	Update metadata
DELETE	/documents/{id}	Soft delete
GET	/documents/{id}/download	Generate download URL
GET	/documents/{id}/preview	Generate preview URL

Service:

DocumentService
DocumentQueryService
R2StorageService
5. Upload R2
Method	Endpoint	Fungsi
POST	/storage/presign	Generate upload URL
DELETE	/storage/{key}	Hapus object R2

Flow:

Next.js
   ↓
POST /storage/presign
   ↓
Laravel
   ↓
Presigned URL
   ↓
Next.js ─────→ R2

Service: R2StorageService

6. Kategori
Method	Endpoint	Fungsi
GET	/categories	List kategori
POST	/categories	Tambah
PUT	/categories/{id}	Update
DELETE	/categories/{id}	Hapus

Service: CategoryService

7. Surat
Method	Endpoint	Fungsi
GET	/letters	List surat
POST	/letters	Tambah surat
GET	/letters/{id}	Detail surat
PUT	/letters/{id}	Update surat
DELETE	/letters/{id}	Hapus surat

Gunakan parameter:

?type=incoming
?type=outgoing

Service: LetterService

8. Laporan
Method	Endpoint	Fungsi
GET	/reports/documents	Laporan dokumen
GET	/reports/mitras	Laporan mitra
GET	/reports/uploads	Laporan upload
GET	/reports/downloads	Laporan download
GET	/reports/storage	Laporan storage
POST	/reports/export	Generate export

Service: ReportService

Untuk export besar → Queue Job.

9. Recycle Bin
Method	Endpoint	Fungsi
GET	/recycle-bin	List data terhapus
POST	/recycle-bin/{id}/restore	Restore
DELETE	/recycle-bin/{id}	Permanent delete

Service: RecycleBinService

10. User & Permission
Method	Endpoint	Fungsi
GET	/users	List user
POST	/users	Tambah user
PUT	/users/{id}	Update user
DELETE	/users/{id}	Hapus user
GET	/roles	List role
POST	/roles	Tambah role
PUT	/roles/{id}	Update role
GET	/permissions	List permission

Service:

UserService
PermissionService
11. Activity Log
Method	Endpoint	Fungsi
GET	/activity-logs	Riwayat aktivitas

Service: ActivityLogService

Aktivitas utama:

LOGIN
UPLOAD
DOWNLOAD
CREATE
UPDATE
DELETE
RESTORE
EXPORT
Service Layer Final
Services/
├── Auth/
│   └── AuthService.php
│
├── Dashboard/
│   └── DashboardService.php
│
├── Mitra/
│   └── MitraService.php
│
├── Document/
│   ├── DocumentService.php
│   └── DocumentQueryService.php
│
├── Storage/
│   └── R2StorageService.php
│
├── Category/
│   └── CategoryService.php
│
├── Letter/
│   └── LetterService.php
│
├── Report/
│   └── ReportService.php
│
├── RecycleBin/
│   └── RecycleBinService.php
│
├── User/
│   └── UserService.php
│
├── Permission/
│   └── PermissionService.php
│
└── ActivityLog/
    └── ActivityLogService.php
Core flow
Controller
    ↓
Request Validation
    ↓
Service
    ↓
Model / Query
    ↓
Database

Untuk file:

Next.js
    ↓
R2StorageService
    ↓
Presigned URL
    ↓
Cloudflare R2

Ini sudah cukup sebagai API contract awal. Detail request/response bisa dibuat nanti setelah struktur database-nya sudah fix.