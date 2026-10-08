<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalAuditItem extends Model
{
    use HasFactory;

    protected $table = 'physical_audit_items';

    protected $fillable = [
        'physical_audit_id',
        'category',
        'item_name',
        'unit',
        'nominal',
        'quantity',
        'subtotal',
        'condition_good',
        'condition_bad',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'quantity' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'condition_good' => 'boolean',
        'condition_bad' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function physicalAudit(): BelongsTo
    {
        return $this->belongsTo(PhysicalAudit::class, 'physical_audit_id');
    }
}
