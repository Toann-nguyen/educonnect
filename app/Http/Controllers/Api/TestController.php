<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="EduConnect API",
 *     version="1.0.0",
 *     description="Tài liệu API cho hệ thống EduConnect - Quản lý giáo dục trực tuyến"
 * )
 * @OA\Server(
 *     url="https://api.toanrobert.online",
 *     description="Production server"
 * )
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="bearerAuth"
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
