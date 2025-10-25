# Laravel Backend Patterns Guide - GPT Manager

## Core Architecture Overview

This application follows a strict **Service-Repository-Controller** pattern with **danx library** integration for standardized CRUD operations, team-based access control, and modern Laravel best practices.

### Key Principles

- **ZERO TECH DEBT**: No legacy code, no backwards compatibility
- **ONE WAY TO DO EVERYTHING**: The correct, modern way only
- **DRY PRINCIPLES**: Don't Repeat Yourself - extract reusable patterns
- **TEAM-BASED ACCESS CONTROL**: All data is scoped to teams automatically
- **danx LIBRARY INTEGRATION**: Standardized patterns for CRUD, resources, and routing

### Coding Standards

#### Namespace and Import Rules

- **CRITICAL: ALL class imports MUST use namespace `use` statements at the top of the file**
- **NEVER use inline class references** like `\App\Models\User::find()` or `\DB::table()`
- **ALWAYS add proper use statements** at the top: `use App\Models\User;` then use `User::find()`
- **This applies to ALL classes**: models, services, repositories, facades, exceptions, traits, interfaces
- **Group imports logically**: Laravel/PHP core first, then third-party packages, then app classes

#### Dependency Injection Rules

- **CRITICAL: ALWAYS use `app()` helper for ALL dependency injection**
- **NEVER use constructor injection** (`public function __construct(Service $service)`)
- **NEVER use `new Service()`** - always use `app(Service::class)`
- **This applies EVERYWHERE**: services, repositories, controllers, jobs, listeners
- **Better readability**: Inline `app(Service::class)->method()` calls are preferred
- **More condensed code**: No need for constructor setup or class properties

```php
// ✅ CORRECT - Namespace imports and app() helper usage
use App\Models\User;
use App\Services\UserService;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;

class ExampleService
{
    public function processUser($id)
    {
        $user = User::find($id);
        
        // Use app() helper for all dependency injection
        $userData = app(UserRepository::class)->getUserData($user);
        $result = app(UserService::class)->transformData($userData);
        
        return $result;
    }
}

// ❌ WRONG - Constructor injection
class BadExampleService
{
    public function __construct(
        private UserRepository $userRepo,
        private UserService $userService
    ) {}
    
    public function processUser($id) {
        // This is the old pattern - DON'T DO THIS
    }
}

// ❌ WRONG - Using new keyword
class AnotherBadExample
{
    public function processUser($id) {
        $service = new UserService(); // NEVER DO THIS
    }
}
```

---

## 1. Service Layer Patterns

Services contain **ALL** business logic. They are the heart of the application architecture.

### Service Structure Template

```php
<?php

namespace App\Services\[Domain];

use App\Models\[Domain]\[Model];
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;

class [Domain][Action]Service
{
    public function [actionMethod]([Model] $model, array $data = []): [ReturnType]
    {
        $this->validate[Action]($model, $data);

        return DB::transaction(function () use ($model, $data) {
            // Use app() helper for all service/repository calls
            app([Repository]::class)->updateModel($model, $data);
            app([OtherService]::class)->processRelatedData($model);
            
            return $model->fresh();
        });
    }

    protected function validate[Action]([Model] $model, array $data): void
    {
        // Validation logic with meaningful error messages
        if (!$this->isValid($model, $data)) {
            throw new ValidationError('Descriptive error message', 400);
        }
    }

    protected function validateOwnership([Model] $model): void
    {
        $currentTeam = team();
        if (!$currentTeam || $model->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this resource', 403);
        }
    }
}
```

### Service Patterns by Type

- **Data Processing**: `TaskProcessExecutorService`, `ArtifactDeduplicationService`
- **Integration**: `AgentThreadService`, `WorkflowRunnerService`, `UsageTrackingService`
- **Transformation**: `WorkflowExportService`, `JSONSchemaDataToDatabaseMapper`

---

## 2. Repository Patterns

Repositories handle **ONLY** data access. They extend `ActionRepository` from danx and implement team-based scoping.

### Repository Structure Template

```php
<?php

namespace App\Repositories;

use App\Models\[Domain]\[Model];
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class [Model]Repository extends ActionRepository
{
    public static string $model = [Model]::class;

    public function query(): Builder
    {
        $query = parent::query()->where('team_id', team()->id);
        
        // Add additional scoping as needed
        if (!can('view_imported_schemas')) {
            $query->whereDoesntHave('schemaDefinition.resourcePackageImport', 
                fn(Builder $builder) => $builder->where('can_view', 0)
            );
        }

        return $query;
    }

    public function applyAction(string $action, [Model]|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->create[Model]($data),
            'update' => $this->update[Model]($model, $data),
            'custom-action' => $this->customAction($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }
}
```

---

## 3. Controller Patterns

Controllers are **THIN** - they handle validation, delegation, and response formatting only.

### Controller Structure Template

```php
<?php

namespace App\Http\Controllers\[Domain];

use App\Models\[Domain]\[Model];
use App\Repositories\[Model]Repository;
use App\Resources\[Domain]\[Model]Resource;
use App\Services\[Domain]\[Service];
use Newms87\Danx\Http\Controllers\ActionController;

class [Model]sController extends ActionController
{
    public static ?string $repo = [Model]Repository::class;
    public static ?string $resource = [Model]Resource::class;

    public function customAction([Model] $model, [OtherModel] $otherModel)
    {
        // Use app() helper - this is the standard pattern everywhere
        $result = app([Service]::class)->performAction($model, $otherModel);
        return new [Model]Resource($result);
    }
}
```

**CRITICAL**: ALL classes MUST use `app()` helper for dependency injection (better readability and condensed code).

---

## 4. Model Patterns

Models contain **ONLY** relationships, scopes, casts, attributes, and simple queries. NO business logic.

### Model Structure Template

```php
<?php

namespace App\Models\[Domain];

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class [Model] extends Model implements AuditableContract
{
    use AuditableTrait, ActionModelTrait, SoftDeletes;

    // Constants for enums (NO hard-coded strings)
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $table = '[table_name]';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function casts(): array
    {
        return [
            'meta' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // Validation method
    public function validate(): static
    {
        // Validation logic that throws ValidationError
        return $this;
    }
}
```

---

## 5. API Resource Patterns

Resources handle API data transformation. They extend `ActionResource` from danx.

### Resource Structure Template

```php
<?php

namespace App\Resources\[Domain];

use App\Models\[Domain]\[Model];
use Newms87\Danx\Resources\ActionResource;

class [Model]Resource extends ActionResource
{
    public static function data([Model] $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'status' => $model->status,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            
            // Computed fields
            'display_name' => static::getDisplayName($model),
            
            // Relationships (loaded conditionally)
            'children' => static::loadChildren($model),
        ];
    }
}
```

---

## 6. Database & Migration Patterns

### Migration Structure (Anonymous Classes - Laravel 9+ Style)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('[table_name]', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('status')->default('[default_status]');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['team_id', 'status']);
            $table->unique(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('[table_name]');
    }
};
```

### Key Migration Principles

- **Anonymous classes**: Modern Laravel 9+ style
- **Foreign key constraints**: Proper cascading deletes
- **Team-based scoping**: `team_id` on all relevant tables
- **NEVER use `->comment()`**: Doesn't work with PostgreSQL
- **Composite indexes**: For query performance
- **Soft deletes**: For audit trails

---

## 7. Testing Patterns

### CRITICAL Testing Principles

**NEVER TEST CONTROLLERS DIRECTLY** - Due to Laravel configuration issues causing 503 errors, ALL controller testing is PROHIBITED.

**NEVER:**
- Use `Mockery::mock(...)` - ALWAYS use `$this->mock(...)`
- Mock database interactions - USE THE DATABASE!
- Test Laravel framework features (fillable, casts, relationships)
- Use static mocking: `Mockery::mock('alias:' . StaticService::class)` - FORBIDDEN

**ALWAYS:**
- Use real database interactions with factories
- Only mock 3rd party API calls
- Test the complete system behavior
- Verify database state changes
- Run `./vendor/bin/sail test` before completing any work

### Service Testing Template

```php
<?php

namespace Tests\Unit\Services\[Domain];

use App\Models\[Domain]\[Model];
use App\Services\[Domain]\[Service];
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class [Service]Test extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_[action]_with[Condition]_[expectedResult](): void
    {
        // Given
        $model = [Model]::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $data = ['key' => 'value'];

        // When - Always use app() helper for service calls
        $result = app([Service]::class)->[action]($model, $data);

        // Then
        $this->assertInstanceOf([Model]::class, $result);
        $this->assertEquals('expected_value', $result->field);
        $this->assertDatabaseHas('[table_name]', [
            'id' => $model->id,
            'key' => 'value'
        ]);
    }
}
```

---

## 8. Background Processing Patterns

### Job Structure Template

```php
<?php

namespace App\Jobs;

use App\Models\[Domain]\[Model];
use App\Services\[Domain]\[Service];
use Newms87\Danx\Jobs\Job;

class [Action][Model]Job extends Job
{
    public int $timeout = 300;

    public function __construct(private [Model] $model, private array $data = [])
    {
        parent::__construct();
    }

    public function ref(): string
    {
        return '[action]-[model]-' . $this->model->id;
    }

    public function run(): void
    {
        // Use app() helper for all dependency injection
        app([Service]::class)->[action]($this->model, $this->data);
    }
}
```

---

## 9. API Routing Patterns

### ActionRoute Pattern (danx)

```php
// In routes/api.php
use Newms87\Danx\Http\Routes\ActionRoute;

// Standard CRUD routes (index, show, store, update, destroy)
ActionRoute::routes('[route-prefix]', new [Model]Controller);

// With additional custom routes
ActionRoute::routes('[route-prefix]', new [Model]Controller, function () {
    Route::post('{model}/custom-action', [[Model]Controller::class, 'customAction']);
});
```

### Generated Routes

ActionRoute automatically creates:
- `GET /[prefix]` → `index()` (list with pagination, filtering, sorting)
- `GET /[prefix]/{id}` → `show()` (single resource)
- `POST /[prefix]` → `store()` (create new)
- `PUT /[prefix]/{id}` → `update()` (update existing)
- `DELETE /[prefix]/{id}` → `destroy()` (delete)

---

## 10. Team-Based Access Control

### CRITICAL: ALL repositories and services MUST implement team scoping

```php
// Repository query scoping
public function query(): Builder
{
    return parent::query()->where('team_id', team()->id);
}

// Service ownership validation
protected function validateOwnership([Model] $model): void
{
    $currentTeam = team();
    if (!$currentTeam || $model->team_id !== $currentTeam->id) {
        throw new ValidationError('You do not have permission to access this resource', 403);
    }
}
```

---

## 11. Error Handling Patterns

### Service-Level Validation

```php
protected function validateAction([Model] $model, array $data): void
{
    if (empty($data['required_field'])) {
        throw new ValidationError('Required field is missing', 400);
    }

    if ($model->status === [Model]::STATUS_LOCKED) {
        throw new ValidationError('Cannot modify locked resource', 409);
    }
}
```

Controllers don't handle errors - they bubble up to Laravel's exception handler.

---

## 12. Performance Patterns

### Database Query Optimization

```php
// Eager loading relationships
$models = [Model]::with(['children', 'parent'])->get();

// Query scoping
public function scopeWithRelatedData(Builder $query): Builder
{
    return $query->with([
        'children' => fn($q) => $q->select(['id', 'parent_id', 'name']),
        'parent:id,name'
    ]);
}

// Chunked processing for large datasets
[Model]::chunk(1000, function ($models) {
    foreach ($models as $model) {
        // Process each model
    }
});
```

---

## File Organization Standards

```
app/
├── Console/Commands/           # Artisan commands
├── Events/                     # Domain events
├── Http/
│   ├── Controllers/            # Thin controllers
│   │   └── [Domain]/          # Grouped by domain
│   └── Resources/             # API resources
│       └── [Domain]/          # Grouped by domain
├── Jobs/                      # Background jobs
├── Listeners/                 # Event listeners
├── Models/                    # Eloquent models
│   └── [Domain]/              # Grouped by domain
├── Repositories/              # Data access layer
├── Services/                  # Business logic
│   └── [Domain]/              # Grouped by domain
└── Traits/                    # Shared model traits

tests/
├── Feature/                   # Integration tests
│   └── [Domain]/              # Grouped by domain
├── Unit/                      # Unit tests
│   └── Services/              # Service tests
└── Traits/                    # Test utilities
```

---

## 7. Broadcasting and Events

All model update events extend `ModelSavedEvent` from the danx library, which provides subscription-based broadcasting with resource-type extraction and team-based filtering.

### ModelSavedEvent Base Class

The danx library's `ModelSavedEvent` (`../danx/src/Events/ModelSavedEvent.php`) provides:

- **Automatic resource type extraction** from Resource class names
- **Team ID resolution** (simple via constructor, complex via override)
- **Subscription-based broadcasting** via `BroadcastsWithSubscriptions` trait integration
- **Lock-based duplicate prevention** to avoid multiple broadcasts
- **Static helper methods** for easy event dispatch

### Event Structure Template

```php
<?php

namespace App\Events;

use App\Models\[Domain]\[Model];
use App\Resources\[Domain]\[Model]Resource;
use Newms87\Danx\Events\ModelSavedEvent;

class [Model]UpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected [Model] $model, protected string $event)
    {
        parent::__construct(
            $model,
            $event,
            [Model]Resource::class,  // Resource class for type extraction
            $model->team_id           // Team ID (or null for complex resolution)
        );
    }

    public function data(): array
    {
        // Return lightweight payload - IDs, status, timestamps only
        return [Model]Resource::make($this->model, [
            'id' => true,
            'name' => true,
            'status' => true,
            'created_at' => true,
            'updated_at' => true,
            // NO relationships, NO large fields (logs, content, etc.)
        ]);
    }
}
```

### Complex Team ID Resolution

For models where team_id requires traversing relationships or polymorphic resolution:

```php
class JobDispatchUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected JobDispatch $jobDispatch, protected string $event)
    {
        // Don't pass team_id - will be resolved via getTeamId()
        parent::__construct(
            $jobDispatch,
            $event,
            JobDispatchResource::class
        );
    }

    protected function getTeamId(): ?int
    {
        // Custom team ID resolution logic
        $dispatchable = DB::table('job_dispatchables')
            ->where('job_dispatch_id', $this->jobDispatch->id)
            ->first();

        if (!$dispatchable) return null;

        $model = $dispatchable->model_type::find($dispatchable->model_id);
        return $model?->team_id;
    }

    public function data(): array
    {
        return JobDispatchResource::make($this->jobDispatch, [
            'id' => true,
            'status' => true,
            // ... lightweight fields only
        ]);
    }
}
```

### Broadcasting Payloads - CRITICAL Rules

**ALWAYS use lightweight payloads in data() method:**

✅ **INCLUDE:**
- IDs (primary keys, foreign keys)
- Status/state fields
- Timestamps
- Simple strings (name, title, type)
- Simple counts

❌ **EXCLUDE:**
- Relationships (NEVER include nested objects)
- Large text fields (logs, content, raw_data)
- Binary/JSON blobs
- Computed attributes requiring queries

### Triggering Events

Events are automatically triggered via model observers, but can be manually dispatched:

```php
use App\Events\WorkflowRunUpdatedEvent;

// Manual dispatch with lock (prevents duplicates)
WorkflowRunUpdatedEvent::dispatch($workflowRun);

// Manual broadcast (without lock)
WorkflowRunUpdatedEvent::broadcast($workflowRun);
```

### Custom broadcastOn() Implementation

If you need custom subscription logic, override `broadcastOn()`:

```php
public function broadcastOn()
{
    $resourceType = $this->getResourceType(); // Auto-extracted from Resource class
    $teamId = $this->getTeamId();

    if (!$teamId) {
        return [];
    }

    // Custom subscription logic here
    $userIds = $this->getSubscribedUsers($resourceType, $teamId, $this->model, $this->model::class);

    return $this->getSubscribedChannels($resourceType, $teamId, $userIds);
}
```

### Event Examples

- **Simple:** `WorkflowRunUpdatedEvent`, `TaskRunUpdatedEvent` - team_id from direct relationship
- **Complex:** `JobDispatchUpdatedEvent`, `UsageSummaryUpdatedEvent` - team_id from polymorphic/nested relationships

---

## CRITICAL Requirements Checklist

1. **ALWAYS use team-based access control** in repositories and services
2. **ALWAYS extend danx base classes**: ActionController, ActionRepository, ActionResource
3. **ALWAYS use database transactions** for multi-step operations
4. **ALWAYS validate ownership** before performing operations
5. **ALWAYS use app() helper** for ALL dependency injection everywhere
6. **ALWAYS use namespace imports** at the top of files
7. **NEVER put business logic** in controllers or models
8. **NEVER use inline class references** with backslashes
9. **NEVER test controllers directly** - test services instead
10. **NEVER use static mocking** in tests

---

## Development Workflow

### Before Writing Code

1. Check existing patterns in similar files
2. Use existing services and repositories
3. Follow established conventions exactly

### When Writing Code

1. Start with the service layer (business logic)
2. Create repository methods (data access)
3. Add controller actions (thin delegation)
4. Create resources (API transformation)
5. Write comprehensive tests

### After Writing Code

1. **Verify all dependency injection uses app() helper** - no constructor injection
2. Run `./vendor/bin/sail artisan fix` for permissions
3. Run `./vendor/bin/sail test` to ensure all tests pass
4. Verify no console errors
5. Check for proper error handling

---

## Important Constraints

- Never use chmod - use `./vendor/bin/sail artisan fix`
- Always use `./vendor/bin/sail artisan make:migration` for migrations
- Use grep instead of rg for searching
- Run PHP with `./vendor/bin/sail php`
- Run tests with `./vendor/bin/sail test`

---

This guide ensures consistent, maintainable, and scalable Laravel backend code following the GPT Manager application patterns.