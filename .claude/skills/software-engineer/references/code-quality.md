# Code Quality

## SOLID Principles

### S - Single Responsibility Principle
A class/module should have ONE reason to change.

```typescript
// BAD: UserService does too many things
class UserService {
  createUser() { /* ... */ }
  sendEmail() { /* ... */ }
  generateReport() { /* ... */ }
}

// GOOD: Separate responsibilities
class UserService { createUser() { /* ... */ } }
class EmailService { sendEmail() { /* ... */ } }
class ReportService { generateReport() { /* ... */ } }
```

### O - Open/Closed Principle
Open for extension, closed for modification.

```typescript
// BAD: Modify function for each new payment type
function processPayment(type: string, amount: number) {
  if (type === 'credit') { /* ... */ }
  else if (type === 'paypal') { /* ... */ }
  // Must modify to add new type
}

// GOOD: Extend via interface
interface PaymentProcessor {
  process(amount: number): Promise<PaymentResult>;
}
class CreditCardProcessor implements PaymentProcessor { /* ... */ }
class PayPalProcessor implements PaymentProcessor { /* ... */ }
class CryptoProcessor implements PaymentProcessor { /* ... */ }  // New, no modification
```

### L - Liskov Substitution Principle
Subtypes must be substitutable for their base types.

```typescript
// BAD: Square breaks Rectangle contract
class Rectangle {
  setWidth(w: number) { this.width = w; }
  setHeight(h: number) { this.height = h; }
}
class Square extends Rectangle {
  setWidth(w: number) { this.width = w; this.height = w; } // Breaks expectation
}

// GOOD: Use composition or separate types
interface Shape {
  area(): number;
}
class Rectangle implements Shape { /* width * height */ }
class Square implements Shape { /* side * side */ }
```

### I - Interface Segregation Principle
Clients shouldn't depend on interfaces they don't use.

```typescript
// BAD: Fat interface
interface Worker {
  work(): void;
  eat(): void;
  sleep(): void;
}

// GOOD: Segregated interfaces
interface Workable { work(): void; }
interface Feedable { eat(): void; }
class HumanWorker implements Workable, Feedable { /* ... */ }
class RobotWorker implements Workable { /* ... */ }
```

### D - Dependency Inversion Principle
Depend on abstractions, not concretions.

```typescript
// BAD: Direct dependency on implementation
class OrderService {
  private db = new PostgresDatabase(); // Coupled to Postgres
}

// GOOD: Depend on abstraction
interface Database {
  query<T>(sql: string, params: unknown[]): Promise<T[]>;
}
class OrderService {
  constructor(private db: Database) {} // Accepts any Database impl
}
```

---

## Clean Code Principles

### Naming

```typescript
// BAD: Unclear, abbreviated, misleading
const d = 86400;           // What is d?
const usrLst = getUsrs();  // Abbreviated
const isNotEmpty = arr.length === 0;  // Misleading

// GOOD: Clear, descriptive, honest
const SECONDS_PER_DAY = 86400;
const users = getActiveUsers();
const isEmpty = arr.length === 0;
```

### Functions

```typescript
// BAD: Too many params, too long, multiple responsibilities
function processOrder(userId, items, coupon, address, paymentMethod,
  sendEmail, updateInventory, notifyWarehouse) { /* 200 lines */ }

// GOOD: Small, focused, structured params
interface ProcessOrderInput {
  userId: string;
  items: OrderItem[];
  couponCode?: string;
  shippingAddress: Address;
  paymentMethod: PaymentMethod;
}

async function processOrder(input: ProcessOrderInput): Promise<Order> {
  const order = await createOrder(input);
  await processPayment(order, input.paymentMethod);
  await updateInventory(order.items);
  await notifyFulfillment(order);
  return order;
}
```

### Guard Clauses (Early Returns)

```typescript
// BAD: Deeply nested conditionals
function getDiscount(user: User): number {
  if (user) {
    if (user.isPremium) {
      if (user.yearsActive > 5) {
        return 0.3;
      } else {
        return 0.15;
      }
    } else {
      return 0;
    }
  }
  return 0;
}

// GOOD: Guard clauses, flat structure
function getDiscount(user: User | null): number {
  if (!user) return 0;
  if (!user.isPremium) return 0;
  if (user.yearsActive > 5) return 0.3;
  return 0.15;
}
```

### Error Handling

```typescript
// BAD: Silent catch, generic errors
try {
  await processPayment(order);
} catch (e) {
  console.log(e); // Silent, no recovery
}

// GOOD: Typed errors, proper handling
class PaymentError extends Error {
  constructor(
    message: string,
    public readonly code: string,
    public readonly retryable: boolean,
  ) {
    super(message);
    this.name = 'PaymentError';
  }
}

try {
  await processPayment(order);
} catch (error) {
  if (error instanceof PaymentError && error.retryable) {
    await retryPayment(order);
  } else {
    logger.error('Payment failed', { orderId: order.id, error });
    throw error; // Re-throw if can't handle
  }
}
```

---

## Code Review Checklist

### Correctness
- [ ] Does it do what it's supposed to do?
- [ ] Are edge cases handled (null, empty, boundary values)?
- [ ] Are race conditions possible?
- [ ] Is error handling appropriate?

### Security
- [ ] Input validated at boundaries?
- [ ] No SQL injection, XSS, or injection vulnerabilities?
- [ ] No secrets or credentials in code?
- [ ] Authorization checks in place?

### Performance
- [ ] No N+1 queries?
- [ ] No unnecessary re-renders (React)?
- [ ] Appropriate data structures used?
- [ ] No memory leaks (event listeners, intervals, subscriptions)?

### Maintainability
- [ ] Clear naming (variables, functions, classes)?
- [ ] Functions small and focused?
- [ ] No code duplication?
- [ ] No magic numbers (use named constants)?
- [ ] Consistent with existing codebase patterns?

### Testing
- [ ] Tests cover the changes?
- [ ] Tests are clear and maintainable?
- [ ] Edge cases tested?
- [ ] No flaky tests?

---

## Anti-Patterns to Avoid

| Anti-Pattern | Problem | Fix |
|-------------|---------|-----|
| God Object | Class that does everything | Break into focused classes |
| Spaghetti Code | Tangled, hard-to-follow flow | Extract functions, use patterns |
| Premature Optimization | Optimizing before measuring | Profile first, then optimize |
| Magic Numbers | Unexplained numeric literals | Named constants |
| Deep Nesting | >3 levels of indentation | Guard clauses, extract methods |
| Copy-Paste | Duplicated code blocks | Extract shared function |
| Shotgun Surgery | One change requires many files | Better encapsulation |
| Feature Envy | Method uses another class more than its own | Move method to correct class |
| Boolean Blindness | `doThing(true, false, true)` | Use options object or enum |

---

## Refactoring Patterns

| Pattern | When to Apply |
|---------|--------------|
| Extract Function | Code block does one distinct thing |
| Inline Function | Function body is as clear as its name |
| Extract Variable | Complex expression needs explanation |
| Rename | Name doesn't clearly convey purpose |
| Move Function | Function belongs in another module |
| Replace Conditional with Polymorphism | Switch/if-else on type |
| Introduce Parameter Object | Function has >3 related parameters |
| Replace Magic Number with Constant | Unexplained literal value |

---

## Sources
- Robert C. Martin: Clean Code
- Martin Fowler: Refactoring (2nd Edition)
- Google Engineering Practices: Code Review Guide
- SOLID Principles (Robert C. Martin)
