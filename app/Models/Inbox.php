<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Inbox extends Model
{
    use HasFactory;

    // Expiration times in minutes
    public const GUEST_EXPIRY_MINUTES = 60; // 1 hour
    public const AUTH_EXPIRY_MINUTES = 10080; // 1 week (7 * 24 * 60)

    protected $fillable = [
        'user_id',
        'email',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
    ];

    /**
     * Get the user that owns the inbox.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the emails for this inbox.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class)->orderBy('received_at', 'desc');
    }

    /**
     * Check if inbox has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if inbox belongs to an authenticated user.
     */
    public function isAuthenticated(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Get unread emails count.
     */
    public function getUnreadCountAttribute(): int
    {
        return $this->emails()->where('is_read', false)->count();
    }

    /**
     * Generate a new random inbox.
     */
    public static function generateNew(string $domain = 'tempmail.local', ?User $user = null): self
    {
        $prefix = strtolower(Str::random(10));
        $expiresInMinutes = $user ? self::AUTH_EXPIRY_MINUTES : self::GUEST_EXPIRY_MINUTES;
        
        return self::create([
            'user_id' => $user?->id,
            'email' => "{$prefix}@{$domain}",
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);
    }

    /**
     * Find inbox by email address.
     */
    public static function findByEmail(string $email): ?self
    {
        return self::where('email', strtolower($email))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Find inbox by token.
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
    }
    /**
     * Delete emails when the inbox is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Inbox $inbox) {
            foreach ($inbox->emails as $email) {
                $email->delete();
            }
        });
    }
}
