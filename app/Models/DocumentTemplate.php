<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'title',
        'description',
        'kop_type',
        'number_format',
        'opening_text',
        'scope_text',
        'closing_text',
        'signatory_city',
        'signatory_name',
        'signatory_title',
        'body_html',
        'content',
        'fields_schema',
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
