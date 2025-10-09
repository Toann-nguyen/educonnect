<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'description',
        'category',
        'guard_name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'guard_name' => 'api',
    ];

    /**
     * Relationships
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_has_permissions',
            'permission_id',
            'role_id'
        );
    }

    /**
     * Scopes
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Mutators
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtolower($value);
    }

    /**
     * Methods
     */
    public function getFullName(): string
    {
        return "{$this->name} ({$this->category})";
    }
}
