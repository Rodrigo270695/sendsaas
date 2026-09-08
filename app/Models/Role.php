<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 * @property string|null $tenant_id
 * @property-read bool $is_system
 */
class Role extends SpatieRole
{
    public const PLATFORM_ROLES = ['superadmin'];

    public const BASE_TENANT_ROLES = [
        'admin_empresa',
        'supervisor',
        'agente',
    ];

    /**
     * @var list<string>
     */
    public const SYSTEM_ROLES = [
        'superadmin',
        'admin_empresa',
        'supervisor',
        'agente',
    ];

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'tenant_id',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'is_system',
    ];

    /**
     * @return list<string>
     */
    public static function protectedRoleNames(): array
    {
        return self::SYSTEM_ROLES;
    }

    protected function isSystem(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => in_array($this->name, self::protectedRoleNames(), true),
        );
    }
}
