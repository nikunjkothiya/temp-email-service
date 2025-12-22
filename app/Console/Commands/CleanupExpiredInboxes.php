<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\Inbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredInboxes extends Command
{
    protected $signature = 'inbox:cleanup';

    protected $description = 'Clean up expired inboxes and their emails';

    public function handle(): int
    {
        $this->info('Starting cleanup of expired inboxes...');

        // Get expired inboxes
        $expiredInboxes = Inbox::where('expires_at', '<', now())->get();
        
        $inboxCount = $expiredInboxes->count();
        $emailCount = 0;
        $attachmentCount = 0;

        foreach ($expiredInboxes as $inbox) {
            // Count emails for logging
            $inboxEmailCount = $inbox->emails()->count();
            $emailCount += $inboxEmailCount;

            // Get all emails to count attachments
            foreach ($inbox->emails as $email) {
                $attachmentCount += $email->attachments()->count();
            }

            // Delete inbox (cascades to emails and attachments via model events)
            $inbox->delete();
        }

        // Clean up empty attachment directories
        $directories = Storage::disk('local')->directories('attachments');
        foreach ($directories as $dir) {
            if (empty(Storage::disk('local')->files($dir))) {
                Storage::disk('local')->deleteDirectory($dir);
            }
        }

        $this->info("Cleanup complete:");
        $this->info("  - Inboxes deleted: {$inboxCount}");
        $this->info("  - Emails deleted: {$emailCount}");
        $this->info("  - Attachments deleted: {$attachmentCount}");

        return 0;
    }
}
