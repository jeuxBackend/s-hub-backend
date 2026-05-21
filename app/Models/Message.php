<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment',
        'attachment_type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Build the public URL for the attachment file.
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment
            ? asset('storage/' . $this->attachment)
            : null;
    }
}
