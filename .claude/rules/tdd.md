# TDD & Testing Requirements

## 100% Test Coverage Required

All features and bug fixes MUST have comprehensive tests. No exceptions.

## When to Use TDD (Test-First)

**Bug fixes**: ALWAYS use TDD - write failing test first

## When to Write Tests After

- **New features**: Implement, then write comprehensive tests
- **Refactoring**: Ensure existing tests pass, add new tests if gaps exist

## Bug Fix TDD Process

1. **Understand** - Read the bug report, reproduce mentally
2. **Write failing test** - Create test that fails due to the bug
3. **Run test** - Verify it fails for the right reason
4. **Fix the bug** - Minimal change to make test pass
5. **Verify** - Run the test, confirm it passes
6. **Check for regressions** - Run related tests

## Feature Testing Requirements

After implementing a feature:
1. Write unit tests for all new services/models
2. Write feature tests for API endpoints
3. Test happy paths AND error cases
4. Verify tests pass before considering feature complete

## Test Commands

```bash
./vendor/bin/sail test --filter=TestName    # Run specific test
./vendor/bin/sail test tests/Unit/Path/     # Run directory
./vendor/bin/sail test                       # Full suite (before PR)
```

## Test Quality Standards

**Philosophy: Quality Over Quantity**
- Focus on behavior and outcomes, not implementation details
- Every test must verify functionality that could actually break
- Use descriptive test names that explain the scenario

**GOOD TESTS (Write These):**
- Critical business logic with complex conditions
- State transitions and workflow correctness
- Security & authorization (team scoping, permissions)
- Edge cases that could cause data corruption
- Error handling and exception scenarios

**BAD TESTS (Never Write These):**
- Resource field enumeration (testing that fields exist)
- Getters/setters (testing basic property access)
- Framework behavior (relationships, casts, timestamps)
- Implementation details (private methods, internal structure)
- Mocking database instead of using real database

**NEVER TEST CONTROLLERS DIRECTLY** - Use feature tests through HTTP instead

See `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md` for detailed testing patterns.
