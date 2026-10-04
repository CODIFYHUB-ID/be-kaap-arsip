<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankConfirmationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_confirmation_id',
        'category',
        'col_1',
        'amount_1',
        'col_2',
        'col_3',
        'amount_2',
        'remarks',
        'order',
    ];

    protected $casts = [
        'amount_1' => 'decimal:2',
        'amount_2' => 'decimal:2',
        'order' => 'integer',
    ];

    public function confirmation()
    {
        return $this->belongsTo(BankConfirmation::class, 'bank_confirmation_id');
    }
}
