<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientBank extends Model
{
    use HasFactory;

    protected $fillable = [
        'mitra_id',
        'bank_name',
        'bank_address',
        'account_number',
    ];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }
}
