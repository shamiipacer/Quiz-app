<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class AdminUser extends Authenticatable
{
    protected $guarded = [];

    protected $attributes = ['active' => true];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean'];
    }

    public function permits(string $permission): bool
    {
        return DB::table('admin_permission_role')->join('admin_permissions', 'admin_permissions.id', '=', 'admin_permission_role.admin_permission_id')->where('admin_role_id', $this->admin_role_id)->where('admin_permissions.name', $permission)->exists();
    }
}
