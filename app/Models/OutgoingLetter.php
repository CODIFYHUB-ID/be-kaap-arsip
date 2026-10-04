<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutgoingLetter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'letter_type',
        'letter_number',
        'parent_id',
        'version',
        'mitra_id',
        'client_name',
        'client_legal_entity',
        'client_address_street',
        'client_address_kelurahan',
        'client_address_kecamatan',
        'client_address_city',
        'client_address_province',
        'client_address_postal_code',
        'client_address_full',
        'letter_date',
        'city',
        'subject',
        'audit_type',
        'fiscal_year_end_date',
        'amount',
        'terbilang',
        'tax_note',
        'tax_note_enabled',
        'salutation',
        'fiscal_year',
        'target_completion_date',
        'signatory_name',
        'signatory_title',
        'signature_stamp_url',
        'show_signature_stamp',
        'signature_stamp_width',
        'status',
        'created_by',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'amount' => 'decimal:2',
        'tax_note_enabled' => 'boolean',
        'show_signature_stamp' => 'boolean',
        'signature_stamp_width' => 'integer',
        'version' => 'integer',
    ];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent()
    {
        return $this->belongsTo(OutgoingLetter::class, 'parent_id');
    }

    public function revisions()
    {
        return $this->hasMany(OutgoingLetter::class, 'parent_id')->orderBy('version', 'desc');
    }
}
