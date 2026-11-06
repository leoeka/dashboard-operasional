<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Mendapatkan user yang sedang diautentikasi saat ini.
     */
    protected function user(): ?User
    {
        return auth()->user();
    }

    /**
     * Mendapatkan ID user yang sedang diautentikasi saat ini.
     */
    protected function userId(): ?int
    {
        return auth()->id();
    }

    /**
     * Helper respon JSON sukses terstandar untuk endpoint AJAX / API internal.
     */
    protected function jsonSuccess(mixed $data = [], string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Helper respon JSON gagal terstandar untuk endpoint AJAX / API internal.
     */
    protected function jsonError(string $message = 'Error', int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
