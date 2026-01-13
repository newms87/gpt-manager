<?php

namespace App\Resources\Template;

use App\Models\Template\TemplateDefinitionHistory;
use Newms87\Danx\Resources\ActionResource;

class TemplateDefinitionHistoryResource extends ActionResource
{
    public static function data(TemplateDefinitionHistory $history): array
    {
        return [
            'id'         => $history->id,
            'user_id'    => $history->user_id,
            'created_at' => $history->created_at,
            'updated_at' => $history->updated_at,

            // Lazy load content fields to avoid large payloads
            'html_content' => fn() => $history->html_content,
            'css_content'  => fn() => $history->css_content,

            // User relationship
            'user' => fn($fields) => $history->user ? [
                'id'    => $history->user->id,
                'name'  => $history->user->name,
                'email' => $history->user->email,
            ] : null,
        ];
    }
}
