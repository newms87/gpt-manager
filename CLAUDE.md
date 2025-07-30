# GPT Manager - LLM Code Writing Guide

## Core Principles

**ZERO TECH DEBT POLICY**

- NO legacy code - remove or refactor immediately
- NO backwards compatibility - always update to the right way
- ONE way to do everything - the correct, modern way
- Remove dead code on sight
- Refactor any code that doesn't meet standards
- Never use chmod on files to fix permissions!!! Always use `./vendor/bin/sail artisan fix`
- Never use the rg command, use grep instead
- When attempting to run PHP files, always use sail php
- To avoid making mistakes, ALWAYS ALWAYS ALWAYS READ THE COMPONENT / CLASS YOU ARE about to interact with BEFORE YOU
  use it. Understand the method completely (no abstraction) before assuming its behavior. Never assume you know how
  something works. Never guess. It is CRITICAL you understand what you are doing before you do it.

**CRITICALLY IMPORTANT: Before each code change, make a TODO list with the FIRST STEP stating:**
*"I will follow the best practices and standards: DRY Principles, no Legacy/backwards compatibility, and use the correct patterns."*

## Specialized Agent Usage

**MANDATORY: For Vue.js/Frontend Work**

You MUST use the specialized Vue agents for any frontend work:

1. **vue-architect-planner** - REQUIRED for:
   - Planning new features affecting multiple components
   - Medium to large changes impacting multiple files
   - Component organization and architectural decisions

2. **vue-tailwind-engineer** - REQUIRED for:
   - Creating any new Vue components
   - Implementing Vue features or functionality
   - Refactoring existing Vue code
   - Any non-trivial changes (more than a few lines of code)

3. **vue-tailwind-reviewer** - REQUIRED for:
   - Reviewing Vue components after creation or modification
   - Ensuring adherence to project patterns and quality standards
   - Quality assurance of all Vue/Tailwind code

**DO NOT** attempt to write Vue components or frontend code directly. Always delegate to the appropriate specialized agent to ensure consistency, quality, and adherence to project standards.

**MANDATORY: For Laravel Backend Work**

You MUST use the specialized Laravel agents for any backend work:

1. **laravel-system-architect** - REQUIRED for:
   - Planning complex backend features involving multiple classes/models/services
   - System architecture decisions affecting multiple components
   - Database schema design and migration planning
   - API endpoint organization and integration planning

2. **laravel-backend-architect** - REQUIRED for:
   - Creating any new services, repositories, controllers, or models
   - Implementing backend features or functionality
   - Refactoring existing Laravel code
   - Any non-trivial backend changes (more than a Simple getter/setter)

3. **laravel-qa-tester** - REQUIRED for:
   - Reviewing Laravel backend code after creation or modification
   - Ensuring comprehensive test coverage and quality standards
   - Quality assurance of all Laravel backend code

**DO NOT** attempt to write Laravel services, repositories, controllers, or models directly. Always delegate to the appropriate specialized agent to ensure consistency, quality, and adherence to the established Service-Repository-Controller pattern with danx integration.

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

- Run lint/typecheck commands if provided
- Verify no console errors
- Check for proper error handling
- Ensure proper loading states

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

## Migration Strategy

When encountering legacy code:

1. Identify the correct pattern
2. Refactor immediately - don't work around it
3. Update all related code to match
4. Remove dead code paths
5. Test the refactored solution

## Custom Libraries (quasar-ui-danx & danx-icon)

### quasar-ui-danx Library
- Comprehensive UI component library that replaces Vuex/Pinia with `storeObjects` pattern
- Provides ActionTableLayout, form fields, action buttons, panels, and dialogs
- Use `storeObjects()` and `storeObject()` for reactive state management
- Each module uses DanxController pattern for CRUD operations

### danx-icon Library
- Use `FaSolid*` imports for most icons (EditIcon, DeleteIcon, etc.)
- All icons use Tailwind width classes (`class="w-3"`)

**Note**: Detailed component usage is available in the specialized Vue agents. Use those agents for specific implementation guidance.

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

