# Laravel Backend Patterns Guide - GPT Manager

## Core Architecture Overview

This application follows a strict **Service-Repository-Controller** pattern with the **danx library** integration for standardized CRUD operations, team-based access control, and modern Laravel best practices.

### Key Principles

- **ZERO TECH DEBT**: No legacy code, no backwards compatibility
- **ONE WAY TO DO EVERYTHING**: The correct, modern way only
- **DRY PRINCIPLES**: Don't Repeat Yourself - extract reusable patterns
- **TEAM-BASED ACCESS CONTROL**: All data is scoped to teams automatically
- **danx LIBRARY INTEGRATION**: Standardized patterns for CRUD, resources, and routing

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
            // All business logic here
            $this->perform[Step1]($model, $data);
            $this->perform[Step2]($model, $data);
            
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

    protected function perform[Step1]([Model] $model, array $data): void
    {
        // Step implementation
    }
}
```

### Real Example: TeamObjectMergeService

```php
class TeamObjectMergeService
{
    public function merge(TeamObject $sourceObject, TeamObject $targetObject, ?array $schema = null): TeamObject
    {
        $this->validateMerge($sourceObject, $targetObject);

        return DB::transaction(function () use ($sourceObject, $targetObject, $schema) {
            $this->mergeAttributes($sourceObject, $targetObject);
            $this->mergeRelationships($sourceObject, $targetObject, $schema);
            $sourceObject->delete();
            return $targetObject->fresh(['attributes', 'relationships']);
        });
    }

    protected function validateMerge(TeamObject $sourceObject, TeamObject $targetObject): void
    {
        $this->validateOwnership($sourceObject);
        $this->validateOwnership($targetObject);

        if ($sourceObject->id === $targetObject->id) {
            throw new ValidationError('Cannot merge object with itself', 400);
        }

        if ($sourceObject->type !== $targetObject->type) {
            throw new ValidationError('Cannot merge objects of different types', 400);
        }
    }
}
```

### Service Patterns by Type

#### Data Processing Services
- `TaskProcessExecutorService`: Processes workflow tasks
- `ArtifactDeduplicationService`: Removes duplicate artifacts
- `ClassificationVerificationService`: Verifies data classifications

#### Integration Services
- `AgentThreadService`: Manages AI agent conversations
- `WorkflowRunnerService`: Executes workflow definitions
- `UsageTrackingService`: Tracks API usage and costs

#### Transformation Services
- `WorkflowExportService`/`WorkflowImportService`: Data serialization
- `JSONSchemaDataToDatabaseMapper`: Schema-based data mapping
- `DatabaseSchemaMapper`: Database structure management

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

### Real Example: TeamObjectRepository

```php
class TeamObjectRepository extends ActionRepository
{
    public static string $model = TeamObject::class;

    public function query(): Builder
    {
        $query = parent::query()->where('team_id', team()->id);
        if (!can('view_imported_schemas')) {
            $query->whereDoesntHave('schemaDefinition.resourcePackageImport', 
                fn(Builder $builder) => $builder->where('can_view', 0)
            );
        }
        return $query;
    }

    public function applyAction(string $action, TeamObject|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createTeamObject($data['type'], $data['name'], $data),
            'update' => (bool)$this->updateTeamObject($model, $data),
            'create-relation' => $this->createRelation($model, $data['relationship_name'], $data['type'], $data['name'], $data),
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
        $result = app([Service]::class)->performAction($model, $otherModel);
        return new [Model]Resource($result);
    }
}
```

### Real Example: TeamObjectsController

```php
class TeamObjectsController extends ActionController
{
    public static ?string $repo = TeamObjectRepository::class;
    public static ?string $resource = TeamObjectResource::class;

    public function merge(TeamObject $sourceObject, TeamObject $targetObject)
    {
        $mergedObject = app(TeamObjectMergeService::class)->merge($sourceObject, $targetObject);
        return new TeamObjectResource($mergedObject);
    }
}
```

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

    public function children(): HasMany
    {
        return $this->hasMany(ChildModel::class);
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

### Common Model Traits

- `AuditableTrait`: Automatic change tracking
- `ActionModelTrait`: Integration with ActionController/ActionRepository
- `SoftDeletes`: Soft deletion capability
- `HasUsageTracking`: Usage/cost tracking for API calls

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

    protected static function getDisplayName([Model] $model): string
    {
        return $model->name ?: "Unnamed {$model->type}";
    }

    protected static function loadChildren([Model] $model): array
    {
        return $model->children->map(fn($child) => [
            'id' => $child->id,
            'name' => $child->name,
        ])->toArray();
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
- **Composite indexes**: For query performance
- **Soft deletes**: For audit trails

---

## 7. Testing Patterns

### Test Structure Template

```php
<?php

namespace Tests\Feature\[Domain];

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
        $model = [Model]::factory()->create();
        $data = ['key' => 'value'];

        // When
        $result = app([Service]::class)->[action]($model, $data);

        // Then
        $this->assertInstanceOf([Model]::class, $result);
        $this->assertEquals('expected_value', $result->field);
    }

    public function test_[action]_with[InvalidCondition]_throwsException(): void
    {
        // Given
        $model = [Model]::factory()->create();
        $invalidData = ['invalid' => 'data'];

        // Then
        $this->expectException(ValidationError::class);

        // When
        app([Service]::class)->[action]($model, $invalidData);
    }
}
```

### Testing Best Practices

- **AuthenticatedTestCase**: Base class with team setup
- **Factory usage**: All test data via factories
- **Given-When-Then**: Clear test structure
- **Exception testing**: Verify error conditions
- **RefreshDatabase**: Fresh database per test

### Troubleshooting Test Failures

If tests are failing unexpectedly (especially with dependency errors or missing methods from the danx library):
- Run `make danx-core` to update the danx library and re-establish local symlinks
- This ensures your local danx library is properly synced with the latest changes
- Common symptoms that indicate danx sync issues:
  - Method not found errors in danx components/traits/classes
  - Unexpected test failures after updating danx-related code
  - Import errors for danx modules
  - Tests that were passing suddenly failing after pulling changes

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
        app([Service]::class)->[action]($this->model, $this->data);
    }
}
```

### Event/Listener Patterns

```php
// Event
class [Model][Action]Event
{
    public function __construct(public [Model] $model, public array $data = []) {}
}

// Listener
class [Model][Action]Listener
{
    public function handle([Model][Action]Event $event): void
    {
        // Handle the event
        app([Service]::class)->handle[Action]($event->model, $event->data);
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
    Route::get('{model}/related-data', [[Model]Controller::class, 'getRelatedData']);
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

## 10. Authentication & Authorization Patterns

### Team-Based Access Control

```php
// Automatic team scoping in repositories
public function query(): Builder
{
    return parent::query()->where('team_id', team()->id);
}

// Permission checking
if (!can('view_imported_schemas')) {
    // Restrict access
}

// Service validation
protected function validateOwnership([Model] $model): void
{
    $currentTeam = team();
    if (!$currentTeam || $model->team_id !== $currentTeam->id) {
        throw new ValidationError('You do not have permission to access this resource', 403);
    }
}
```

### API Token Generation

```bash
# Generate token for CLI testing
./vendor/bin/sail artisan auth:token user@example.com
./vendor/bin/sail artisan auth:token user@example.com --team=team-uuid
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

### Controller-Level Error Responses

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

### Caching Patterns

```php
// Service-level caching
public function getCachedData([Model] $model): array
{
    return Cache::remember("model-data-{$model->id}", 3600, function () use ($model) {
        return $this->generateExpensiveData($model);
    });
}
```

---

## File Organization Standards

```
app/
├── Console/Commands/           # Artisan commands
├── Events/                     # Domain events
├── Http/
│   ├── Controllers/            # Thin controllers
│   │   ├── [Domain]/          # Grouped by domain
│   ├── Middleware/            # Custom middleware
│   ├── Requests/              # Form request validation
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

## Common Anti-Patterns to Avoid

### ❌ Don't Do

```php
// Business logic in controllers
class BadController extends ActionController
{
    public function store(Request $request)
    {
        // ❌ Business logic in controller
        $data = $request->validated();
        if ($data['type'] === 'special') {
            // Complex business logic here
        }
        return Model::create($data);
    }
}

// Direct database queries in controllers
public function index()
{
    // ❌ Direct query in controller
    return Model::where('team_id', team()->id)->with('relations')->get();
}

// Mixed concerns in services
class BadService
{
    public function processData($data)
    {
        // ❌ Mixed validation, business logic, and data access
        if (!$data['valid']) throw new Exception('Invalid');
        $result = DB::table('models')->insert($data);
        return response()->json($result);
    }
}
```

### ✅ Do This Instead

```php
// Thin controller with proper delegation
class GoodController extends ActionController
{
    public static ?string $repo = ModelRepository::class;
    public static ?string $resource = ModelResource::class;

    public function customAction(Model $model)
    {
        $result = app(ModelService::class)->performAction($model);
        return new ModelResource($result);
    }
}

// Proper service with clear separation
class GoodService
{
    public function performAction(Model $model): Model
    {
        $this->validateAction($model);
        
        return DB::transaction(function () use ($model) {
            return $this->executeBusinessLogic($model);
        });
    }
}
```

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
1. Run `./vendor/bin/sail artisan fix` for permissions
2. Ensure all tests pass
3. Verify no console errors
4. Check for proper error handling

This guide ensures consistent, maintainable, and scalable Laravel backend code following the GPT Manager application patterns.