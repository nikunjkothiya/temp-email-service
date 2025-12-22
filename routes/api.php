<?php

use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\InboxController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Temp Mail Server API endpoints
|
*/

// Inbox routes
Route::prefix('inbox')->group(function () {
    // Create new inbox
    Route::post('/', [InboxController::class, 'create']);
    
    // Get inbox by token
    Route::get('/{token}', [InboxController::class, 'show']);
    
    // Refresh/extend inbox
    Route::post('/{token}/refresh', [InboxController::class, 'refresh']);
    
    // Delete inbox
    Route::delete('/{token}', [InboxController::class, 'destroy']);
    
    // Email routes (nested under inbox)
    Route::prefix('{token}/emails')->group(function () {
        // List all emails
        Route::get('/', [EmailController::class, 'index']);
        
        // Bulk delete emails
        Route::delete('/', [EmailController::class, 'bulkDestroy']);
        
        // Get single email
        Route::get('/{emailId}', [EmailController::class, 'show']);
        
        // Delete email
        Route::delete('/{emailId}', [EmailController::class, 'destroy']);
        
        // Download attachment
        Route::get('/{emailId}/attachments/{attachmentId}', [EmailController::class, 'downloadAttachment']);
    });
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});
