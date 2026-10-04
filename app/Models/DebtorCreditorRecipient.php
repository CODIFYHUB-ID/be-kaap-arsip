<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtorCreditorRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'confirmation_id',
        'order',
        'recipient_name',
        'recipient_type',
        'recipient_address',
        'nominal',
        'signatory_title',
        'status',
        'sent_date',
        'response_date',
        'difference_notes',
    ];

    protected $casts = [
        'order' => 'integer',
        'nominal' => 'decimal:2',
        'sent_date' => 'date',
        'response_date' => 'date',
    ];

    public function confirmation()
    {
        return $this->belongsTo(DebtorCreditorConfirmation::class, 'confirmation_id');
    }
}
