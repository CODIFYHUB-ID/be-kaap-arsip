<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalAudit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'physical_audits';

    protected $fillable = [
        'audit_number',
        'type', // 'cash', 'persediaan', 'aset_tetap'
        'mitra_id',
        'client_name',
        'audit_period',
        'day',
        'audit_date',
        'location_or_cashier',
        'time_start',
        'time_end',
        'audit_data',
        'total_amount',
        'total_items_count',
        'status',
        'r2_file_key',
        'r2_file_url',
        'archived_document_id',
        'pic_name',
        'pic_title',
        'auditor_name',
        'auditor_title',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'audit_data' => 'array',
        'audit_date' => 'date:Y-m-d',
        'total_amount' => 'decimal:2',
        'total_items_count' => 'integer',
    ];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }

    public function archivedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'archived_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PhysicalAuditItem::class, 'physical_audit_id')->orderBy('sort_order', 'asc');
    }
}
