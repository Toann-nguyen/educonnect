<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Demo API",
 *     version="1.0.0",
 *     description="Demo tài liệu API cho Laravel 10"
 * )
 */
class TestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/test",
     *     summary="Test API",
     *     description="Trả về chuỗi test đơn giản",
     *     tags={"Test"},
     *     @OA\Response(
     *         response=200,
     *         description="Thành công"
     *     )
     * )
     */
    public function index()
    {
        return response()->json(['message' => 'Swagger working!']);
    }
}
