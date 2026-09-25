# Testing Strategies

## Testing Pyramid

```
        /    E2E     \        5-10% of tests
       /  Integration  \      20-30% of tests
      /   Unit Tests     \    60-70% of tests
```

### Unit Tests (60-70%)
- Test individual functions, classes, modules in isolation
- Fast (<10ms per test), no external dependencies
- Mock external dependencies (DB, APIs, file system)
- Focus on business logic, edge cases, error handling

### Integration Tests (20-30%)
- Test component interactions (API + DB, service + service)
- Use real dependencies when practical (TestContainers)
- Test API contracts, database queries, message handling
- Slower but catch interface mismatches

### E2E Tests (5-10%)
- Test critical user journeys through full stack
- Run in environment matching production
- Cover: signup, login, core workflow, payment, logout
- Expensive to maintain; keep minimal

---

## Test Structure: Arrange-Act-Assert

```typescript
describe('OrderService', () => {
  describe('calculateTotal', () => {
    it('should apply percentage discount to subtotal', () => {
      // Arrange
      const items = [
        { name: 'Widget', price: 100, quantity: 2 },
        { name: 'Gadget', price: 50, quantity: 1 },
      ];
      const discount = { type: 'percentage', value: 10 };

      // Act
      const total = calculateTotal(items, discount);

      // Assert
      expect(total).toBe(225); // (200 + 50) * 0.9
    });

    it('should throw on negative quantity', () => {
      const items = [{ name: 'Widget', price: 100, quantity: -1 }];
      expect(() => calculateTotal(items)).toThrow('Invalid quantity');
    });
  });
});
```

---

## What to Test

### Must Test
- Business logic and calculations
- Data transformations and mappings
- Input validation and error handling
- Edge cases: empty arrays, null/undefined, boundary values
- Authentication and authorization logic
- API request/response contracts

### Don't Over-Test
- Framework internals (React rendering, Express routing)
- Simple getters/setters with no logic
- Third-party library functionality
- Implementation details (test behavior, not structure)

---

## Testing Patterns

### Mocking Strategy

| What | How | When |
|------|-----|------|
| External APIs | Mock HTTP responses | Always in unit tests |
| Database | In-memory DB or TestContainers | Integration tests |
| Time/Date | Mock Date.now(), freeze time | Time-dependent logic |
| File System | Virtual file system (memfs) | File operations |
| Randomness | Seed random generators | Random-dependent logic |

```typescript
// GOOD: Mock at the boundary
const mockRepository = {
  findById: jest.fn().mockResolvedValue({ id: '1', name: 'Test' }),
};
const service = new UserService(mockRepository);

// BAD: Mock implementation details
jest.spyOn(service, 'privateMethod'); // Don't test internals
```

### Test Fixtures & Factories

```typescript
// Use factory functions for test data
function createUser(overrides: Partial<User> = {}): User {
  return {
    id: 'user-1',
    name: 'Test User',
    email: 'test@example.com',
    role: 'user',
    createdAt: new Date('2025-01-01'),
    ...overrides,
  };
}

// Usage
const admin = createUser({ role: 'admin' });
const newUser = createUser({ id: 'user-2', name: 'Jane' });
```

---

## API Testing

### REST API Tests

```typescript
describe('POST /api/v1/users', () => {
  it('should create user and return 201', async () => {
    const response = await request(app)
      .post('/api/v1/users')
      .send({ name: 'John', email: 'john@example.com' })
      .expect(201);

    expect(response.body).toMatchObject({
      id: expect.any(String),
      name: 'John',
      email: 'john@example.com',
    });
  });

  it('should return 422 for invalid email', async () => {
    const response = await request(app)
      .post('/api/v1/users')
      .send({ name: 'John', email: 'not-an-email' })
      .expect(422);

    expect(response.body.errors[0].field).toBe('email');
  });

  it('should return 401 without auth token', async () => {
    await request(app)
      .post('/api/v1/users')
      .send({ name: 'John', email: 'john@example.com' })
      .expect(401);
  });
});
```

---

## TDD Workflow

```
1. Write a failing test (RED)
2. Write minimal code to pass (GREEN)
3. Refactor while keeping tests green (REFACTOR)
4. Repeat
```

### When TDD Works Best
- Complex business logic with clear rules
- Bug fixes (write test that reproduces bug first)
- API design (tests define the contract)
- Algorithm implementation

### When TDD Is Less Useful
- Exploratory prototyping
- UI layout and styling
- Integration with unknown third-party APIs

---

## Testing Tools by Stack

| Stack | Unit | Integration | E2E |
|-------|------|-------------|-----|
| Node.js/TS | Jest, Vitest | Supertest, TestContainers | Playwright, Cypress |
| Python | pytest | pytest + httpx, TestContainers | Playwright, Selenium |
| Go | testing + testify | TestContainers | Playwright |
| React | React Testing Library | MSW (Mock Service Worker) | Playwright |
| Vue | Vue Test Utils, Vitest | MSW | Cypress, Playwright |

---

## CI Testing Pipeline

```yaml
# Run fast tests first, fail fast
stages:
  - lint          # ESLint, Prettier (seconds)
  - typecheck     # tsc --noEmit (seconds)
  - unit          # Unit tests with coverage (seconds-minutes)
  - integration   # API and DB tests (minutes)
  - e2e           # Critical path tests (minutes)
  - coverage      # Enforce coverage thresholds
```

### Coverage Thresholds

| Type | Threshold | Reasoning |
|------|-----------|-----------|
| Business Logic | >80% | Critical code must be well-tested |
| Utilities | >90% | Pure functions are easy to test |
| Controllers/Routes | >60% | Integration tests cover these |
| Overall | >70% | Balance quality and velocity |

**Note**: Coverage measures code execution, not correctness. 100% coverage doesn't mean bug-free. Focus on meaningful assertions.

---

## Sources
- Martin Fowler: Test Pyramid
- Kent Beck: Test-Driven Development by Example
- Google Testing Blog: https://testing.googleblog.com/
