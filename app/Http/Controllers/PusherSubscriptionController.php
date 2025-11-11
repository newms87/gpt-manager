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
        $validated = $request->validate([
            'subscription_id'    => 'required|string|uuid',
            'resource_type'      => 'required|string',
            'model_id_or_filter' => 'required',
            'events'             => 'required|array',
            'events.*'           => 'string',
        ]);

        // Validate model_id_or_filter - reject any empty value
        $modelIdOrFilter = $validated['model_id_or_filter'];

        if (empty($modelIdOrFilter)) {
            throw new ValidationError('model_id_or_filter cannot be empty');
        }

        $result = app(PusherSubscriptionService::class)->subscribe(
            $validated['resource_type'],
            $validated['model_id_or_filter'],
            team()->id,
            auth()->id(),
            $validated['events'],
            $validated['subscription_id']
        );

        return response()->json($result);
    }

    /**
     * Unsubscribe from model updates
     */
    public function unsubscribe(Request $request)
    {
        $request->validate([
            'resource_type'      => 'required|string',
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
     * Keep subscriptions alive by refreshing TTL using subscription IDs
     */
    public function keepaliveByIds(Request $request)
    {
        $validated = $request->validate([
            'subscription_ids'   => 'required|array',
            'subscription_ids.*' => 'string|uuid',
        ]);

        $results = app(PusherSubscriptionService::class)->keepaliveByIds(
            $validated['subscription_ids'],
            auth()->id()
        );

        return response()->json([
            'success'       => true,
            'subscriptions' => $results,
        ]);
    }

}
