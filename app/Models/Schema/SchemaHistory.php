<?php

namespace App\Models\Schema;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Traits\ActionModelTrait;

class SchemaHistory extends Model
{
    use ActionModelTrait;

    protected $table = 'schema_history';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public static function write(User $user, SchemaDefinition $schemaDefinition, array $previousSchema): self|null
    {
        if (!$previousSchema) {
            return null;
        }

        // Create the history record
        $history = self::create([
            'user_id'              => $user->id,
            'schema_definition_id' => $schemaDefinition->id,
            'schema'               => $previousSchema,
        ]);

        // Cleanup history records older than 15 minutes so we only retain 1 per minute
        self::cleanupOldHistory($schemaDefinition, now()->subMinutes(15));

        // Cleanup history records older than 1 day so we only retain 1 per hour
        self::cleanupOldHistory($schemaDefinition, now()->subDay(), 'YYYY-MM-DD HH24');

        // Cleanup history records older than 1 week so we only retain 1 per day
        self::cleanupOldHistory($schemaDefinition, now()->subWeek(), 'YYYY-MM-DD');

        return $history;
    }

    public static function cleanupOldHistory(SchemaDefinition $schemaDefinition, Carbon $newestDate, string $groupFormat = 'YYYY-MM-DD HH24:MI'): void
    {
        // Collect the IDs of all the records that are the most recent for each interval (older than the newestDate)
        $ids = self::where('created_at', '<', $newestDate)
            ->where('schema_definition_id', $schemaDefinition->id)
            ->groupByRaw("TO_CHAR(created_at, '$groupFormat')")
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        // Delete all the records oldest than newest date, that are not in the list of IDs
        self::where('schema_definition_id', $schemaDefinition->id)
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

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class);
    }
}
