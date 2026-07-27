<?php

namespace App\Domains\Finance\Services\Interface;

use App\Domains\Finance\Models\FeeType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FeeTypeServiceInterface
{
    public function getAllFeeTypes(array $filters): LengthAwarePaginator;
    public function createFeeType(array $data): FeeType;
    public function updateFeeType(FeeType $feeType, array $data): FeeType;
    public function deleteFeeType(FeeType $feeType): bool;
    public function toggleActiveStatus(FeeType $feeType): FeeType;
    public function restoreFeeType(int $id): ?FeeType;
}
