<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(array $data = [], string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, string $code, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
        ], $status);
    }
}
