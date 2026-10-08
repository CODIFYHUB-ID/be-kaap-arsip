<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'audit_contracts';

    protected $fillable = [
        'contract_number',
        'contract_date',
        'contract_date_text',
        'city',
        'ref_text',
        'mitra_id',
        'client_name',
        'client_address',
        'client_pic_name',
        'client_pic_title',
        'accounting_standard',
        'period_end_date',
        'fee_amount',
        'fee_terbilang',
        'report_copies',
        'payment_terms',
        'accommodation_note',
        'kop_type',
        'signatory_name',
        'signatory_title',
        'signature_stamp_url',
        'show_signature_stamp',
        'signature_stamp_width',
        'custom_html',
        'status',
        'created_by',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'fee_amount' => 'decimal:2',
        'report_copies' => 'integer',
        'payment_terms' => 'array',
        'show_signature_stamp' => 'boolean',
        'signature_stamp_width' => 'integer',
    ];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
