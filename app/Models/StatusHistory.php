<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StatusHistory extends Model
{
    protected $fillable = [
        'statusable_type',
        'statusable_id',
        'status',
        'changed_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'changed_at' => 'datetime',
    ];

    public function statusable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Count entities of the given type whose most recent status change at or
     * before $asOf resolved to $statusValue. Entities with no history row
     * before $asOf (i.e. they did not exist yet) are not counted either way.
     *
     * Uses MAX(id) rather than MAX(changed_at) to pick the latest row per
     * entity: two changes landing in the same second would tie on
     * changed_at (timestamp column, 1s resolution), but row id always
     * reflects true insertion order.
     *
     * $idsFilter optionally restricts to a specific set of entity IDs (e.g.
     * users of a given role), since role isn't tracked on this table.
     */
    public static function countAsOf(string $statusableType, Carbon $asOf, bool $statusValue, ?array $idsFilter = null): int
    {
        $latestIdPerEntity = static::query()
            ->selectRaw('MAX(id)')
            ->where('statusable_type', $statusableType)
            ->where('changed_at', '<=', $asOf)
            ->when($idsFilter !== null, fn ($q) => $q->whereIn('statusable_id', $idsFilter))
            ->groupBy('statusable_id');

        return static::query()
            ->whereIn('id', $latestIdPerEntity)
            ->where('status', $statusValue)
            ->count();
    }
}
