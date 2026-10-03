<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_slug',
        'title',
        'code',
        'description',
        'content',
        'fields_schema',
        'default_letterhead_id',
        'kop_type',
        'number_format',
        'opening_text',
        'scope_text',
        'closing_text',
        'signatory_city',
        'signatory_name',
        'signatory_title',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'fields_schema' => 'array',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generatedLetters()
    {
        return $this->hasMany(GeneratedLetter::class, 'template_id');
    }
}
