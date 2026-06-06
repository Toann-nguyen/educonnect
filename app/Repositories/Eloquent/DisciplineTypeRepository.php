<?php

namespace App\Repositories\Eloquent;

use App\Models\DisciplineType;
use App\Repositories\Contracts\DisciplineTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class DisciplineTypeRepository implements DisciplineTypeRepositoryInterface
{
    protected $model;

    public function __construct(DisciplineType $model)
    {
        $this->model = $model;
    }

    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by severity level
        if (isset($filters['severity_level'])) {
            $query->where('severity_level', $filters['severity_level']);
        }

        // Search by name or code
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('severity_level')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 50);
    }

    public function getAllActive(): Collection
    {
        return $this->model->where('is_active', true)
            ->orderBy('severity_level')
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?DisciplineType
    {
        return $this->model->find($id);
    }

    public function findByCode(string $code): ?DisciplineType
    {
        return $this->model->where('code', $code)->first();
    }

    public function create(array $data): DisciplineType
    {
        try {
            $disciplineType = $this->model->create($data);
            Log::info('DisciplineType created successfully.', ['id' => $disciplineType->id]);
            return $disciplineType;
        } catch (\Exception $e) {
            Log::error('Failed to create DisciplineType.', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function update(int $id, array $data): DisciplineType
    {
        try {
            $disciplineType = $this->findById($id);
            if (!$disciplineType) {
                throw new \Exception('DisciplineType not found.');
            }

            $disciplineType->update($data);
            Log::info('DisciplineType updated successfully.', ['id' => $id]);
            return $disciplineType->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to update DisciplineType.', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $result = $this->model->destroy($id) > 0;
            Log::info('DisciplineType deleted successfully.', ['id' => $id]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to delete DisciplineType.', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw $e;
        }
    }

    public function findTrashedById(int $id): ?DisciplineType
    {
        return $this->model->onlyTrashed()->find($id);
    }

    public function restore(int $id): bool
    {
        $disciplineType = $this->findTrashedById($id);
        if ($disciplineType) {
            return $disciplineType->restore();
        }
        return false;
    }
}
