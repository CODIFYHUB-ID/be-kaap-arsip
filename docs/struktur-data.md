1. Struktur Relasi Utama
users
  │
  ├── roles ─── permissions
  │
  └── activity_logs

mitras
  │
  ├── documents ─── categories
  │
  └── letters

documents
  │
  └── categories

documents / letters
        │
        └── R2 Storage
2. Users
users
id
role_id
name
email
password
status
last_login_at
created_at
updated_at
deleted_at

Status:

active
inactive

Relasi:

users.role_id → roles.id
3. Roles
roles
id
name
description
created_at
updated_at

Contoh:

Super Admin
Admin
Staff
Viewer
4. Permissions
permissions
id
name
module
description
created_at
updated_at

Contoh:

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
5. Role Permissions

Karena satu role bisa memiliki banyak permission:

role_permissions
role_id
permission_id

Relasi:

roles
  ↓
role_permissions
  ↓
permissions
6. Mitra
mitras
id
code
name
company_name
address
phone
email
status
notes
created_at
updated_at
deleted_at

Contoh:

MITRA-001
PT ABC Indonesia

Status:

active
inactive
7. Categories
categories
id
name
description
created_at
updated_at
deleted_at

Contoh:

Surat Kontrak
Surat Penawaran
Kwitansi
Surat Keterangan
Laporan Audit
Dokumen Legal
8. Documents

Ini tabel utama untuk inventory berkas.

documents
id
mitra_id
category_id

file_key
file_name
file_size
mime_type
extension

description

uploaded_by

created_at
updated_at
deleted_at

Relasi:

documents.mitra_id
        ↓
     mitras

documents.category_id
        ↓
    categories

documents.uploaded_by
        ↓
      users
Contoh
id          = 1001
mitra_id    = 5
category_id = 2

file_key    = documents/2026/08/a82f.pdf
file_name   = Surat-Kontrak-ABC.pdf
file_size   = 5242880
mime_type   = application/pdf
extension   = pdf
9. Letters

Untuk surat masuk dan surat keluar, cukup satu tabel.

letters
id
mitra_id
category_id

type

letter_number
subject

letter_date
received_date

description

document_id

created_by

created_at
updated_at
deleted_at

type:

incoming
outgoing

document_id digunakan jika surat memiliki file fisik yang tersimpan di documents.

Relasi:

letters
   │
   ├── mitra
   ├── category
   ├── document
   └── created_by
10. Activity Logs
activity_logs
id
user_id

action
module

resource_type
resource_id

description

ip_address
user_agent

created_at

Contoh:

user_id       = 5
action        = DOWNLOAD
module        = DOCUMENT
resource_type = Document
resource_id   = 1001

description   = Download Surat Kontrak ABC

Tidak perlu updated_at karena log bersifat immutable.

11. System Settings
system_settings
id
key
value
type
description
created_at
updated_at

Contoh:

key   = max_upload_size
value = 52428800
type  = integer

Atau:

key   = company_name
value = PT ABC Indonesia
type  = string
12. Recycle Bin

Tidak perlu tabel recycle_bins.

Gunakan Laravel:

deleted_at

pada tabel:

documents
mitras
letters
categories
users

Flow:

DELETE
   ↓
deleted_at
   ↓
Recycle Bin

Restore:

deleted_at = NULL

Permanent delete:

Database
   +
R2 Object
13. Storage di R2

Database tidak menyimpan file.

Database:

documents
├── file_key
├── file_name
├── file_size
├── mime_type
└── extension

R2:

documents/
├── 2026/
│   ├── 08/
│   │   ├── uuid-1.pdf
│   │   ├── uuid-2.docx
│   │   └── uuid-3.xlsx

file_key menjadi penghubung database dengan object R2.

14. Relationship
User
User
 ├── belongsTo Role
 ├── hasMany Documents
 └── hasMany ActivityLogs
Role
Role
 ├── hasMany Users
 └── belongsToMany Permissions
Mitra
Mitra
 ├── hasMany Documents
 └── hasMany Letters
Document
Document
 ├── belongsTo Mitra
 ├── belongsTo Category
 └── belongsTo User (uploaded_by)
Letter
Letter
 ├── belongsTo Mitra
 ├── belongsTo Category
 ├── belongsTo Document
 └── belongsTo User
15. Index Database

Untuk performa, tambahkan index pada:

users.email
users.role_id

documents.mitra_id
documents.category_id
documents.uploaded_by
documents.created_at
documents.deleted_at

letters.mitra_id
letters.category_id
letters.type
letters.letter_date
letters.created_at

activity_logs.user_id
activity_logs.module
activity_logs.action
activity_logs.created_at

Untuk pencarian:

mitras.name
mitras.code
documents.file_name
letters.letter_number
letters.subject
16. Final Database Structure

Jadi total tabel utama:

users
roles
permissions
role_permissions

mitras

categories
documents
letters

activity_logs
system_settings

Total: 10 tabel utama.

Strukturnya cukup untuk fitur yang sekarang tanpa membuat database terlalu kompleks.

Gambaran akhirnya
                    ┌─────────────┐
                    │    roles    │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │    users    │
                    └──┬──────┬───┘
                       │      │
             uploaded  │      │ activity
                       │      │
                       ▼      ▼
                  documents  activity_logs
                    │   │
                    │   └────── categories
                    │
              ┌─────▼─────┐
              │   mitras  │
              └─────┬─────┘
                    │
                    ▼
                  letters

documents
    │
    │ file_key
    ▼
Cloudflare R2