<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mitra_id',
        'category_id',
        'file_key',
        'file_name',
        'file_size',
        'mime_type',
        'extension',
        'description',
        'tahun_berkas',
        'tanggal_dokumen',
        'uploaded_by',
        'approval_status',
        'is_final_archive',
        'approved_by',
        'approved_at',
        'approval_notes',
        'is_working_paper',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'tahun_berkas' => 'string',
        'tanggal_dokumen' => 'date',
        'approved_at' => 'datetime',
        'is_final_archive' => 'boolean',
        'is_working_paper' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
