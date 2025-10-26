<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Resources\Auth\AuthTokenResource;
use App\Models\Auth\AuthToken;
use App\Repositories\Auth\AuthTokenRepository;
use App\Services\Auth\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Http\Controllers\ActionController;

class OAuthController extends ActionController
{
    public static ?string $repo     = AuthTokenRepository::class;

    public static ?string $resource = AuthTokenResource::class;

    /**
     * Get OAuth authorization URL for a service
     */
    public function authorize(Request $request, string $service): JsonResponse
    {
        $request->validate([
            'redirect_after_auth' => 'sometimes|url',
        ]);

        $oauthService = app(OAuthService::class);

        if (!$oauthService->isConfigured($service)) {
            throw new ValidationError("OAuth is not configured for service: {$service}", 500);
        }

        $currentTeam = team();
        if (!$currentTeam) {
            throw new ValidationError('Team context required for OAuth authorization', 400);
        }

        // Use team ID and service as state parameter for security
        $state = base64_encode(json_encode([
            'service'             => $service,
            'team_id'             => $currentTeam->id,
            'timestamp'           => time(),
            'redirect_after_auth' => $request->input('redirect_after_auth'),
        ]));

        $authUrl = $oauthService->getAuthorizationUrl($service, $state);

        return response()->json([
            'authorization_url' => $authUrl,
            'service'           => $service,
            'state'             => $state,
        ]);
    }

    /**
     * Handle OAuth callback for any service
     */
    public function callback(Request $request)
    {
        $code  = $request->input('code');
        $state = $request->input('state');
        $error = $request->input('error');

        if ($error) {
            throw new ValidationError('OAuth authorization failed: ' . $error, 400);
        }

        if (!$code) {
            throw new ValidationError('Missing authorization code in OAuth callback', 400);
        }

        if (!$state) {
            throw new ValidationError('Missing state parameter in OAuth callback', 400);
        }

        // Validate state parameter and extract service/team info
        $validationResult = $this->validateState($state);
        if (is_array($validationResult) && isset($validationResult['error'])) {
            throw new ValidationError($validationResult['message'], $validationResult['status']);
        }
        [$service, $team, $redirectUrl] = $validationResult;

        $oauthService = app(OAuthService::class);

        // Exchange code for token
        $oauthTokenData = $oauthService->exchangeCodeForToken($service, $code);

        // Store token for the team from state
        $oauthService->storeToken($service, $oauthTokenData, $team);

        // If a specific redirect URL was provided in state, use it
        // Otherwise redirect to the SPA dashboard
        $dashboardUrl = $redirectUrl ?: config('app.spa_url');

        return redirect($dashboardUrl);
    }

    /**
     * Get current OAuth status for a service
     */
    public function status(Request $request, string $service): JsonResponse
    {
        $oauthService   = app(OAuthService::class);
        $shouldValidate = $request->query('validate') === 'true';

        // If validation is requested, use the full API validation
        if ($shouldValidate) {
            $result = $oauthService->validateTokenWithApi($service);

            return response()->json([
                'has_token'     => $result['valid'],
                'is_configured' => $oauthService->isConfigured($service),
                'service'       => $service,
                'validation'    => $result,
            ]);
        }

        // Otherwise, just check if token exists
        $token = $oauthService->getToken($service);

        if (!$token) {
            return response()->json([
                'has_token'     => false,
                'is_configured' => $oauthService->isConfigured($service),
                'service'       => $service,
            ]);
        }

        return response()->json([
            'has_token'     => true,
            'is_configured' => $oauthService->isConfigured($service),
            'service'       => $service,
            'token'         => AuthTokenResource::data($token),
        ]);
    }

    /**
     * Validate OAuth token by testing with actual API
     */
    public function validate(string $service): JsonResponse
    {
        $oauthService = app(OAuthService::class);
        $result       = $oauthService->validateTokenWithApi($service);

        return response()->json($result);
    }

    /**
     * Revoke OAuth token for a service
     */
    public function revoke(string $service): JsonResponse
    {
        $oauthService = app(OAuthService::class);
        $success      = $oauthService->revokeToken($service);

        return response()->json([
            'success' => $success,
            'service' => $service,
            'message' => $success
                ? "OAuth token for {$service} revoked successfully"
                : "No OAuth token found for {$service}",
        ]);
    }

    /**
     * Refresh OAuth token for a service
     */
    public function refresh(string $service)
    {
        $oauthService = app(OAuthService::class);
        $token        = $oauthService->getToken($service);

        if (!$token) {
            return response()->json([
                'message' => "No OAuth token found for service: {$service}",
            ], 404);
        }

        try {
            $refreshedToken = $oauthService->refreshToken($service, $token);

            return response()->json(AuthTokenResource::data($refreshedToken));

        } catch (\Exception $e) {
            if ($e instanceof ValidationError) {
                throw $e;
            }
            throw new ValidationError('Failed to refresh OAuth token: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all OAuth tokens for current team
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'service' => 'sometimes|string',
            'type'    => 'sometimes|in:oauth,api_key',
        ]);

        $repository = app(AuthTokenRepository::class);
        $tokens     = $repository->getTokensForTeam();

        // Filter by service if provided
        if ($request->filled('service')) {
            $tokens = $tokens->where('service', $request->input('service'));
        }

        // Filter by type if provided
        if ($request->filled('type')) {
            $tokens = $tokens->where('type', $request->input('type'));
        }

        return response()->json([
            'data'  => AuthTokenResource::collection($tokens),
            'count' => $tokens->count(),
        ]);
    }

    /**
     * Store an API key for a service
     */
    public function storeApiKey(Request $request): JsonResponse
    {
        $request->validate([
            'service'  => 'required|string|max:50',
            'api_key'  => 'required|string',
            'name'     => 'nullable|string|max:100',
            'metadata' => 'sometimes|array',
        ]);

        $repository = app(AuthTokenRepository::class);

        $token = $repository->storeApiKey(
            $request->input('service'),
            $request->input('api_key'),
            $request->input('name'),
            null,
            $request->input('metadata', [])
        );

        return response()->json(AuthTokenResource::data($token));
    }

    /**
     * Delete an auth token
     */
    public function destroy(AuthToken $authToken): JsonResponse
    {
        // Validate ownership
        $currentTeam = team();
        if (!$currentTeam || $authToken->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to delete this token', 403);
        }

        $service = $authToken->service;
        $type    = $authToken->type;

        $authToken->delete();

        return response()->json([
            'success' => true,
            'message' => "Successfully deleted {$type} token for {$service}",
        ]);
    }

    /**
     * Validate the state parameter from OAuth callback and return service/team info
     */
    protected function validateState(string $state): array
    {
        try {
            $stateData = json_decode(base64_decode($state), true);

            if (!$stateData || !isset($stateData['service'], $stateData['team_id'], $stateData['timestamp'])) {
                return ['error' => true, 'message' => 'Invalid state parameter in OAuth callback', 'status' => 400];
            }

            // Validate service
            $service = $stateData['service'];
            if (empty($service)) {
                return ['error' => true, 'message' => 'Missing service in OAuth callback state', 'status' => 400];
            }

            // Validate team exists
            try {
                $team = \App\Models\Team\Team::find($stateData['team_id']);
                if (!$team) {
                    return ['error' => true, 'message' => 'Invalid team ID in OAuth callback state', 'status' => 403];
                }
            } catch (\Exception $e) {
                return ['error' => true, 'message' => 'Invalid team ID in OAuth callback state', 'status' => 403];
            }

            // Check if state is not older than 10 minutes (prevent replay attacks)
            if ((time() - $stateData['timestamp']) > 600) {
                return ['error' => true, 'message' => 'OAuth state parameter has expired', 'status' => 400];
            }

            $redirectUrl = $stateData['redirect_after_auth'] ?? null;

            return [$service, $team, $redirectUrl];

        } catch (\Exception $e) {
            return ['error' => true, 'message' => 'Failed to validate OAuth state parameter', 'status' => 400];
        }
    }
}
