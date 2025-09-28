<?php

namespace App\Repositories\Contracts;

use  \Illuminate\Database\Eloquent\Collection;

interface InvoiceRepositoryInterface
{
    public function getByStudentIds(array $studentIds);
}
