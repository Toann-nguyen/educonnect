<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;

class PermissionRepository implements PermissionRepositoryInterface
{
    protected $model;

    public function __construct(Permission $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->orderBy('category')->orderBy('name')->get();
    }

    public function getByCategory(string $category)
    {
        return $this->model
            ->where('category', $category)
            ->orderBy('name')
            ->get();
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
        $permission = $this->findById($id);
        if ($permission) {
            $permission->update($data);
        }
        return $permission;
    }

    public function delete(int $id): bool
    {
        $permission = $this->findById($id);
        return $permission ? $permission->delete() : false;
    }

    public function getCategories()
    {
        return $this->model->select('category')
            ->distinct()
            ->pluck('category');
    }
}
