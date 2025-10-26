<?php

namespace App\Http\Resources\Auth;

use App\Models\Auth\AuthToken;
use Newms87\Danx\Resources\ActionResource;

class AuthTokenResource extends ActionResource
{
    public static function data(AuthToken $token): array
    {
        return [
            'id'                => $token->id,
            'team_id'           => $token->team_id,
            'service'           => $token->service,
            'type'              => $token->type,
            'name'              => $token->name,
            'display_name'      => $token->getDisplayName(),
            'scopes'            => $token->scopes,
            'expires_at'        => $token->expires_at,
            'is_valid'          => $token->isValid(),
            'is_expired'        => $token->isExpired(),
            'will_expire_soon'  => $token->willExpireWithin(5),
            'has_refresh_token' => !empty($token->refresh_token),
            'metadata'          => $token->metadata,
            'created_at'        => $token->created_at,
            'updated_at'        => $token->updated_at,
        ];
    }
}
