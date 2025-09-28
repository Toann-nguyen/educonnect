<?php

namespace App\Services\Interface;

use App\Models\User;

interface InvoiceServiceInterface
{
    public function getInvoiceForParent(User $parent);
}
