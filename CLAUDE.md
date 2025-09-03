# GPT Manager - LLM Code Writing Guide

## Core Principles

**ZERO TECH DEBT POLICY**

- NO legacy code - remove or refactor immediately
- **ABSOLUTE ZERO BACKWARDS COMPATIBILITY** - NEVER EVER maintain old patterns, even temporarily
- **IMMEDIATE REPLACEMENT ONLY** - Replace old code completely, no compatibility layers
- ONE way to do everything - the correct, modern way
- Remove dead code on sight
- Refactor any code that doesn't meet standards
- **ZERO TOLERANCE POLICY** - No exceptions, no matter how small or temporary
- Never use chmod on files to fix permissions!!! Always use `./vendor/bin/sail artisan fix`
- Never use the rg command, use grep instead
- When attempting to run PHP files, always use sail php
- **CRITICAL - ALWAYS READ EXISTING PATTERNS FIRST**:
    - MANDATORY: READ EXISTING SIMILAR IMPLEMENTATIONS BEFORE WRITING ANY CODE
    - ALWAYS READ THE COMPONENT / CLASS YOU ARE about to interact with BEFORE YOU use it
    - NEVER create custom implementations when established patterns exist
    - NEVER create custom API endpoints - use existing resource relationships and details() methods
    - NEVER create custom formatters - use quasar-ui-danx formats.ts patterns ONLY
    - NEVER create custom tables - use ActionTable/ActionTableLayout patterns ONLY
    - NEVER create custom controllers - use existing DanxController patterns ONLY
    - Understand the method completely (no abstraction) before assuming its behavior
    - Never assume you know how something works - ALWAYS check the actual implementation
    - Never guess parameter names, configuration options, or API signatures
    - ALWAYS look at examples first or the actual implementation and do it by example or by understanding how the code
      actually works
    - When unsure, search for existing usage patterns in the codebase
    - READ FULL FILES of similar implementations to understand complete patterns
    - It is CRITICAL you understand what you are doing before you do it

**CRITICALLY IMPORTANT: Before each code change, make a TODO list with the FIRST STEP stating:**
*"I will follow the best practices and standards: DRY Principles, no Legacy/backwards compatibility, and use the correct
patterns."*

## Specialized Agent Usage

**MANDATORY: For Vue.js/Frontend Work**

You MUST use the specialized Vue agents for any frontend work:

1. **vue-spa-architect** - REQUIRED for:
    - Planning new features affecting multiple components
    - Medium to large changes impacting multiple files
    - Component organization and architectural decisions

2. **vue-spa-engineer** - REQUIRED for:
    - Creating any new Vue components
    - Implementing Vue features or functionality
    - Refactoring existing Vue code
    - Any non-trivial changes (more than a few lines of code)

3. **vue-spa-reviewer** - REQUIRED for:
    - Reviewing Vue components after creation or modification
    - Ensuring adherence to project patterns and quality standards
    - Quality assurance of all Vue/Tailwind code

**CRITICAL: If you write ANY Vue/frontend code yourself instead of using specialized agents, you MUST:**

- First read the complete documentation files for the specialized agents (e.g., `@agent-vue-spa-engineer.md`)
- Follow ALL patterns and standards documented in those files
- Never guess at implementation patterns - always verify against existing code

**DO NOT** attempt to write Vue components or frontend code directly. Always delegate to the appropriate specialized
agent to ensure consistency, quality, and adherence to project standards.

**MANDATORY: For Laravel Backend Work**

You MUST use the specialized Laravel agents for any backend work:

1. **laravel-backend-architect** - REQUIRED for:
    - Planning complex backend features involving multiple classes/models/services
    - System architecture decisions affecting multiple components
    - Database schema design and migration planning
    - API endpoint organization and integration planning

2. **laravel-backend-engineer** - REQUIRED for:
    - Creating any new services, repositories, controllers, or models
    - Implementing backend features or functionality
    - Refactoring existing Laravel code
    - Any non-trivial backend changes (more than a Simple getter/setter)

3. **laravel-backend-qa-tester** - REQUIRED for:
    - Reviewing Laravel backend code after creation or modification
    - Ensuring comprehensive test coverage and quality standards
    - Quality assurance of all Laravel backend code

**DO NOT** attempt to write Laravel services, repositories, controllers, or models directly. Always delegate to the
appropriate specialized agent to ensure consistency, quality, and adherence to the established
Service-Repository-Controller pattern with danx integration.

## Laravel Backend Standards (High-Level)

### Core Architecture - Service-Repository-Controller Pattern

- **Services**: ALL business logic with validation and DB transactions
- **Repositories**: Data access ONLY, extend ActionRepository with team scoping
- **Controllers**: THIN delegation only, extend ActionController with app() helper
- **Models**: Relationships and validation ONLY, use danx traits

### Key Requirements

- **Team-based access control**: ALL data scoped to teams automatically
- **danx library integration**: ActionController, ActionRepository, ActionResource, ActionRoute
- **Database transactions**: Multi-step operations must use DB::transaction()
- **Anonymous class migrations**: Laravel 9+ style with team_id fields
- **Comprehensive testing**: AuthenticatedTestCase with team setup
- **Unit tests for non-trivial changes**: ALL new functionality must include comprehensive unit tests covering happy
  path, validation errors, edge cases, and boundary conditions

**For detailed implementation patterns, see `LARAVEL_BACKEND_PATTERNS_GUIDE.md` or use the Laravel specialized agents.**

## Vue.js Frontend Standards

- **Component Architecture**: Use Vue 3 Composition API with `<script setup lang="ts">`
- **State Management**: Use quasar-ui-danx storeObjects pattern (NO Vuex/Pinia)
- **Styling**: Tailwind CSS utility classes only, NO inline styles
- **Components**: Use quasar-ui-danx components (ActionTableLayout, TextField, etc.)
- **Icons**: Use danx-icon library imports
- **API**: ALWAYS use `request` from quasar-ui-danx (NEVER axios)
- **TypeScript**: Full TypeScript, no 'any' types, interface definitions for all data structures
- **Logic**: Composables for reusable logic, props validation with TypeScript

### Critical Data Management Patterns

**NEVER USE storeObject() OR storeObjects() DIRECTLY:**

- ❌ `const stored = storeObject(response)` - WRONG
- ❌ `items.value = storeObjects(response.data)` - WRONG
- ✅ `const result = await routes.list()` - Returns stored data
- ✅ `items.value = result.data` - Assign already-stored data
- ✅ `await routes.details(object, fields)` - Updates object in-place

**CORRECT details() Usage:**

- ❌ `routes.details({ id: 123 }, fields)` - WRONG
- ✅ `routes.details(objectInstance, fields)` - Pass actual object
- ✅ Loads relationships INTO the existing object automatically
- ✅ No manual state management needed - store handles everything

**Performance-Optimized Relationship Loading:**

```javascript
// ❌ WRONG - Loads heavy relationships every time
const loadItem = async (id) => {
    await routes.details(item, {
        user: true,
        files: true,
        usage_events: { user: true } // HEAVY!
    });
};

// ✅ CORRECT - Load relationships separately as needed
const loadItem = async (id) => {
    await routes.details(item, {
        user: true,
        files: true
    });
};

const loadItemUsage = async (item) => {
    await routes.details(item, {
        usage_events: { user: true }
    });
};
```

**Centralized State Management:**

- Objects are stored once per `id + __type` combination
- All references point to the same object instance
- Updates automatically reflect everywhere
- No manual array updates needed - store handles it all

## Coding Style Rules

### General

- NO comments unless absolutely necessary for complex logic
- Clear, self-documenting code
- Descriptive variable and function names
- Early returns to reduce nesting

### PHP/Laravel

- Use app() helper for service resolution in ActionControllers (not constructor DI)
- Database transactions for multi-step operations
- Custom exceptions with proper HTTP codes
- Team-based data scoping automatic
- Type declarations for all parameters and returns

### Testing Requirements

**CRITICAL TESTING PRINCIPLES:**

- **NEVER TEST CONTROLLERS DIRECTLY** - Due to Laravel config issues causing 503 errors, ALL controller testing is
  PROHIBITED. Controllers are thin delegation layers.
- **NEVER use Mockery::mock(...)** - ALWAYS use `$this->mock(...)`
- **NEVER mock database interactions** - USE THE DATABASE! This is what we want to test!
- **ONLY mock 3rd party API calls** - Everything else should use real implementations
- **Tests should verify the entire system works** - Not just isolated functions
- **Use factories and extend TestCase or AuthenticatedTestCase (read the full code for these base classes)** - All tests
  reset the database between runs
- **Database writes are GOOD in tests** - They verify the complete behavior
- **NEVER TEST LARAVEL FRAMEWORK FEATURES** - DO NOT test:
    - Model fillable attributes working correctly
    - Model cast attributes working correctly
    - Eloquent relationships working (belongs to, has many, etc.)
    - Laravel validation rules syntax
    - Laravel's built-in functionality
- **ONLY TEST YOUR BUSINESS LOGIC** - Tests should focus on:
    - Custom business rules and validation logic
    - Service layer methods and their outcomes
    - Repository methods with complex queries
    - Custom scopes and data transformations
    - Team-based access control logic
    - Custom exception handling

**MANDATORY for all non-trivial changes:**

- **Unit tests**: Test individual methods/classes with REAL database interactions
- **Integration tests**: Test complete workflows end-to-end
- **Edge case coverage**: Test boundary conditions, validation limits, error scenarios
- **Team-based access control tests**: Verify security constraints work properly
- **Service layer tests**: All public service methods must have comprehensive tests
- **Repository tests**: Test data access patterns and team scoping

**Test Structure Standards:**

- Use AuthenticatedTestCase with SetUpTeamTrait for feature tests
- Use TestCase for pure unit tests with real database interactions
- Follow Given-When-Then test structure with clear comments
- Test method names should describe the scenario: `test_methodName_withCondition_expectedResult`
- Each test should verify one specific behavior
- Only mock external 3rd party dependencies (APIs, external services)

### Naming Conventions

- **Files**: PascalCase for components, camelCase for TS/JS
- **Routes**: kebab-case
- **Database**: snake_case
- **Classes/Interfaces**: PascalCase
- **Functions/Variables**: camelCase

## Development Workflow

### Before Writing Code

1. Check existing patterns in neighboring files
2. Use existing libraries - DON'T assume availability
3. Follow established conventions exactly

### When Writing Code

1. Start with the simplest working solution
2. Extract to service/repository/component when needed
3. NO premature optimization
4. Delete old code immediately

### Testing & Validation

- **MANDATORY for non-trivial frontend changes**: Always run `yarn build` from the spa directory to ensure build passes
- **Linting**: DO NOT use command-line linting tools - linting is handled manually via the IDE
- Verify no console errors
- Check for proper error handling
- Ensure proper loading states

#### 🚨 **CRITICAL: NEVER USE STATIC MOCKING**

```php
// ❌ FORBIDDEN - Breaks test isolation and causes mysterious failures
$mock = Mockery::mock('alias:' . StaticService::class);
```

- **Breaks test isolation** - tests fail when run in batch but pass individually
- **Global state pollution** - affects other tests unpredictably
- **Use real services instead** - for integration testing
- **Use dependency injection** - for proper unit testing

### Troubleshooting Test Failures

If tests are failing unexpectedly (especially with dependency errors or missing methods from the danx library):

- Run `make danx-core` to update the danx library and re-establish local symlinks
- This ensures your local danx library is properly synced with the latest changes
- Common symptoms that indicate danx sync issues:
    - Method not found errors in danx components
    - Unexpected test failures after updating danx-related code
    - Import errors for danx modules

## Anti-Patterns to Avoid

### Backend

- Business logic in controllers or models (use Services)
- Direct DB queries in controllers (use Repositories)
- Missing team-based access control
- Not using danx patterns (ActionController, ActionRepository, etc.)

### Frontend

- Large monolithic components (extract to smaller components)
- Direct API calls in components (use storeObjects)
- Local state management instead of storeObjects
- Raw HTML inputs instead of quasar-ui-danx components

**When you encounter these anti-patterns, immediately delegate to the appropriate specialized agent for refactoring.**

## ActionButton and Model Action Patterns

### Core Action Principles - ALWAYS REUSE EXISTING ACTIONS

**NEVER CREATE CUSTOM ACTION IMPLEMENTATIONS - REUSE EXISTING DX CONTROLLERS**

- **FIRST**: Search for existing `dx*` controllers (e.g., `dxWorkflowRun`, `dxTaskRun`, `dxAgent`)
- **THEN**: Use `.getAction("actionName")` to get pre-built actions
- **NEVER**: Create custom API calls for standard CRUD/workflow operations

### ActionButton Usage Patterns

**Standard ActionButton with existing actions:**

```vue

<ActionButton
    v-if="model.isActive"
    type="stop"
    color="red"
    size="xs"
    tooltip="Stop Operation"
    :action="dxController.getAction('stop')"
    :target="model"
/>
```

**Key ActionButton Properties:**

- `type`: Icon type (stop, play, view, edit, delete, etc.)
- `color`: Color theme (red, sky, green, etc.)
- `size`: Button size (xs, sm, md, lg)
- `tooltip`: Hover text
- `:action`: Pre-built action from dx controller
- `:target`: The model object to act upon

### DRY Action Implementation

**✅ CORRECT - Reuse existing dx controllers:**

```vue

<script setup>
    import { dxWorkflowRun } from "@/path/to/WorkflowRuns/config";

    const stopAction = dxWorkflowRun.getAction("stop");
    const resumeAction = dxWorkflowRun.getAction("resume");
</script>

<template>
    <ActionButton
        v-if="status.isActive && status.workflowRun"
        :action="stopAction"
        :target="status.workflowRun"
        type="stop"
        color="red"
    />
</template>
```

**❌ WRONG - Creating custom actions:**

```vue
// DON'T DO THIS - duplicates existing functionality
const stopWorkflow = async () => {
await customRoutes.stopWorkflow(model);
};
```

### Generalized State Management

**Use generic object properties, not specific model names:**

```javascript
// ✅ GOOD - Works with any model
const statusObjects = computed(() => {
    return steps.map(step => ({
        name: step.name,
        workflowRun: step.workflowRun, // Generic reference
        isActive: step.workflowRun?.status && activeStates.includes(step.workflowRun.status),
        isStopped: step.workflowRun?.status === "Stopped"
    }));
});

// ❌ BAD - Specific to one model type  
const demandSpecificLogic = computed(() => {
    return demand.extract_data_workflow_run?.status === "Running";
});
```

### Action Discovery Process

**MANDATORY steps when implementing any model actions:**

1. **Search first**: `grep -r "dx.*Controller" spa/src` to find existing controllers
2. **Examine existing**: Look at similar components using the same model type
3. **Check actions**: Use `.getActions()` or `.getAction("name")` from dx controllers
4. **Verify backend**: Ensure the action exists in the backend ActionController
5. **Test generically**: Make sure actions work with any instance of the model

### Common Action Types Available

Most dx controllers provide these standard actions:

- `create` - Create new instance
- `update` - Update existing instance
- `delete` - Delete instance
- `stop` - Stop running process
- `resume` - Resume stopped process
- `restart` - Restart failed process

**Before creating ANY custom action, verify it doesn't already exist in the dx controller.**

### Universal Application of These Principles

**These action patterns apply to ALL model-based operations:**

- User management (dxUser, dxAgent, dxThread)
- Content management (dxContent, dxDocument, dxFile)
- Workflow management (dxWorkflowRun, dxTaskRun)
- Data management (dxTeamObject, dxSchema)
- Any other model with standard CRUD or process operations

**The pattern is ALWAYS the same:**

1. Find the existing dx controller for that model type
2. Import it: `import { dxModelName } from "@/path/to/config"`
3. Use pre-built actions: `const action = dxModelName.getAction("actionName")`
4. Apply to ActionButton: `:action="action" :target="modelInstance"`

**This prevents:**

- Duplicate API endpoints
- Inconsistent error handling
- Missing team-based access control
- Maintenance overhead from custom implementations
- Breaking changes when core actions are updated

## Migration Strategy

When encountering legacy code:

1. **IMMEDIATE REPLACEMENT** - Never work around legacy patterns
2. **COMPLETE REMOVAL** - Delete old code entirely, no compatibility layers
3. **ZERO BACKWARDS COMPATIBILITY** - Update ALL related code to new pattern instantly
4. **NO GRADUAL MIGRATION** - Replace everything in one atomic change
5. **COMPREHENSIVE TESTING** - Ensure complete replacement works correctly

## Custom Libraries (quasar-ui-danx & danx-icon)

### quasar-ui-danx Library

- Comprehensive UI component library that replaces Vuex/Pinia with `storeObjects` pattern
- Provides ActionTableLayout, form fields, action buttons, panels, and dialogs
- Use `storeObjects()` and `storeObject()` for reactive state management
- Each module uses DanxController pattern for CRUD operations

### danx-icon Library

- Use `FaSolid*` imports for most icons (EditIcon, DeleteIcon, etc.)
- All icons use Tailwind width classes (`class="w-3"`)

**Note**: Detailed component usage is available in the specialized Vue agents. Use those agents for specific
implementation guidance.

## Authentication & API Testing

### Generate API Tokens for CLI Testing

Use the `auth:token` command to generate authentication tokens for testing endpoints via CLI:

```bash
# Generate token for a user (uses first team)
./vendor/bin/sail artisan auth:token user@example.com

# Generate token for specific team
./vendor/bin/sail artisan auth:token user@example.com --team=team-uuid-here

# Generate token with custom name
./vendor/bin/sail artisan auth:token user@example.com --name=testing-token
```

**Usage in CLI requests:**

```bash
# Test API endpoints with generated token
curl -H "Authorization: Bearer your-token-here" \
     -H "Accept: application/json" \
     http://localhost/api/user

# Test with data
curl -X POST \
     -H "Authorization: Bearer your-token-here" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{"key":"value"}' \
     http://localhost/api/endpoint
```

**Authentication System Overview:**

- Uses Laravel Sanctum for API authentication
- Multi-tenant with team-based access control
- Token names contain team UUID for context resolution
- Users must have roles assigned to generate tokens
- Supports team switching via token regeneration

## Docker/Sail Commands

- Use `./vendor/bin/sail artisan` for all artisan commands
- Run `./vendor/bin/sail artisan fix` for permission issues
- Never modify git state without explicit instruction

---
**Remember: ONE RIGHT WAY - NO EXCEPTIONS!**

