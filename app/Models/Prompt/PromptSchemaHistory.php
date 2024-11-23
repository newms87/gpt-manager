<?php

namespace App\Models\Prompt;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptSchemaHistory extends Model
{
    protected $table = 'prompt_schema_history';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public static function write(User $user, PromptSchema $promptSchema, array $previousSchema): self|null
    {
        if (!$previousSchema) {
            return null;
        }

        // Create the history record
        $history = self::create([
            'user_id'          => $user->id,
            'prompt_schema_id' => $promptSchema->id,
            'schema'           => $previousSchema,
        ]);

        // Cleanup history records older than 15 minutes so we only retain 1 per minute
        self::cleanupOldHistory($promptSchema, now()->subMinutes(15));

        // Cleanup history records older than 1 day so we only retain 1 per hour
        self::cleanupOldHistory($promptSchema, now()->subDay(), '%Y-%m-%d %H');

        // Cleanup history records older than 1 week so we only retain 1 per day
        self::cleanupOldHistory($promptSchema, now()->subWeek(), '%Y-%m-%d');

        return $history;
    }

    public static function cleanupOldHistory(PromptSchema $promptSchema, Carbon $newestDate, string $groupFormat = '%Y-%m-%d %H:%i'): void
    {
        // Collect the IDs of all the records that are the most recent for each interval (older than the newestDate)
        $ids = self::where('created_at', '<', $newestDate)
            ->where('prompt_schema_id', $promptSchema->id)
            ->groupByRaw("DATE_FORMAT(created_at, '$groupFormat')")
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        // Delete all the records oldest than newest date, that are not in the list of IDs
        self::where('prompt_schema_id', $promptSchema->id)
            ->where('created_at', '<', $newestDate)
            ->whereNotIn('id', $ids)
            ->delete();
    }

    public function casts(): array
    {
        return [
            'schema' => 'json',
        ];
    }

    public function user(): BelongsTo|User
    {
        return $this->belongsTo(User::class);
    }

    public function promptSchema(): BelongsTo|PromptSchema
    {
        return $this->belongsTo(PromptSchema::class);
    }
}
