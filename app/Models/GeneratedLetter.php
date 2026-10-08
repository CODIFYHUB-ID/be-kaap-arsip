<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneratedLetter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'template_id',
        'mitra_id',
        'letter_type',
        'letter_number',
        'client_name',
        'audit_type',
        'period_end_date',
        'assigned_auditors',
        'kop_type',
        'letter_date',
        'city',
        'signatory_name',
        'signatory_title',
        'signature_stamp_url',
        'show_signature_stamp',
        'signature_stamp_width',
        'opening_text',
        'scope_text',
        'closing_text',
        'body_html',
        'status',
        'created_by',
    ];

    protected $casts = [
        'assigned_auditors' => 'array',
        'letter_date' => 'date',
        'show_signature_stamp' => 'boolean',
        'signature_stamp_width' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
