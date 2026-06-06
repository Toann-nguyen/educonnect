<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PermissionRepository implements PermissionRepositoryInterface
{
    protected $model;

    public function __construct(Permission $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy permissions có phân trang
     */
    public function all(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Filter by search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Tìm permission theo ID
     */
    public function findById(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * Tìm permission theo name
     */
    public function findByName(string $name)
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * Tạo permission mới
     */
    public function create(array $data)
    {
        try {
            DB::beginTransaction();
            $permission = $this->model->create($data);
            DB::commit();
            return $permission;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cập nhật permission
     */
    public function update(int $id, array $data)
    {
        // dd($id , $data);
        try {
            DB::beginTransaction();

            $permission = $this->findById($id);
     
            if (!$permission) {
                DB::rollBack();
                throw new \Exception("Permission not found", 404);
            }

            $permission->update($data);

            DB::commit();
            return $permission->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Xóa permission
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $permission = $this->findById($id);
            
            if (!$permission) {
                DB::rollBack();
                return false;
            }

            // Xóa các liên kết với roles
            DB::table('role_has_permissions')
                ->where('permission_id', $id)
                ->delete();

            // Xóa các liên kết với users
            DB::table('model_has_permissions')
                ->where('permission_id', $id)
                ->delete();

            $result = $permission->delete();

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Kiểm tra permission đang được sử dụng bởi bao nhiêu roles/users
     */
    public function getUsageCount(int $id): array
    {
        $byRoles = DB::table('role_has_permissions')
            ->where('permission_id', $id)
            ->count();

        $byUsers = DB::table('model_has_permissions')
            ->where('permission_id', $id)
            ->where('model_type', 'App\\Models\\User')
            ->count();

        return [
            'by_roles' => $byRoles,
            'by_users' => $byUsers,
            'total' => $byRoles + $byUsers
        ];
    }
}