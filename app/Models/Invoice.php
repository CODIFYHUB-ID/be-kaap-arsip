<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'parent_id',
        'version',
        'mitra_id',
        'client_name',
        'client_address',
        'client_city',
        'invoice_date',
        'subtotal',
        'tax_pph23_enabled',
        'tax_pph23_percent',
        'tax_pph23_amount',
        'total_amount',
        'terbilang',
        'total_label',
        'signatory_city',
        'signatory_name',
        'signatory_title',
        'signatory_signature_url',
        'footer_nb',
        'status',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_pph23_enabled' => 'boolean',
        'tax_pph23_percent' => 'decimal:2',
        'tax_pph23_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'footer_nb' => 'array',
        'version' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id')->orderBy('item_order', 'asc');
    }

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
        return $this->belongsTo(Invoice::class, 'parent_id');
    }

    public function revisions()
    {
        return $this->hasMany(Invoice::class, 'parent_id')->orderBy('version', 'desc');
    }
}
