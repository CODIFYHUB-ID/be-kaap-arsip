<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebtorCreditorConfirmation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'package_number',
        'confirmation_type',
        'parent_id',
        'version',
        'mitra_id',
        'client_name',
        'client_letterhead_url',
        'client_signature_stamp_url',
        'show_client_stamp',
        'letter_city',
        'letter_date',
        'subject',
        'balance_date',
        'response_deadline_days',
        'auditor_pic_name',
        'auditor_pic_phone',
        'auditor_correspondence_address',
        'client_signatory_name',
        'client_signatory_title',
        'show_cut_line',
        'show_note',
        'note_text',
        'total_nominal',
        'general_ledger_nominal',
        'status',
        'created_by',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'version' => 'integer',
        'response_deadline_days' => 'integer',
        'show_client_stamp' => 'boolean',
        'show_cut_line' => 'boolean',
        'show_note' => 'boolean',
        'total_nominal' => 'decimal:2',
        'general_ledger_nominal' => 'decimal:2',
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
        return $this->belongsTo(DebtorCreditorConfirmation::class, 'parent_id');
    }

    public function revisions()
    {
        return $this->hasMany(DebtorCreditorConfirmation::class, 'parent_id')->orderBy('version', 'desc');
    }

    public function recipients()
    {
        return $this->hasMany(DebtorCreditorRecipient::class, 'confirmation_id')->orderBy('order', 'asc');
    }
}
