<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateInboxRequest;
use App\Http\Requests\Api\RefreshInboxRequest;
use App\Models\Inbox;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InboxController extends Controller
{
    use ApiResponse;

    /**
     * Create a new temporary inbox.
     */
    public function create(CreateInboxRequest $request): JsonResponse
    {
        $domain = $request->input('domain', 'tempmail.local');
        
        // Check if user is authenticated
        $user = Auth::user();
        
        // Generate inbox with appropriate expiration
        // Auth users: 1 week, Guest users: 1 hour
        
        // If user is authenticated, delete their old inboxes first
        if ($user) {
            Inbox::where('user_id', $user->id)->delete();
        }

        $inbox = Inbox::generateNew($domain, $user);
        
        $expiryLabel = $user ? '1 week' : '1 hour';
        
        return $this->successResponse([
            'id' => $inbox->id,
            'email' => $inbox->email,
            'token' => $inbox->token,
            'expires_at' => $inbox->expires_at->toIso8601String(),
            'expires_in_seconds' => $inbox->expires_at->diffInSeconds(now()),
            'is_authenticated' => $user !== null,
            'expiry_label' => $expiryLabel,
        ], null, 201);
    }

    /**
     * Get inbox details by token.
     */
    public function show(string $token): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }
        
        $expiryLabel = $inbox->isAuthenticated() ? '1 week' : '1 hour';
        
        return $this->successResponse([
            'id' => $inbox->id,
            'email' => $inbox->email,
            'expires_at' => $inbox->expires_at->toIso8601String(),
            'expires_in_seconds' => $inbox->expires_at->diffInSeconds(now()),
            'unread_count' => $inbox->unread_count,
            'emails_count' => $inbox->emails()->count(),
            'is_authenticated' => $inbox->isAuthenticated(),
            'expiry_label' => $expiryLabel,
        ]);
    }

    /**
     * Refresh/extend inbox expiration.
     */
    public function refresh(string $token, RefreshInboxRequest $request): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }
        
        $additionalMinutes = $request->input('duration', 60);
        // Validation is already handled by RefreshInboxRequest, but we can keep the clamp logic or move it there.
        // Keeping it here as business logic for now, though validation ensures min/max.
        
        $inbox->update([
            'expires_at' => now()->addMinutes($additionalMinutes),
        ]);
        
        return $this->successResponse([
            'id' => $inbox->id,
            'email' => $inbox->email,
            'expires_at' => $inbox->expires_at->toIso8601String(),
            'expires_in_seconds' => $inbox->expires_at->diffInSeconds(now()),
        ]);
    }

    /**
     * Delete inbox and all its emails.
     */
    public function destroy(string $token): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }
        
        $inbox->delete();
        
        return $this->successResponse(null, 'Inbox deleted successfully');
    }
}
