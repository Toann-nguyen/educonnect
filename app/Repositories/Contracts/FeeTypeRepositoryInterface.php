<?php

namespace App\Repositories\Contracts;

use App\Models\FeeType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FeeTypeRepositoryInterface
{
    public function getAll(array $filters): LengthAwarePaginator;
    public function findById(int $id): ?FeeType;
    public function create(array $data): FeeType;
    public function update(int $id, array $data): FeeType;
    public function delete(int $id): bool;
    public function findTrashedById(int $id): ?FeeType;
    public function restore(int $id): bool;
}
