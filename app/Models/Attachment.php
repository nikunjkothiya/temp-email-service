<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'filename',
        'mime_type',
        'size',
        'storage_path',
    ];

    /**
     * Get the email this attachment belongs to.
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Get the attachment contents.
     */
    public function getContents(): ?string
    {
        return Storage::disk('local')->get($this->storage_path);
    }

    /**
     * Get the full path to the attachment.
     */
    public function getFullPath(): string
    {
        return Storage::disk('local')->path($this->storage_path);
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete the attachment file when the model is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            Storage::disk('local')->delete($attachment->storage_path);
        });
    }
}
