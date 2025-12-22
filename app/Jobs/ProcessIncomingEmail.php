<?php

namespace App\Jobs;

use App\Events\NewEmailReceived;
use App\Models\Attachment;
use App\Models\Email;
use App\Models\Inbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessIncomingEmail implements ShouldQueue
{
    use Queueable;

    public string $rawEmail;
    public string $toEmail;
    public string $fromEmail;

    /**
     * Create a new job instance.
     */
    public function __construct(string $rawEmail, string $toEmail, string $fromEmail)
    {
        $this->rawEmail = $rawEmail;
        $this->toEmail = $toEmail;
        $this->fromEmail = $fromEmail;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Find the inbox
            $inbox = Inbox::findByEmail($this->toEmail);
            
            if (!$inbox) {
                Log::warning("No inbox found for email: {$this->toEmail}");
                return;
            }

            // Parse the email using our simple parser
            $parsed = $this->parseEmail($this->rawEmail);

            // Extract from name and email
            $fromName = null;
            $fromEmail = $this->fromEmail;

            if (!empty($parsed['headers']['from'])) {
                $fromHeader = $parsed['headers']['from'];
                if (preg_match('/^"?([^"<]+)"?\s*<([^>]+)>$/', $fromHeader, $matches)) {
                    $fromName = trim($matches[1]);
                    $fromEmail = $matches[2];
                } elseif (filter_var($fromHeader, FILTER_VALIDATE_EMAIL)) {
                    $fromEmail = $fromHeader;
                }
            }

            // Create email record
            $email = Email::create([
                'inbox_id' => $inbox->id,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to_email' => $this->toEmail,
                'subject' => $parsed['headers']['subject'] ?? '(No Subject)',
                'body_text' => $parsed['body_text'],
                'body_html' => $parsed['body_html'],
                'raw_content' => $this->rawEmail,
                'is_read' => false,
                'received_at' => now(),
            ]);

            // Process attachments
            foreach ($parsed['attachments'] as $attachment) {
                $storagePath = 'attachments/' . $email->id . '/' . Str::random(16) . '_' . $attachment['filename'];
                
                Storage::disk('local')->put($storagePath, $attachment['content']);
                
                Attachment::create([
                    'email_id' => $email->id,
                    'filename' => $attachment['filename'],
                    'mime_type' => $attachment['mime_type'],
                    'size' => strlen($attachment['content']),
                    'storage_path' => $storagePath,
                ]);
            }

            // Broadcast the new email event
            broadcast(new NewEmailReceived($email));

            Log::info("Email processed successfully", [
                'email_id' => $email->id,
                'inbox_id' => $inbox->id,
                'from' => $fromEmail,
                'subject' => $email->subject,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process incoming email", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'to' => $this->toEmail,
                'from' => $this->fromEmail,
            ]);
            throw $e;
        }
    }

    /**
     * Simple email parser that handles basic MIME emails.
     */
    private function parseEmail(string $rawEmail): array
    {
        $result = [
            'headers' => [],
            'body_text' => null,
            'body_html' => null,
            'attachments' => [],
        ];

        // Split headers and body
        $parts = preg_split('/\r?\n\r?\n/', $rawEmail, 2);
        $headerSection = $parts[0] ?? '';
        $bodySection = $parts[1] ?? '';

        // Parse headers
        $result['headers'] = $this->parseHeaders($headerSection);

        // Get content type
        $contentType = $result['headers']['content-type'] ?? 'text/plain';
        
        // Handle multipart emails
        if (preg_match('/multipart\/[a-z]+;\s*boundary="?([^";\s]+)"?/i', $contentType, $matches)) {
            $boundary = $matches[1];
            $this->parseMultipart($bodySection, $boundary, $result);
        } else {
            // Simple email
            $body = $this->decodeBody($bodySection, $result['headers']);
            
            if (stripos($contentType, 'text/html') !== false) {
                $result['body_html'] = $body;
            } else {
                $result['body_text'] = $body;
            }
        }

        return $result;
    }

    /**
     * Parse email headers.
     */
    private function parseHeaders(string $headerSection): array
    {
        $headers = [];
        $currentHeader = '';
        $currentValue = '';

        foreach (preg_split('/\r?\n/', $headerSection) as $line) {
            if (preg_match('/^(\S+):\s*(.*)$/', $line, $matches)) {
                if ($currentHeader) {
                    $headers[strtolower($currentHeader)] = trim($currentValue);
                }
                $currentHeader = $matches[1];
                $currentValue = $matches[2];
            } elseif (preg_match('/^\s+(.*)$/', $line, $matches)) {
                // Continuation of previous header
                $currentValue .= ' ' . $matches[1];
            }
        }

        if ($currentHeader) {
            $headers[strtolower($currentHeader)] = trim($currentValue);
        }

        return $headers;
    }

    /**
     * Parse multipart MIME body.
     */
    private function parseMultipart(string $body, string $boundary, array &$result): void
    {
        $parts = preg_split('/--' . preg_quote($boundary, '/') . '(--)?\r?\n/', $body);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || $part === '--') {
                continue;
            }

            $subParts = preg_split('/\r?\n\r?\n/', $part, 2);
            $partHeaders = $this->parseHeaders($subParts[0] ?? '');
            $partBody = $subParts[1] ?? '';

            $partContentType = $partHeaders['content-type'] ?? 'text/plain';
            $contentDisposition = $partHeaders['content-disposition'] ?? '';

            // Check for nested multipart
            if (preg_match('/multipart\/[a-z]+;\s*boundary="?([^";\s]+)"?/i', $partContentType, $matches)) {
                $this->parseMultipart($partBody, $matches[1], $result);
                continue;
            }

            // Check if it's an attachment
            if (stripos($contentDisposition, 'attachment') !== false || 
                preg_match('/filename="?([^";\s]+)"?/i', $contentDisposition . ';' . $partContentType, $filenameMatch)) {
                
                $filename = $filenameMatch[1] ?? 'attachment';
                $mimeType = preg_replace('/;.*$/', '', $partContentType);
                
                $result['attachments'][] = [
                    'filename' => $filename,
                    'mime_type' => trim($mimeType),
                    'content' => $this->decodeBody($partBody, $partHeaders),
                ];
            } else {
                // Regular body part
                $decodedBody = $this->decodeBody($partBody, $partHeaders);
                
                if (stripos($partContentType, 'text/html') !== false) {
                    $result['body_html'] = $decodedBody;
                } elseif (stripos($partContentType, 'text/plain') !== false) {
                    $result['body_text'] = $decodedBody;
                }
            }
        }
    }

    /**
     * Decode body content based on transfer encoding.
     */
    private function decodeBody(string $body, array $headers): string
    {
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '7bit');

        switch ($encoding) {
            case 'base64':
                return base64_decode(preg_replace('/\s+/', '', $body));
            case 'quoted-printable':
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }
}
