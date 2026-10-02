<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mitra_id',
        'receipt_number',
        'input_mode',
        'receipt_type',
        'payment_method',
        'payer_name',
        'category_transaction',
        'bank_account_destination',
        'subtotal',
        'tax_pph23_percent',
        'tax_pph23_amount',
        'tax_ppn_percent',
        'tax_ppn_amount',
        'amount',
        'terbilang',
        'transaction_date',
        'description',
        'file_key',
        'file_name',
        'file_size',
        'mime_type',
        'extension',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_pph23_percent' => 'decimal:2',
        'tax_pph23_amount' => 'decimal:2',
        'tax_ppn_percent' => 'decimal:2',
        'tax_ppn_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'file_size' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class)->orderBy('item_order');
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
