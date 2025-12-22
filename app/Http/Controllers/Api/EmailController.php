<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkDeleteEmailsRequest;
use App\Models\Inbox;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailController extends Controller
{
    use ApiResponse;

    /**
     * List all emails for an inbox.
     */
    public function index(string $token): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }
        
        $emails = $inbox->emails()
            ->select(['id', 'from_email', 'from_name', 'subject', 'is_read', 'received_at', 'created_at'])
            ->withCount('attachments')
            ->orderBy('received_at', 'desc')
            ->get()
            ->map(function ($email) {
                return [
                    'id' => $email->id,
                    'from_email' => $email->from_email,
                    'from_name' => $email->from_name,
                    'subject' => $email->subject,
                    'preview' => $email->preview,
                    'is_read' => $email->is_read,
                    'received_at' => $email->received_at->toIso8601String(),
                    'attachments_count' => $email->attachments_count,
                ];
            });
        
        return $this->successResponse($emails);
    }

    /**
     * Get a single email by ID.
     */
    public function show(string $token, int $emailId): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }
        
        $email = $inbox->emails()->with('attachments')->find($emailId);
        
        if (!$email) {
            return $this->errorResponse('Email not found', 404);
        }
        
        // Mark as read
        $email->markAsRead();
        
        return $this->successResponse([
            'id' => $email->id,
            'from_email' => $email->from_email,
            'from_name' => $email->from_name,
            'to_email' => $email->to_email,
            'subject' => $email->subject,
            'body_text' => $email->body_text,
            'body_html' => $email->body_html,
            'is_read' => $email->is_read,
            'received_at' => $email->received_at->toIso8601String(),
            'attachments' => $email->attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'human_size' => $attachment->human_size,
                ];
            }),
        ]);
    }

    /**
     * Delete an email.
     */
    public function destroy(string $token, int $emailId): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }
        
        $email = $inbox->emails()->find($emailId);
        
        if (!$email) {
            return $this->errorResponse('Email not found', 404);
        }
        
        $email->delete();
        
        return $this->successResponse(null, 'Email deleted successfully');
    }

    /**
     * Bulk delete emails.
     */
    public function bulkDestroy(BulkDeleteEmailsRequest $request, string $token): JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }

        $ids = $request->validated()['ids'];
        
        $inbox->emails()->whereIn('id', $ids)->delete();

        return $this->successResponse(null, count($ids) . ' emails deleted successfully');
    }

    /**
     * Download an attachment.
     */
    public function downloadAttachment(string $token, int $emailId, int $attachmentId): StreamedResponse|JsonResponse
    {
        $inbox = Inbox::findByToken($token);
        
        if (!$inbox) {
            return $this->errorResponse('Inbox not found or expired', 404);
        }
        
        $email = $inbox->emails()->find($emailId);
        
        if (!$email) {
            return $this->errorResponse('Email not found', 404);
        }
        
        $attachment = $email->attachments()->find($attachmentId);
        
        if (!$attachment) {
            return $this->errorResponse('Attachment not found', 404);
        }
        
        return response()->streamDownload(function () use ($attachment) {
            echo $attachment->getContents();
        }, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }
}
