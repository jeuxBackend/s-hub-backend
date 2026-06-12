<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    /**
     * Find an existing conversation between two users.
     * Always uses lower ID as user_one to match the unique constraint.
     */
    public static function findBetween(int $userA, int $userB): ?self
    {
        [$one, $two] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return self::where('user_one_id', $one)
            ->where('user_two_id', $two)
            ->first();
    }

    /**
     * Get or create a conversation between two users.
     */
    public static function findOrCreateBetween(int $userA, int $userB): self
    {
        [$one, $two] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return self::firstOrCreate([
            'user_one_id' => $one,
            'user_two_id' => $two,
        ]);
    }

    /**
     * Get the OTHER participant (not the authenticated user).
     */
    public function participant(int $authUserId): ?User
    {
        return $this->user_one_id === $authUserId
            ? $this->userTwo
            : $this->userOne;
    }

    /**
     * Count unread messages for the given user (messages sent by the other person, not yet read).
     */
    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }
}