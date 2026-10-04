<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankConfirmation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'confirmation_number',
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
        'bank_name',
        'bank_address',
        'balance_date',
        'client_signatory_name',
        'client_signatory_title',
        'confirmation_date',
        'fill_mode_giro',
        'fill_mode_deposito',
        'fill_mode_loan',
        'fill_mode_other',
        'bank_signatory_city',
        'bank_signatory_title',
        'bank_signatory_name',
        'show_note',
        'note_text',
        'status',
        'sent_date',
        'received_date',
        'created_by',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'sent_date' => 'date',
        'received_date' => 'date',
        'show_client_stamp' => 'boolean',
        'fill_mode_giro' => 'boolean',
        'fill_mode_deposito' => 'boolean',
        'fill_mode_loan' => 'boolean',
        'fill_mode_other' => 'boolean',
        'show_note' => 'boolean',
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
        return $this->belongsTo(BankConfirmation::class, 'parent_id');
    }

    public function revisions()
    {
        return $this->hasMany(BankConfirmation::class, 'parent_id')->orderBy('version', 'desc');
    }

    public function items()
    {
        return $this->hasMany(BankConfirmationItem::class, 'bank_confirmation_id')->orderBy('order', 'asc');
    }
}
