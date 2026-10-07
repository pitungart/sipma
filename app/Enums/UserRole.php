<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Agent = 'agent';
    case Student = 'student';

    public function getLabel(): string
    {
        return __("enums.role.{$this->value}");
    }

    /**
     * Role staf universitas, yaitu yang dikelola di panel /admin.
     *
     * @return list<self>
     */
    public static function adminRoles(): array
    {
        return [self::SuperAdmin, self::Admin];
    }
}
