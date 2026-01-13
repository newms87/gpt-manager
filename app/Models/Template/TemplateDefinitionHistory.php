<?php

namespace App\Models\Template;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Newms87\Danx\Traits\ActionModelTrait;

class TemplateDefinitionHistory extends Model
{
    use ActionModelTrait;

    protected $table = 'template_definition_history';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * Write a history entry with the PREVIOUS values before the update is saved.
     */
    public static function write(TemplateDefinition $template): ?self
    {
        $originalHtmlContent = $template->getOriginal('html_content');
        $originalCssContent  = $template->getOriginal('css_content');

        // Don't create history if there was no previous content
        if (!$originalHtmlContent && !$originalCssContent) {
            return null;
        }

        // Create the history record with the previous values
        $history = self::create([
            'template_definition_id' => $template->id,
            'user_id'                => user()?->id,
            'html_content'           => $originalHtmlContent ?? '',
            'css_content'            => $originalCssContent,
        ]);

        // Cleanup history records older than 15 minutes so we only retain 1 per minute
        self::cleanupOldHistory($template->id, now()->subMinutes(15));

        // Cleanup history records older than 1 day so we only retain 1 per hour
        self::cleanupOldHistory($template->id, now()->subDay(), 'YYYY-MM-DD HH24');

        // Cleanup history records older than 1 week so we only retain 1 per day
        self::cleanupOldHistory($template->id, now()->subWeek(), 'YYYY-MM-DD');

        return $history;
    }

    /**
     * Clean up old history records based on a tiered retention policy.
     * Keeps the most recent record for each time interval.
     */
    public static function cleanupOldHistory(int $templateDefinitionId, Carbon $newestDate, string $groupFormat = 'YYYY-MM-DD HH24:MI'): void
    {
        // Collect the IDs of all the records that are the most recent for each interval (older than the newestDate)
        $ids = self::where('created_at', '<', $newestDate)
            ->where('template_definition_id', $templateDefinitionId)
            ->groupByRaw("TO_CHAR(created_at, '$groupFormat')")
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        // Delete all the records older than newest date, that are not in the list of IDs
        self::where('template_definition_id', $templateDefinitionId)
            ->where('created_at', '<', $newestDate)
            ->whereNotIn('id', $ids)
            ->delete();
    }

    public function templateDefinition(): BelongsTo
    {
        return $this->belongsTo(TemplateDefinition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Restore the template definition to this history state.
     * Updates the template's html_content and css_content to the values stored in this history record.
     */
    public function restore(): void
    {
        $this->templateDefinition->update([
            'html_content' => $this->html_content,
            'css_content'  => $this->css_content,
        ]);
    }
}
