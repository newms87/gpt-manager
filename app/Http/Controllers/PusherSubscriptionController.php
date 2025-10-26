<?php

namespace App\Http\Controllers;

use App\Services\PusherSubscriptionService;
use Illuminate\Http\Request;
use Newms87\Danx\Exceptions\ValidationError;

class PusherSubscriptionController extends Controller
{
    /**
     * Subscribe to model updates via Pusher
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string',
            'model_id_or_filter' => 'required',
        ]);

        // Validate model_id_or_filter - reject any empty value
        $modelIdOrFilter = $request->input('model_id_or_filter');

        if (empty($modelIdOrFilter)) {
            throw new ValidationError('model_id_or_filter cannot be empty');
        }

        app(PusherSubscriptionService::class)->subscribe(
            $request->input('resource_type'),
            $request->input('model_id_or_filter'),
            team()->id,
            auth()->id()
        );

        return response()->json(['success' => true]);
    }

    /**
     * Unsubscribe from model updates
     */
    public function unsubscribe(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string',
            'model_id_or_filter' => 'required',
        ]);

        // Validate model_id_or_filter - reject any empty value
        $modelIdOrFilter = $request->input('model_id_or_filter');

        if (empty($modelIdOrFilter)) {
            throw new ValidationError('model_id_or_filter cannot be empty');
        }

        app(PusherSubscriptionService::class)->unsubscribe(
            $request->input('resource_type'),
            $request->input('model_id_or_filter'),
            team()->id,
            auth()->id()
        );

        return response()->json(['success' => true]);
    }

    /**
     * Keep subscriptions alive by refreshing TTL
     */
    public function keepalive(Request $request)
    {
        $request->validate([
            'subscriptions' => 'required|array',
            'subscriptions.*.resource_type' => 'required|string',
            'subscriptions.*.model_id_or_filter' => 'required',
        ]);

        app(PusherSubscriptionService::class)->keepalive(
            $request->input('subscriptions'),
            team()->id,
            auth()->id()
        );

        return response()->json(['success' => true]);
    }
}
