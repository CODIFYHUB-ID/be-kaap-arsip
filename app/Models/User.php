<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'mitra_id',
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function isMitra(): bool
    {
        return $this->hasRole('Mitra');
    }

    public function isKlien(): bool
    {
        return $this->hasRole('Klien') || (!empty($this->mitra_id) && !$this->hasRole('Mitra'));
    }

    public function isAuditor(): bool
    {
        return $this->hasRole('Auditor');
    }

    public function auditorAssignments(): HasMany
    {
        return $this->hasMany(AuditorAssignment::class, 'auditor_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function hasPermission(string $permissionName): bool
    {
        if (! $this->role) {
            return false;
        }

        if ($this->role->name === 'Super Admin') {
            return true;
        }

        return $this->role->permissions->contains('name', $permissionName);
    }
}
