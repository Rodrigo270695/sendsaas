<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use App\Notifications\Auth\PasswordResetLinkNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_active
 * @property bool $must_change_password
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $last_seen_at
 */
#[Fillable([
    'tenant_id',
    'name',
    'email',
    'phone',
    'documento_tipo',
    'documento_numero',
    'colegiatura',
    'cv_path',
    'dni_file_path',
    'firma_path',
    'password',
    'is_active',
    'must_change_password',
    'bootstrap_login_token',
    'bootstrap_login_expires_at',
    'last_login_at',
    'created_by_id',
])]
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
    'bootstrap_login_token',
    'cv_path',
    'dni_file_path',
    'firma_path',
])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable, UsesPublicSchema;

    private ?bool $isPlatformSuperadminMemo = null;

    /**
     * @var list<string>
     */
    protected $appends = [
        'first_name',
        'display_name',
        'cv_url',
        'dni_file_url',
        'firma_url',
        'demo_locked',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'bootstrap_login_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_path_at' => 'datetime',
        ];
    }

    public function getFirstNameAttribute(): string
    {
        $name = trim((string) ($this->attributes['name'] ?? ''));
        if ($name === '') {
            return '';
        }

        return explode(' ', $name)[0];
    }

    public function getDisplayNameAttribute(): string
    {
        return trim((string) ($this->attributes['name'] ?? ''));
    }

    public function getCvUrlAttribute(): ?string
    {
        return $this->publicDiskUrl($this->cv_path);
    }

    public function getDniFileUrlAttribute(): ?string
    {
        return $this->publicDiskUrl($this->dni_file_path);
    }

    public function getFirmaUrlAttribute(): ?string
    {
        return $this->publicDiskUrl($this->firma_path);
    }

    public function getDemoLockedAttribute(): bool
    {
        return is_demo_protected_user($this);
    }

    private function publicDiskUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return asset('storage/'.ltrim((string) $path, '/'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PasswordResetLinkNotification($token));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isCentral(): bool
    {
        return $this->tenant_id === null;
    }

    public function isTenantUser(): bool
    {
        return $this->tenant_id !== null;
    }

    public function belongsToTenant(?string $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    public function isPlatformSuperadmin(): bool
    {
        if ($this->isPlatformSuperadminMemo !== null) {
            return $this->isPlatformSuperadminMemo;
        }

        if (! $this->isCentral()) {
            return $this->isPlatformSuperadminMemo = false;
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $this->unsetRelation('roles');

            return $this->isPlatformSuperadminMemo = $this->hasRole('superadmin');
        } finally {
            setPermissionsTeamId($previousTeam);
            $this->unsetRelation('roles');
        }
    }
}
