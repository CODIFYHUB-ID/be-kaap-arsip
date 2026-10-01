<?php

namespace App\Models;

use App\Enums\MitraStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mitra extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'created_by',
        'code',
        'name',
        'company_name',
        'address',
        'phone',
        'email',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => MitraStatus::class,
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
