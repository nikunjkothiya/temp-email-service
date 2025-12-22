<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Send a success response.
     *
     * @param  mixed  $data
     */
    protected function successResponse($data, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send an error response.
     *
     * @param  mixed  $data
     */
    protected function errorResponse(string $message, int $code = 400, $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $message, // Keeping 'error' for backward compatibility if needed
            'data' => $data,
        ], $code);
    }
}
