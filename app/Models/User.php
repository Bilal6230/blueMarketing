<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use \App\Models\Concerns\PreservesDeletedRecords;
    use \App\Models\Concerns\ArchivesAssignments;
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles {
        syncRoles as private syncRolesOriginal;
        syncPermissions as private syncPermissionsOriginal;
    }

    public function syncRoles(...$roles): static
    {
        if (!$this->exists) {
            return $this->syncRolesOriginal(...$roles);
        }
        return $this->getConnection()->transaction(function () use ($roles) {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            return $this->syncRolesOriginal(...$roles);
        });
    }

    public function syncPermissions(...$permissions): static
    {
        if (!$this->exists) {
            return $this->syncPermissionsOriginal(...$permissions);
        }
        return $this->getConnection()->transaction(function () use ($permissions) {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            return $this->syncPermissionsOriginal(...$permissions);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'gender',
        'nic_number',
        'phone_number',
        'department',
        'status_id',
        'project_id',
        'address',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function Leads()
    {
        return $this->belongsToMany(Lead::class, 'lead_user');
    }

    public function works()
    {
        return $this->hasMany(Work::class);
    }
    public function ledgers()
    {
        return $this->hasMany(Ledger::class,'create_by','id');
    }
}
