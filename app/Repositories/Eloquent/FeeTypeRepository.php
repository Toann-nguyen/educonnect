<?php

namespace App\Repositories\Eloquent;

use App\Models\FeeType;
use App\Repositories\Contracts\FeeTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FeeTypeRepository implements FeeTypeRepositoryInterface
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = FeeType::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }

        return $query->orderBy('name')->paginate($filters['per_page'] ?? 15);
    }

    public function findById(int $id): ?FeeType
    {
        return FeeType::find($id);
    }

    public function create(array $data): FeeType
    {
        return FeeType::create($data);
    }

    public function update(int $id, array $data): FeeType
    {
        $feeType = $this->findById($id);
        $feeType->update($data);
        return $feeType;
    }

    public function delete(int $id): bool
    {
        return FeeType::destroy($id) > 0;
    }

    public function findTrashedById(int $id): ?FeeType
    {
        // withTrashed() để tìm cả trong thùng rác
        // whereNotNull('deleted_at') để chắc chắn nó đã bị xóa mềm
        return FeeType::withTrashed()->whereNotNull('deleted_at')->find($id);
    }

    public function restore(int $id): bool
    {
        $feeType = $this->findTrashedById($id);
        if ($feeType) {
            return $feeType->restore();
        }
        return false;
    }
}