<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class RoleRepository implements RoleRepositoryInterface
{
    protected $model;

    public function __construct(Role $model)
    {
        $this->model = $model;
    }

    public function paginate(int $perPage = 15, array $filters = [])
    {
        $query = $this->model->query();
        
        // Filter by search
         $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($subQ) use ($search) {
                $subQ->where('name', 'LIKE', "%{$search}%")
                     ->orWhere('description', 'LIKE', "%{$search}%");
            });
        });

        $query->when(isset($filters['is_active']), function ($q) use ($filters) {
            $q->where('is_active', $filters['is_active']);
        });


        $query->with(['permissions']); // Eager load danh sách permissions chi tiết
        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    public function findById(int $id)
    {
        return $this->model->find($id);
    }

    public function findByName(string $name)
    {
        return $this->model->where('name', $name)->first();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data)
    {

        $role = $this->findById($id);
        if ($role) {
            $role->update($data);
        }
        return $role;
    }

    public function delete(int $id): bool
    {
        $role = $this->findById($id);
        return $role ? $role->delete() : false;
    }

    public function forceDelete(int $id): bool
    {
        $role = Role::withTrashed()->find($id);
        return $role ? $role->forceDelete() : false;
    }

    public function getWithPermissions(int $id)
    {
        return $this->model->with(['permissions', 'users'])->find($id);
    }

    public function getUsersCount(int $roleId): int
    {
        return DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', 'App\\Models\\User')
            ->count();
    }
}
