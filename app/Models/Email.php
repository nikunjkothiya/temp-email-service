<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'inbox_id',
        'from_email',
        'from_name',
        'to_email',
        'subject',
        'body_text',
        'body_html',
        'raw_content',
        'is_read',
        'received_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'received_at' => 'datetime',
    ];

    /**
     * Get the inbox this email belongs to.
     */
    public function inbox(): BelongsTo
    {
        return $this->belongsTo(Inbox::class);
    }

    /**
     * Get the attachments for this email.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Mark the email as read.
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true]);
        }
    }

    /**
     * Get a short preview of the email body.
     */
    public function getPreviewAttribute(): string
    {
        $text = $this->body_text ?? strip_tags($this->body_html ?? '');
        return Str::limit(trim($text), 100);
    }

    /**
     * Get the sender display name.
     */
    public function getSenderAttribute(): string
    {
        if ($this->from_name) {
            return "{$this->from_name} <{$this->from_email}>";
        }
        return $this->from_email;
    }
    /**
     * Delete attachments when the email is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Email $email) {
            foreach ($email->attachments as $attachment) {
                $attachment->delete();
            }
        });
    }
}
