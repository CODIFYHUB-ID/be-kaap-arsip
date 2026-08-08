Backend Architecture Document
Sistem Arsip Dokumen & Surat

Version: 1.0
Backend: Laravel API
Frontend: Next.js
Database: PostgreSQL / MySQL
File Storage: Cloudflare R2
Authentication: Laravel Sanctum
Architecture: Modular Monolith REST API

1. Tujuan

Backend bertanggung jawab untuk:

Authentication & authorization
RBAC / Role & Permission
Manajemen Mitra
Manajemen Dokumen
Manajemen Surat
Metadata dokumen
Generate Presigned URL R2
Laporan
Recycle Bin
Activity Log
System Settings

Backend tidak digunakan sebagai jalur upload file utama.

2. Arsitektur
Next.js
   │
   │ REST API
   ▼
Laravel API
   │
   ├── PostgreSQL / MySQL
   │
   └── Presigned URL
          │
          ▼
     Cloudflare R2
Upload
Next.js ───────────────→ R2
          Direct Upload

Laravel hanya membuat Presigned URL, melakukan validasi, authorization, dan menyimpan metadata file.

Download
Next.js
   ↓
Laravel
   ↓
Check Permission
   ↓
Presigned URL
   ↓
R2
   ↓
User
3. Batasan Backend
File
File tidak disimpan di database.
File tidak melewati Laravel pada proses upload normal.
Credential R2 tidak boleh dikirim ke frontend.
Upload menggunakan Presigned URL.
Download menggunakan Presigned URL.
Database

Database hanya menyimpan:

Metadata file
Data mitra
Data surat
User
Role & permission
Activity log
Relasi antar data
API
Menggunakan REST API.
API menggunakan versioning /api/v1.
Response JSON harus konsisten.
Semua endpoint private wajib menggunakan authentication.
Semua data list wajib menggunakan pagination.
Filtering dilakukan di database.
4. Struktur Folder
app/
├── Enums/
├── Models/
│
├── Http/
│   ├── Controllers/Api/
│   │   ├── Auth/
│   │   ├── Dashboard/
│   │   ├── Mitra/
│   │   ├── Document/
│   │   ├── Letter/
│   │   ├── Report/
│   │   ├── RecycleBin/
│   │   ├── Setting/
│   │   └── ActivityLog/
│   │
│   ├── Requests/
│   └── Resources/
│
├── Policies/
│
├── Services/
│   ├── Auth/
│   ├── Dashboard/
│   ├── Mitra/
│   ├── Document/
│   ├── Letter/
│   ├── Storage/
│   ├── Report/
│   ├── RecycleBin/
│   ├── User/
│   ├── Permission/
│   ├── Category/
│   ├── ActivityLog/
│   └── System/
│
├── Jobs/
└── Support/
5. Service Layer
Service	Fungsi
AuthService	Login & authentication
DashboardService	Statistik dashboard
MitraService	CRUD Mitra
DocumentService	Business logic dokumen
DocumentQueryService	Search, filter & pagination
LetterService	Surat masuk & keluar
R2StorageService	Presigned URL & operasi R2
ReportService	Laporan & export
RecycleBinService	Restore & permanent delete
UserService	Manajemen user
PermissionService	Role & permission
CategoryService	Kategori dokumen
ActivityLogService	Audit aktivitas
SystemSettingService	Pengaturan sistem

Controller hanya menangani:

Request
   ↓
Validation
   ↓
Service
   ↓
Resource
   ↓
JSON Response

Business logic tidak ditaruh di Controller.

6. Modul Backend

Backend mencakup modul:

Dashboard

Mitra
├── Daftar Mitra
├── Dokumen Mitra
├── Surat Keterangan
├── Surat Penawaran Audit
├── Surat Kontrak
└── Kwitansi

Dokumen
├── Inventory Berkas
└── Kategori Berkas

Surat
├── Surat Masuk
└── Surat Keluar

Laporan
├── Dokumen
├── Mitra
├── Upload
├── Download
└── Storage

Recycle Bin

Pengaturan
├── User
├── Role & Permission
├── Kategori
└── Sistem

Activity Log
7. Authentication & Authorization

Menggunakan:

Laravel Sanctum + RBAC + Policy

Struktur:

User
 ↓
Role
 ↓
Permission
 ↓
Policy
 ↓
Resource

Contoh permission:

document.view
document.create
document.update
document.delete
document.download

mitra.view
mitra.create
mitra.update
mitra.delete

report.view
report.export

user.view
user.create
user.update
user.delete
8. Storage Architecture

Cloudflare R2 digunakan khusus untuk menyimpan file.

R2
└── documents/
    └── 2026/
        └── 08/
            └── {uuid}.pdf

Database menyimpan:

file_key
file_name
file_size
mime_type
extension
Upload Flow
1. FE meminta Presigned URL
2. Laravel validasi permission
3. Laravel membuat Presigned URL
4. FE upload langsung ke R2
5. FE mengirim metadata ke Laravel
6. Laravel menyimpan metadata ke database
7. Activity Log dibuat
9. Performance

Backend harus menerapkan:

Pagination
Database indexing
Query Builder / Eloquent secara efisien
Eager loading untuk menghindari N+1
API Resource untuk membatasi payload
Cache untuk data yang sesuai
Queue untuk proses berat
Rate limiting
Direct upload ke R2
Jangan
Ambil 10.000 data
↓
Filter di PHP
Gunakan
Database
↓
WHERE
↓
ORDER BY
↓
LIMIT
↓
Pagination
10. Recycle Bin

Dokumen menggunakan Soft Delete.

Delete
  ↓
deleted_at
  ↓
Recycle Bin

User dengan permission dapat:

Restore
Permanent Delete

Permanent delete akan menghapus:

Database Record
+
R2 Object
11. Activity Log

Aktivitas penting wajib dicatat:

LOGIN
LOGOUT

CREATE
UPDATE
DELETE
RESTORE

UPLOAD
DOWNLOAD
VIEW
EXPORT

CHANGE_PERMISSION

Data minimal:

user_id
action
module
resource_id
description
ip_address
user_agent
created_at
12. API Convention

Base URL:

/api/v1

Contoh:

POST   /api/v1/auth/login

GET    /api/v1/mitras
POST   /api/v1/mitras
GET    /api/v1/mitras/{id}
PUT    /api/v1/mitras/{id}
DELETE /api/v1/mitras/{id}

GET    /api/v1/documents
POST   /api/v1/documents

POST   /api/v1/storage/presign

GET    /api/v1/letters
POST   /api/v1/letters

GET    /api/v1/reports

GET    /api/v1/activity-logs
13. Standard Response
Success
{
    "success": true,
    "message": "Data retrieved successfully.",
    "data": {}
}
List
{
    "success": true,
    "message": "Documents retrieved successfully.",
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 100
    }
}
Error
{
    "success": false,
    "message": "You do not have permission.",
    "errors": {}
}
14. Queue & Background Process

Queue digunakan untuk proses yang berat:

Generate Report
Export Excel/PDF
Storage Calculation
Cleanup R2
Notification

Request API tidak boleh menunggu proses berat selesai jika tidak diperlukan.

15. Deployment

Production:

                    Internet
                       │
                       ▼
                     Nginx
                       │
              ┌────────┴────────┐
              ▼                 ▼
           Next.js           Laravel
             │                  │
            PM2             Octane*
                                │
                    ┌───────────┼───────────┐
                    ▼           ▼           ▼
                 Database     Redis*        R2

Octane dan Redis bersifat opsional pada tahap awal dan dapat ditambahkan ketika memang diperlukan.

16. Prinsip Utama Backend

Laravel menangani logic dan keamanan, Database menyimpan metadata, sedangkan Cloudflare R2 menyimpan file.

Arsitektur menggunakan modular monolith, bukan microservices.

Next.js
   ↓
Laravel API
   ↓
Service Layer
   ↓
Database

Next.js
   ↓
Presigned URL
   ↓
Cloudflare R2