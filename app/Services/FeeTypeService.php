<?php

namespace App\Services;

use App\Models\FeeType;
use App\Repositories\Contracts\FeeTypeRepositoryInterface;
use App\Services\Interface\FeeTypeServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class FeeTypeService implements FeeTypeServiceInterface
{
    protected $feeTypeRepository;

    public function __construct(FeeTypeRepositoryInterface $feeTypeRepository)
    {
        $this->feeTypeRepository = $feeTypeRepository;
    }

    public function getAllFeeTypes(array $filters): LengthAwarePaginator
    {
        return $this->feeTypeRepository->getAll($filters);
    }

    public function createFeeType(array $data): FeeType
    {
        return $this->feeTypeRepository->create($data);
    }

    public function updateFeeType(FeeType $feeType, array $data): FeeType
    {
        return $this->feeTypeRepository->update($feeType->id, $data);
    }

    public function deleteFeeType(FeeType $feeType): bool
    {
        // LOGIC NGHIỆP VỤ: Không cho xóa nếu đang được sử dụng
        // Giả sử có mối quan hệ `items()` trong FeeType Model trỏ đến invoice_items
        if ($feeType->invoiceItems()->exists()) {
            throw new Exception('Cannot delete fee type that is being used in invoices. Consider deactivating it instead.');
        }
        return $this->feeTypeRepository->delete($feeType->id);
    }

    public function toggleActiveStatus(FeeType $feeType): FeeType
    {
        return $this->feeTypeRepository->update($feeType->id, [
            'is_active' => !$feeType->is_active
        ]);
    }
    public function restoreFeeType(int $id): ?FeeType
    {
        $restored = $this->feeTypeRepository->restore($id);

        // Nếu khôi phục thành công, tìm lại bản ghi để trả về
        if ($restored) {
            return $this->feeTypeRepository->findById($id);
        }

        return null;
    }
}
