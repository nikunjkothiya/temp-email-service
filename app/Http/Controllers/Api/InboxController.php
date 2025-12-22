<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InboxController extends Controller
{
    /**
     * Create a new temporary inbox.
     */
    public function create(Request $request): JsonResponse
    {
        $domain = $request->input('domain', 'tempmail.local');
        
        // Check if user is authenticated
        $user = Auth::user();
        
        // Generate inbox with appropriate expiration
        // Auth users: 1 week, Guest users: 1 hour
        $inbox = Inbox::generateNew($domain, $user);
        
        $expiryLabel = $user ? '1 week' : '1 hour';
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inbox->id,
                'email' => $inbox->email,
                'token' => $inbox->token,
                'expires_at' => $inbox->expires_at->toIso8601String(),
                'expires_in_seconds' => $inbox->expires_at->diffInSeconds(now()),
                'is_authenticated' => $user !== null,
                'expiry_label' => $expiryLabel,
            ],
        ], 201);
    }

    /**
     * Get inbox details by token.
     */
    public function show(string $token): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return response()->json([
                'success' => false,
                'error' => 'Inbox not found or expired',
            ], 404);
        }
        
        $expiryLabel = $inbox->isAuthenticated() ? '1 week' : '1 hour';
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inbox->id,
                'email' => $inbox->email,
                'expires_at' => $inbox->expires_at->toIso8601String(),
                'expires_in_seconds' => $inbox->expires_at->diffInSeconds(now()),
                'unread_count' => $inbox->unread_count,
                'emails_count' => $inbox->emails()->count(),
                'is_authenticated' => $inbox->isAuthenticated(),
                'expiry_label' => $expiryLabel,
            ],
        ]);
    }

    /**
     * Refresh/extend inbox expiration.
     */
    public function refresh(string $token, Request $request): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return response()->json([
                'success' => false,
                'error' => 'Inbox not found or expired',
            ], 404);
        }
        
        $additionalMinutes = $request->input('duration', 60);
        $additionalMinutes = max(10, min(1440, (int) $additionalMinutes));
        
        $inbox->update([
            'expires_at' => now()->addMinutes($additionalMinutes),
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inbox->id,
                'email' => $inbox->email,
                'expires_at' => $inbox->expires_at->toIso8601String(),
                'expires_in_seconds' => $inbox->expires_at->diffInSeconds(now()),
            ],
        ]);
    }

    /**
     * Delete inbox and all its emails.
     */
    public function destroy(string $token): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return response()->json([
                'success' => false,
                'error' => 'Inbox not found or expired',
            ], 404);
        }
        
        $inbox->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Inbox deleted successfully',
        ]);
    }
}
