# Pusher Subscription System Refactor - Implementation Plan

## Overview
Consolidate all WebSocket subscription patterns into a single `subscribeToModel` system with server-side filtering, TTL-based cache cleanup, centralized subscription tracking, and flexible filter-based subscriptions.

---

## 🎯 Core Design Principles

### Subscription Types

1. **Channel-Wide**: `subscribeToModel('WorkflowRun', ['updated'], true)` - Listen to ALL models of this type
2. **Model-Specific**: `subscribeToModel('WorkflowRun', ['updated'], 123)` - Listen to specific model ID
3. **Filter-Based**: `subscribeToModel('JobDispatch', ['created'], {filter: {...}})` - Listen to models matching filter criteria

### Key Constraints

- `modelIdOrFilter` parameter must NEVER be `null` or `undefined` - throw error if it is
- Use `true` for channel-wide (not `false`)
- Filter objects are hashed to create consistent cache keys
- TTL: 5 minutes on all Redis cache keys
- Keepalive: Every 1 minute to refresh TTL
- Server-side filtering: Only broadcast to users with active subscriptions

---

## 📋 Subscription Parameter Spec

### Frontend Method Signature

```typescript
subscribeToModel(
  resourceType: string,           // e.g., "WorkflowRun", "TaskRun", "JobDispatch"
  events: string[],               // e.g., ["updated", "created"]
  modelIdOrFilter: number | true | { filter: object }
)
```

### Backend Endpoint Payload

```json
{
  "resource_type": "JobDispatch",
  "events": ["created", "updated"],
  "model_id_or_filter": {
    "filter": {
      "jobDispatchables.model_type": "App\\Models\\Workflow\\WorkflowRun",
            "jobDispatchables.model_id": 5
        }
    }
}
```

### Cache Key Format

- Channel-wide: `subscribe:WorkflowRun:{teamId}:all`
- Model-specific: `subscribe:WorkflowRun:{teamId}:id:{modelId}`
- Filter-based: `subscribe:JobDispatch:{teamId}:filter:{hash}`
    - Hash is MD5 of JSON-encoded filter object (sorted keys for consistency)

---

## 📁 Backend Implementation

### Routes

**File: `routes/api.php`**

- **Add**: `POST /api/pusher/subscribe`
- **Add**: `POST /api/pusher/unsubscribe`
- **Add**: `POST /api/pusher/keepalive`
- **Remove**: `POST /api/task-runs/{taskRun}/subscribe-to-processes`
- **Remove**: `POST /api/workflow-runs/{workflowRun}/subscribe-to-job-dispatches`

---

### Controllers

**New File: `app/Http/Controllers/PusherSubscriptionController.php`**

**Methods:**

1. `subscribe(Request $request)`
    - Validate: `resource_type` (required string)
    - Validate: `model_id_or_filter` (required, cannot be null)
    - Validate: Must be integer, boolean true, or object with 'filter' key
    - Get team ID from authenticated user
    - Build cache key using `buildCacheKey()` helper
    - Add user ID to Redis set with 5-minute TTL
    - Return success response

2. `unsubscribe(Request $request)`
    - Same validation as subscribe
    - Build cache key
    - Remove user ID from Redis set
    - Delete Redis key if set is empty
    - Return success response

3. `keepalive(Request $request)`
    - Accept array of subscriptions: `[{resource_type, model_id_or_filter}, ...]`
    - For each subscription, build cache key
    - Check if user is in the set
    - If yes, refresh TTL to 5 minutes
    - Return success response

**Helper Methods:**

1. `buildCacheKey(string $resourceType, int $teamId, $modelIdOrFilter): string`
    - If `$modelIdOrFilter === true`: Return `subscribe:{$resourceType}:{$teamId}:all`
    - If `$modelIdOrFilter` is integer: Return `subscribe:{$resourceType}:{$teamId}:id:{$modelIdOrFilter}`
    - If `$modelIdOrFilter` is array with 'filter':
        - Sort filter keys recursively
    - JSON encode filter
    - Generate MD5 hash
     - Return `subscribe:{$resourceType}:{$teamId}:filter:{$hash}`
     - Store filter in separate cache: `subscribe:{$resourceType}:{$teamId}:filter:{$hash}:definition` => filter object (5min TTL)

2. `validateModelIdOrFilter($value)`
   - Throw exception if null or undefined
    - Validate type is integer, boolean true, or array with 'filter' key
    - Return validated value

**Changes to Existing Controllers:**

**File: `app/Http/Controllers/Ai/TaskRunsController.php`**

- **Remove**: `subscribeToProcesses()` method (around line 16)

**File: `app/Http/Controllers/Ai/WorkflowRunsController.php`**

- **Remove**: `subscribeToJobDispatches()` method (around line 36)

---

### Events - Broadcasting Logic

**All Event Classes Must Implement:**

**Pattern for `broadcastOn()` method:**

1. Get team ID from model
2. Build resource type string (e.g., "WorkflowRun")
3. Query Redis for all subscription types:
    - Channel-wide: `subscribe:{ResourceType}:{teamId}:all`
    - Model-specific: `subscribe:{ResourceType}:{teamId}:id:{this.model.id}`
    - Filter-based: Scan `subscribe:{ResourceType}:{teamId}:filter:*` keys
4. For each filter-based subscription:
    - Retrieve filter definition from `subscribe:{ResourceType}:{teamId}:filter:{hash}:definition`
    - Apply filter to model: `Model::filter($filter)->where('id', $model->id)->exists()`
    - If match, include subscribed users
5. Merge all user ID sets and deduplicate
6. If no users subscribed, return empty array (no broadcast)
7. Return team channel for each subscribed user (not user-specific channels): `new PrivateChannel('{ResourceType}.{teamId}')`

**Files to Update:**

1. **`app/Events/WorkflowRunUpdatedEvent.php`**
    - Resource type: "WorkflowRun"
    - Model: `$this->workflowRun`
    - Team ID: `$this->workflowRun->workflowDefinition->team_id`
    - Implement new `broadcastOn()` logic

2. **`app/Events/TaskRunUpdatedEvent.php`**
    - Resource type: "TaskRun"
    - Model: `$this->taskRun`
    - Team ID: `$this->taskRun->taskDefinition->team_id`

3. **`app/Events/AgentThreadRunUpdatedEvent.php`**
    - Resource type: "AgentThreadRun"
    - Determine team ID from model relationships

4. **`app/Events/AgentThreadUpdatedEvent.php`**
    - Resource type: "AgentThread"
    - Determine team ID from model relationships

5. **`app/Events/TaskProcessUpdatedEvent.php`**
    - Resource type: "TaskProcess"
    - Model: `$this->taskProcess`
    - Team ID: `$this->taskProcess->taskRun->taskDefinition->team_id`
    - **Remove**: Old cache check logic (lines 22-28)

6. **`app/Events/JobDispatchUpdatedEvent.php`**
    - Resource type: "JobDispatch"
    - Model: `$this->jobDispatch`
    - Determine team ID (may need to check dispatchables relationship)
    - **Remove**: Old cache check logic (lines 20-43)
    - **Critical**: This will need filter-based subscriptions for workflow-specific job dispatches

7. **`app/Events/ClaudeCodeGenerationEvent.php`**
    - Resource type: "ClaudeCodeGeneration"
    - Implement new pattern

8. **`app/Events/UsageSummaryUpdatedEvent.php`**
    - Resource type: "UsageSummary"
    - Implement new pattern

9. **`app/Events/TeamObjectUpdatedEvent.php`**
    - Resource type: "TeamObject"
    - Model: `$this->teamObject`
    - Team ID: `$this->teamObject->team_id`
    - Note: Currently sends lightweight payload - decide if this should change to full resource

10. **`app/Events/UiDemandUpdatedEvent.php`**
    - Resource type: "UiDemand"
    - Implement new pattern

**Optional: Shared Trait**

**New File: `app/Traits/BroadcastsWithSubscriptions.php`**

- Create reusable trait to reduce duplication
- Method: `getSubscribedUsers(string $resourceType, int $teamId, Model $model, string $modelClass): array`
    - Implements the subscription checking logic
    - Returns array of user IDs
- Method: `getSubscribedChannels(string $resourceType, int $teamId, array $userIds): array`
    - Converts user IDs to PrivateChannel objects
    - Returns array of channels
- Can be used by all Event classes to simplify `broadcastOn()` implementation

---

### Channel Authorization

**File: `routes/channels.php`**

- No changes needed
- All channels already use `{teamId}` parameter and check team membership
- Server-side filtering in Events handles user-specific targeting

---

### Cache Management

**Redis Cache Structure:**

```
# Channel-wide subscriptions
subscribe:WorkflowRun:5:all => [1, 2, 3]  // User IDs 1,2,3 subscribed to all WorkflowRuns for team 5

# Model-specific subscriptions
subscribe:WorkflowRun:5:id:123 => [1]  // User 1 subscribed to WorkflowRun #123

# Filter-based subscriptions
subscribe:JobDispatch:5:filter:abc123def => [2]  // User 2 subscribed with filter hash abc123def
subscribe:JobDispatch:5:filter:abc123def:definition => {"jobDispatchables.model_type": "..."}  // Filter definition

# All keys have 5-minute TTL
```

**Cache Keys to Deprecate:**

- `subscribe:task-run-processes:{userId}` - replaced by `subscribe:TaskProcess:{teamId}:all` or filter
- `subscribe:workflow-job-dispatches:{userId}:{workflowRunId}` - replaced by filter-based subscription

**Migration Strategy:**

- No migration script needed
- Old keys will expire naturally (current TTL is 60 seconds)
- New system creates new keys on first subscription
- Optional: Flush old pattern keys on deployment for immediate cleanup

---

## 📁 Frontend Implementation

### Core Pusher Module

**File: `spa/src/helpers/pusher.ts`**

**Interfaces to Add:**

```typescript
interface Subscription {
    resourceType: string;
    events: string[];
    modelIdOrFilter: number | true | { filter: object };
}
```

**Data Structures:**

```typescript
// Centralized tracking of all active subscriptions
// Key format: hash of {resourceType, modelIdOrFilter}
const activeSubscriptions = ref<Map<string, Subscription>>(new Map());
```

**Methods to Add:**

1. `getSubscriptionKey(resourceType: string, modelIdOrFilter: number | true | object): string`
    - Generate consistent hash for subscription tracking
    - If `modelIdOrFilter === true`: Return `{resourceType}:all`
    - If `modelIdOrFilter` is number: Return `{resourceType}:id:{modelIdOrFilter}`
    - If `modelIdOrFilter` is object: Return `{resourceType}:filter:{hash}`
    - Hash object by: JSON.stringify(sorted object) → MD5/SHA256

2. `subscribeToModel(resourceType: string, events: string[], modelIdOrFilter: number | true | object): Promise<boolean>`
    - Validate: `modelIdOrFilter` is NOT null or undefined (throw error)
    - Generate subscription key
    - Check if already subscribed (return false if duplicate)
    - Subscribe to Pusher channel: `{resourceType}.{teamId}`
    - Call backend API: `POST /api/pusher/subscribe` with payload
    - Add to `activeSubscriptions` Map
    - Start keepalive timer if not already running
   - Return true

3. `unsubscribeFromModel(resourceType: string, events: string[], modelIdOrFilter: number | true | object): Promise<void>`
   - Generate subscription key
   - Check if subscribed (return early if not)
- Call backend API: `POST /api/pusher/unsubscribe`
- Remove from `activeSubscriptions` Map
- Note: Keep Pusher channel open if other subscriptions for same resource type exist
- Stop keepalive timer if no subscriptions remain

4. `startKeepalive()`
    - Set interval to run every 60 seconds (1 minute)
    - On each tick:
        - If `activeSubscriptions` is empty, call `stopKeepalive()` and return
        - Build payload: Array of all subscriptions `[{resource_type, model_id_or_filter}, ...]`
        - Call: `POST /api/pusher/keepalive` with payload
        - If error, log but don't retry (let subscriptions expire naturally)

5. `stopKeepalive()`
    - Clear interval timer
    - Set timer reference to null

**Code to Remove:**

- `defaultChannelNames` object (lines 26-36)
- `UserSubscription` interface
- `addUserSubscription()`, `removeUserSubscription()`, `fireUserSubscription()`, `continuouslyFireUserSubscriptions()` (lines 70-118)
- `subscribeToProcesses()`, `unsubscribeFromProcesses()` (lines 158-165)
- `subscribeToWorkflowJobDispatches()`, `unsubscribeFromWorkflowJobDispatches()` (lines 167-177)
- Auto-subscription loop in `usePusher()` initialization (lines 148-151)

**Code to Keep:**

- `subscribeToChannel()` - internal method for Pusher channel management
- `fireSubscriberEvents()` - internal event dispatcher
- `onEvent()`, `offEvent()` - rare channel-wide listening (keep for backward compatibility)
- `onModelEvent()`, `offModelEvent()` - can simplify but keep for component-level filtering

**Initialization Changes:**

- Remove auto-subscribe logic
- Initialize Pusher connection only
- No default channel subscriptions

**Return Object:**

```typescript
return {
    pusher,
    subscribeToModel,
    unsubscribeFromModel,
    onEvent,
    offEvent,
    onModelEvent,
    offModelEvent
};
```

---

### Composables Refactor

**File: `spa/src/composables/useAssistantChat.ts`**

**Changes:**

- Line 186: `pusher.onEvent("AgentThread", "updated", async (minimalThread) => { ... })`
    - **Replace with**: `pusher.subscribeToModel("AgentThread", ["updated"], thread.id)`
    - Move subscription call to appropriate lifecycle hook
- Add `onMounted`: Subscribe when thread exists
- Add `onUnmounted`: `pusher.unsubscribeFromModel("AgentThread", ["updated"], thread.id)`
- Consider: Keep `onModelEvent` for client-side filtering or remove if server-side filtering is sufficient

**File: `spa/src/components/Modules/TeamObjects/composables/useTeamObjectUpdates.ts`**

**Changes:**

- Line 106: `pusher.onModelEvent(teamObject, "updated", updateCallback)`
    - **Replace with**: `pusher.subscribeToModel("TeamObject", ["updated"], teamObject.id)`
- Line 121: `pusher.offModelEvent(teamObject, "updated", callback)`
    - **Replace with**: `pusher.unsubscribeFromModel("TeamObject", ["updated"], teamObject.id)`
- Simplify: Remove `activeSubscriptions` Map tracking (now handled by pusher.ts)
- Keep: `loadTeamObjectWithQueue()` for handling rapid updates and preventing duplicate API calls

**File: `spa/src/ui/insurance-demands/composables/useDemands.ts`**

**Changes:**

- Line 310-322: `pusher.onModelEvent(workflowRun, "updated", async (updatedWorkflowRun) => { ... })`
    - **Replace with**: `pusher.subscribeToModel("WorkflowRun", ["updated"], workflowRun.id)`
- Add unsubscribe logic in appropriate cleanup function
- Simplify: Remove `subscribedWorkflowIds` tracking (handled by pusher.ts)
- Consider: Keep debounce logic for `loadDemand()` if needed for API protection
- Update: `clearWorkflowSubscriptions()` to use new unsubscribe method

**File: `spa/src/components/Modules/WorkflowDefinitions/store.ts`**

**Changes:**

- Line 192-196: `pusher.onEvent("TaskRun", "created", async (taskRun: TaskRun) => { ... })`
  - **Decision needed**: Keep as channel-wide OR make workflow-specific?
  - **Option A (Channel-wide)**: `pusher.subscribeToModel("TaskRun", ["created"], true)`
  - **Option B (Filter-based)**: `pusher.subscribeToModel("TaskRun", ["created"], { filter: { workflow_run_id: activeWorkflowRun.value.id } })`
- Add proper lifecycle: Subscribe when store initializes, unsubscribe on cleanup
- Consider: Move subscription to component that uses this store rather than global store

---

### Components Update

**File: `spa/src/components/Modules/TaskDefinitions/Panels/TaskRunCard.vue`**

**Changes:**

- Line 89: `usePusher().subscribeToProcesses(props.taskRun)`
  - **Replace with**: `usePusher().subscribeToModel("TaskProcess", ["updated", "created"], true)`
  - **Alternative**: Filter by task run: `subscribeToModel("TaskProcess", ["updated", "created"], { filter: { task_run_id: props.taskRun.id } })`
- Add `onUnmounted`: Unsubscribe when hiding processes
- Update: Watch on `isShowingProcesses` to handle subscribe/unsubscribe

**File: `spa/src/components/Modules/WorkflowCanvas/ShowTaskProcessesButton.vue`**

**Changes:**
- Line 186: `usePusher().subscribeToProcesses(props.taskRun)`
  - **Replace with**: `usePusher().subscribeToModel("TaskProcess", ["updated", "created"], true)`
  - **Alternative**: Filter by task run: `subscribeToModel("TaskProcess", ["updated", "created"], { filter: { task_run_id: props.taskRun.id } })`
- Line 189: `usePusher().unsubscribeFromProcesses()`
  - **Replace with**: `usePusher().unsubscribeFromModel("TaskProcess", ["updated", "created"], true)`
  - Use same filter as subscribe if using filter-based

**File: `spa/src/components/Modules/WorkflowDefinitions/WorkflowWorkersInfoDialog.vue`**

**Changes:**

- Line 303: `await pusher.subscribeToWorkflowJobDispatches(props.workflowRun)`
    - **Replace with**:
      ```typescript
      await pusher.subscribeToModel("JobDispatch", ["updated", "created"], {
        filter: {
          'jobDispatchables.model_type': 'App\\Models\\Workflow\\WorkflowRun',
          'jobDispatchables.model_id': props.workflowRun.id
        }
      })
      ```
- Line 307: `pusher.unsubscribeFromWorkflowJobDispatches()`
    - **Replace with**: `unsubscribeFromModel()` with same parameters as subscribe

**File: `spa/src/ui/insurance-demands/components/Detail/ViewWorkflowDialog.vue`**

- Review for any direct pusher subscription usage
- Update to new pattern if found

---

### TypeScript Types

**File: `spa/src/helpers/pusher.ts`**

- **Remove**: `UserSubscription` interface
- **Add**: `Subscription` interface
- **Update**: Return type of `usePusher()` to reflect new methods

**File: `spa/src/types/workflows.d.ts`**

- **Remove**: Line 57: `subscribeToJobDispatches(workflowRun: WorkflowRun): Promise<{ success: boolean }>;`

**File: `spa/src/types/task-definitions.d.ts`**

- **Remove**: Any `subscribeToProcesses` definitions if present

---

### Route Configuration

**File: `spa/src/components/Modules/WorkflowDefinitions/WorkflowRuns/config/routes.ts`**
- **Remove**: Line 8: `subscribeToJobDispatches: async (workflowRun) => await request.get(...)`

**File: `spa/src/components/Modules/TaskDefinitions/TaskRuns/config/routes.ts`**
- **Remove**: `subscribeToProcesses` route definition if present

---

## 📁 Shared Utilities

### Hash Generation

**Backend (PHP):**

```php
// In PusherSubscriptionController or trait
private function hashFilter(array $filter): string
{
    ksort($filter); // Sort by keys
    // Recursively sort nested arrays
    array_walk_recursive($filter, function(&$value) {
        if (is_array($value)) {
            ksort($value);
        }
    });

    $json = json_encode($filter, JSON_UNESCAPED_SLASHES);
    return md5($json);
}
```

**Frontend (TypeScript):**

```typescript
import md5 from 'js-md5';

function hashFilter(filter: object): string {
    // Sort object keys recursively
    const sorted = sortObjectKeys(filter);
    const json = JSON.stringify(sorted);
    // Use js-md5 library (lightweight, ~3KB)
    return md5(json);
}

function sortObjectKeys(obj: any): any {
    if (Array.isArray(obj)) {
        return obj.map(sortObjectKeys);
    } else if (obj !== null && typeof obj === 'object') {
        return Object.keys(obj)
            .sort()
            .reduce((result, key) => {
                result[key] = sortObjectKeys(obj[key]);
                return result;
            }, {});
    }
    return obj;
}
```

**Important**: Both frontend and backend must use IDENTICAL sorting and hashing algorithms to generate matching keys.

---

## 📊 Usage Examples

### Example 1: Channel-Wide Subscription

```typescript
// Listen to ALL WorkflowRuns for the team
await pusher.subscribeToModel("WorkflowRun", ["updated", "created"], true);

// Later, unsubscribe
await pusher.unsubscribeFromModel("WorkflowRun", ["updated", "created"], true);
```

### Example 2: Model-Specific Subscription

```typescript
// Listen to specific WorkflowRun
const workflowRun = { id: 123 };
await pusher.subscribeToModel("WorkflowRun", ["updated"], workflowRun.id);

// Later, unsubscribe
await pusher.unsubscribeFromModel("WorkflowRun", ["updated"], workflowRun.id);
```

### Example 3: Filter-Based Subscription

```typescript
// Listen to JobDispatches for specific WorkflowRun
await pusher.subscribeToModel("JobDispatch", ["created", "updated"], {
    filter: {
        'jobDispatchables.model_type': 'App\\Models\\Workflow\\WorkflowRun',
        'jobDispatchables.model_id': 5
    }
});

// Later, unsubscribe with SAME filter object
await pusher.unsubscribeFromModel("JobDispatch", ["created", "updated"], {
    filter: {
        'jobDispatchables.model_type': 'App\\Models\\Workflow\\WorkflowRun',
        'jobDispatchables.model_id': 5
    }
});
```

### Example 4: Simultaneous Subscriptions

```typescript
// User on list page - subscribe to all
await pusher.subscribeToModel("WorkflowRun", ["updated"], true);

// User clicks into detail - subscribe to specific
await pusher.subscribeToModel("WorkflowRun", ["updated"], 123);

// Both are active simultaneously
// Backend sends WorkflowRun #123 because of BOTH subscriptions

// User closes list page
await pusher.unsubscribeFromModel("WorkflowRun", ["updated"], true);

// Model-specific subscription still active
// Backend still sends WorkflowRun #123 updates ✅
```

### Example 5: Component Lifecycle

```vue
<script setup>
import { onMounted, onUnmounted } from 'vue';
import { usePusher } from '@/helpers/pusher';

const props = defineProps<{ workflowRun: WorkflowRun }>();
const pusher = usePusher();

onMounted(async () => {
        await pusher.subscribeToModel("WorkflowRun", ["updated"], props.workflowRun.id);
    });

    onUnmounted(async () => {
        await pusher.unsubscribeFromModel("WorkflowRun", ["updated"], props.workflowRun.id);
    });
</script>
```

---

## 🧪 Testing Strategy

### Backend Tests

**File: `tests/Feature/PusherSubscriptionControllerTest.php` (new)**

**Test Cases:**

1. Subscribe to channel-wide (model_id_or_filter = true)
2. Subscribe to specific model ID (integer)
3. Subscribe with filter object
4. Reject null/undefined model_id_or_filter
5. Unsubscribe removes user from Redis set
6. Unsubscribe deletes empty Redis sets
7. Keepalive refreshes TTL for all subscriptions
8. Multiple users can subscribe to same resource
9. TTL expiration removes subscriptions after 5 minutes
10. Filter hash generation is consistent
11. Same filter object generates same cache key

**File: `tests/Unit/Events/WorkflowRunUpdatedEventTest.php`**

**Test Cases:**

1. Channel-wide subscription receives events
2. Model-specific subscription receives only that model's events
3. Filter-based subscription receives matching events only
4. No subscription = no broadcast (empty channel array)
5. Multiple subscriptions (channel-wide + model-specific + filter) all receive event
6. User deduplication works correctly
7. Filter matching uses Model::filter() correctly

**Repeat similar tests for all Event classes**

### Frontend Tests

**File: `spa/src/helpers/pusher.test.ts` (new or update)**

**Test Cases:**

1. `subscribeToModel` with `true` (channel-wide)
2. `subscribeToModel` with integer ID (model-specific)
3. `subscribeToModel` with filter object
4. Error thrown when model_id_or_filter is null
5. Error thrown when model_id_or_filter is undefined
6. Duplicate subscription detection (returns false)
7. `unsubscribeFromModel` removes from tracker
8. Filter hash generation matches backend
9. Same filter object generates same hash
10. Different filter object order generates same hash (key sorting)
11. Keepalive sends correct payload
12. Keepalive starts when first subscription added
13. Keepalive stops when last subscription removed

### Integration Tests

**File: `tests/Feature/PusherIntegrationTest.php` (new)**

**Test Cases:**

1. Frontend subscribes → Backend cache updated
2. Event triggered → Only subscribed users receive
3. Frontend unsubscribes → No longer receives events
4. TTL expires → Events stop being received
5. Keepalive extends subscription → Events continue
6. Filter subscription → Only matching events received
7. Multiple users → Each receives according to their subscriptions

---

## 📚 Documentation Updates

### SPA Patterns Guide

**File: `spa/SPA_PATTERNS_GUIDE.md`**

**Add Section: "WebSocket Subscriptions with Pusher"**

**Content to Add:**

- Overview of subscription system
- When to use channel-wide vs model-specific vs filter-based
- Lifecycle management (onMounted/onUnmounted)
- How keepalive works
- TTL implications (5 minutes, refresh every minute)
- Example: Detail view subscription
- Example: List view subscription
- Example: Filter-based subscription
- Example: Handling simultaneous subscriptions
- Important: Never pass null/undefined for modelIdOrFilter
- Important: Use `true` for channel-wide, not `false`

### Backend Patterns Guide

**File: `LARAVEL_BACKEND_PATTERNS_GUIDE.md` or new `WEBSOCKET_BROADCASTING_GUIDE.md`**

**Content to Add:**

- How to implement `broadcastOn()` in Event classes
- Cache key format specification
- Filter matching with `Model::filter()` macro
- How to use `BroadcastsWithSubscriptions` trait (if implemented)
- Example: Creating new resource with broadcasting support
- Redis cache structure documentation
- Server-side filtering logic explanation

---

## 📅 Implementation Phases

### Phase 1: Backend Foundation

1. Create `PusherSubscriptionController`
2. Add routes to `api.php`
3. Implement hash generation utility
4. Create optional `BroadcastsWithSubscriptions` trait
5. Write backend unit tests for controller
6. Test Redis cache operations

### Phase 2: Backend Events Refactor

1. Create `BroadcastsWithSubscriptions` trait with shared logic
2. Update all 11+ Event classes with new `broadcastOn()` logic
3. **Refactor all Event `data()` methods to lightweight payloads**
4. Implement filter matching with `Model::filter()`
5. Test each Event class individually
6. Verify frontend still works with lightweight payloads
7. Write unit tests for each Event
8. Verify cache key generation is consistent

### Phase 3: Frontend Core

1. Install `js-md5` package: `npm install js-md5 --save` (or yarn)
2. Refactor `pusher.ts`:
    - Remove old code
    - Add new `subscribeToModel`/`unsubscribeFromModel` methods
    - Add hash generation utility using js-md5 (matching backend)
    - Implement keepalive timer
3. Add TypeScript interfaces
4. Remove auto-subscription from App.vue

### Phase 4: Frontend Components Migration

1. Update composables:
    - `useAssistantChat.ts`
    - `useTeamObjectUpdates.ts`
    - `useDemands.ts`
2. Update components:
    - `TaskRunCard.vue`
    - `ShowTaskProcessesButton.vue`
    - `WorkflowWorkersInfoDialog.vue`
    - `ViewWorkflowDialog.vue`
3. Update store: `WorkflowDefinitions/store.ts`
4. Remove old route definitions
5. Test each component individually

### Phase 5: Documentation & Cleanup

1. Update `SPA_PATTERNS_GUIDE.md`
2. Create or update `WEBSOCKET_BROADCASTING_GUIDE.md`
3. Add inline code comments
4. Remove deprecated code

---

## ✅ Success Criteria

- ✅ Single `subscribeToModel` method handles all subscription types
- ✅ Channel-wide, model-specific, and filter-based subscriptions all work
- ✅ `modelIdOrFilter` validation prevents null/undefined bugs
- ✅ Backend and frontend hash generation is identical (using js-md5)
- ✅ Server-side filtering reduces unnecessary broadcasts
- ✅ **All Event payloads are lightweight (IDs, status, simple fields only)**
- ✅ **No relationships or large fields in broadcast payloads**
- ✅ `BroadcastsWithSubscriptions` trait implemented and used
- ✅ Keepalive maintains subscriptions with 5-minute TTL
- ✅ No auto-subscriptions on app init
- ✅ All components explicitly manage subscriptions
- ✅ Multiple subscription types can coexist simultaneously
- ✅ Unsubscribing from one doesn't affect others
- ✅ Redis cache uses consistent key format
- ✅ Filter-based subscriptions work with SCAN approach
- ✅ All backend tests pass
- ✅ Documentation is complete and accurate
- ✅ No old subscription patterns remain in codebase
- ✅ No backwards compatibility or legacy code related to subscriptions
- ✅ No dead code remains

---

## 🚨 Critical Implementation Notes

### Hash Generation MUST Match

- Backend and frontend must use IDENTICAL hashing algorithms
- Both must sort object keys recursively before hashing
- Both must use same hash function MD5

### Filter Object Structure

**Backend uses Laravel's `filter()` macro from Danx FilterBuilder**

The filter macro accepts an array of filter conditions that can query the model and its relationships. On the backend, filters are validated using:

```php
Model::filter($filter)->where('id', $model->id)->exists()
```

#### Supported Filter Syntax

**Basic Equality:**
```php
['field_name' => 'value']  // WHERE field_name = 'value'
['field_name' => ['value1', 'value2']]  // WHERE field_name IN ('value1', 'value2')
```

**Comparison Operators:**
```php
['field_name' => ['>' => 100]]  // WHERE field_name > 100
['field_name' => ['>=' => 100]]  // WHERE field_name >= 100 (also: 'from', 'start')
['field_name' => ['<' => 100]]  // WHERE field_name < 100
['field_name' => ['<=' => 100]]  // WHERE field_name <= 100 (also: 'to', 'end')
['field_name' => ['!=' => 'value']]  // WHERE field_name != 'value'
['field_name' => ['=' => 'value']]  // WHERE field_name = 'value' (explicit)
```

**String Operators:**
```php
['field_name' => ['like' => 'search']]  // WHERE field_name LIKE '%search%'
['field_name' => ['not like' => 'search']]  // WHERE field_name NOT LIKE '%search%'
```

**Null Checks:**
```php
['field_name' => ['null' => true]]  // WHERE field_name IS NULL
['field_name' => ['null' => false]]  // WHERE field_name IS NOT NULL
```

**Relationship Filtering (Dot Notation):**
```php
['relationship.field' => 'value']  // Joins relationship table and filters
['jobDispatchables.model_type' => 'App\\Models\\Workflow\\WorkflowRun']
['taskRun.workflow_run_id' => 5]
```

**Grouping with AND/OR:**
```php
[
  'and' => [
    ['field1' => 'value1'],
    ['field2' => 'value2']
  ]
]

[
  'or' => [
    ['status' => 'Running'],
    ['status' => 'Pending']
  ]
]

// Nested groups
[
  'and' => [
    ['field1' => 'value1'],
    [
      'or' => [
        ['field2' => 'value2'],
        ['field3' => 'value3']
      ]
    ]
  ]
]
```

#### Filter Examples for Subscriptions

**JobDispatch for specific WorkflowRun:**
```typescript
{
  filter: {
    'jobDispatchables.model_type': 'App\\Models\\Workflow\\WorkflowRun',
    'jobDispatchables.model_id': workflowRun.id
  }
}
```

**TaskProcess for specific TaskRun:**
```typescript
{
  filter: {
    'task_run_id': taskRun.id
  }
}
```

**TaskRun for specific WorkflowRun:**
```typescript
{
  filter: {
    'workflow_run_id': workflowRun.id
  }
}
```

**Running or Pending WorkflowRuns:**
```typescript
{
  filter: {
    'or': [
      { 'status': 'Running' },
      { 'status': 'Pending' }
    ]
  }
}
```

**JobDispatch with status and type filters:**
```typescript
{
  filter: {
    'and': [
      { 'status': ['Running', 'Pending'] },  // IN operator
      { 'jobDispatchables.model_type': 'App\\Models\\Workflow\\WorkflowRun' }
    ]
  }
}
```

#### Backend Validation

When a filtered event is broadcast, the backend:
1. Retrieves filter definition from cache: `subscribe:{Resource}:{teamId}:filter:{hash}:definition`
2. Applies filter to model: `JobDispatch::filter($filter)->where('id', $jobDispatch->id)->exists()`
3. Only broadcasts if filter matches (returns true)

**Important:** Filter syntax must be valid for the model being filtered. Invalid filters will cause the `exists()` check to fail, preventing broadcast.

### Error Handling

- Backend validation errors should return clear messages
- Frontend should handle subscribe/unsubscribe failures gracefully
- Keepalive failures should be logged but not block user actions

### Migration Strategy

- Old cache keys will expire naturally (60s TTL currently)
- No data migration needed

---

## 🔧 Front End UI Subscription Debug Panel

1. **WebSocket /Subscription Connection Status**: Show user when subscriptions are active/inactive
2. **Event Logger**: Log and group events received so devs can easily see event counts and monitor all incoming events
   to validate the correct filtered events are firing and performance is optimal
3. **Filtering**: Simple text filtering on event payloads to find specific events (and by resource type as well as drop
   down)

---

## 📞 Questions & Decisions

### Resolved

- ✅ Use `true` for channel-wide (not `false`)
- ✅ TTL duration: 5 minutes
- ✅ Keepalive frequency: 1 minute
- ✅ Team channels only, server-side filtering
- ✅ Remove all auto-subscriptions
- ✅ Let subscriptions expire on keepalive failure
- ✅ Support filter-based subscriptions
- ✅ Use hash for filter cache keys

### Open Questions

None - all questions resolved below.

### Newly Resolved

- ✅ **TeamObject payload**: Keep lightweight - **AND UPDATE ALL EVENTS TO LIGHTWEIGHT**
- ✅ **All Event payloads**: Send only essential fields (name, status, ids) - NEVER relationships or large fields (logs,
  etc.)
- ✅ **Filter performance**: Acceptable - use SCAN approach, optimize later if needed
- ✅ **Code sharing**: Use `BroadcastsWithSubscriptions` trait
- ✅ **Frontend hash library**: `js-md5` package (lightweight, ~3KB)
- ✅ **Error notification**: Log errors, don't block user (let subscriptions expire gracefully)

---

## 🎯 CRITICAL: Lightweight Event Payloads (New Requirement)

**ALL broadcast events must send lightweight payloads for performance.**

### Payload Rules

**INCLUDE:**

- IDs (primary key, foreign keys, root_object_id, etc.)
- Status fields (status, state, phase, etc.)
- Simple strings (name, title, type, etc.)
- Timestamps (created_at, updated_at, completed_at, etc.)
- Simple counts (process_count, task_count, etc.)
- Simple flags (is_running, is_complete, etc.)

**EXCLUDE:**

- Relationships (NEVER include nested objects)
- Large text fields (logs, content, description, raw_data, etc.)
- Binary/JSON blobs (settings, meta, config, etc.)
- Computed/virtual attributes that require queries

### Implementation

**CRITICAL ARCHITECTURE RULE:**
Broadcast payloads MUST be a subset of the corresponding Resource class. Frontend uses `storeObject()` which requires
consistent Resource structure.

**Each Event's `data()` method must:**

1. **Use the corresponding Resource class** (e.g., `JobDispatchResource`, `WorkflowRunResource`)
2. **Pass explicit fields parameter** to include only lightweight fields
3. Only include base fields (IDs, status, timestamps, simple strings/counts)
4. **EXCLUDE fields wrapped in `fn()`** in the Resource (these are relationships/large data)

**Example:**

```php
// app/Events/JobDispatchUpdatedEvent.php
public function data(): array
{
    // Use Resource::make() with explicit lightweight fields only
    return JobDispatchResource::make($this->jobDispatch, [
        'id' => true,
        'name' => true,
        'ref' => true,
        'job_batch_id' => true,
        'status' => true,
        'ran_at' => true,
        'completed_at' => true,
        'timeout_at' => true,
        'run_time_ms' => true,
        'count' => true,
        'created_at' => true,
        // ❌ EXCLUDE: logs, errors, apiLogs (fn() fields - too large)
    ]);
}
```

**Resource Structure Reference:**

```php
// app/Resources/Audit/JobDispatchResource.php
public static function data(JobDispatch $jobDispatch): array
{
    return [
        // ✅ INCLUDE in broadcasts (lightweight)
        'id' => $jobDispatch->id,
        'name' => $jobDispatch->name,
        'status' => $jobDispatch->status,
        'created_at' => $jobDispatch->created_at,
        // ... other simple fields

        // ❌ EXCLUDE from broadcasts (fn() wrapped - heavy/expensive)
        'logs' => fn() => $jobDispatch->runningAuditRequest?->logs ?? '',
        'errors' => fn($fields) => ErrorLogEntryResource::collection(...),
        'apiLogs' => fn($fields) => ApiLogResource::collection(...),
    ];
}
```

### Frontend Impact

**Clients receive Resource-structured data via `storeObject()`:**

1. Broadcast payload is **subset of Resource fields** (not full object)
2. Frontend uses `storeObject()` to merge broadcast data with existing stored objects
3. `storeObject()` automatically updates all references to that object across the app
4. Use broadcast as "change notification" - status/name updates show immediately
5. Fetch full details via API only if needed (logs, relationships, etc.)
6. **Frontend code does NOT need changes** - Resource structure is consistent

**Why this works:**

- Resource structure is identical between API and broadcast (just fewer fields)
- `storeObject()` merges new fields into existing object
- `__type` field ensures correct resource type identification
- Frontend already handles partial objects gracefully

### Migration for Existing Events

**All 11+ Event classes need payload review:**

1. **Identify the corresponding Resource class** for each Event
2. Audit the Resource's `data()` method - identify lightweight fields vs `fn()` wrapped fields
3. Update Event's `data()` method to use `Resource::make($model, [fields])` with explicit field list
4. **Only include** non-fn() fields (IDs, status, timestamps, simple strings/counts)
5. **Exclude** fn() wrapped fields (relationships, logs, large data)
6. Test frontend with new payloads - should work automatically via `storeObject()`
7. Update Event tests to expect new Resource-based payload structure

**Rule of Thumb:**
If a field is wrapped in `fn()` in the Resource class, it's too expensive for broadcasts.

This is a **significant scope addition** but improves performance across the board.

---

This plan is comprehensive and ready for implementation by specialized agents. Each section provides enough detail to
implement independently while maintaining consistency across the system.
