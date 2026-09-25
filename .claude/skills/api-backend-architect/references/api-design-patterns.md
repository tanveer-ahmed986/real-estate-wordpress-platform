# API Design Patterns

Comprehensive patterns for REST, GraphQL, gRPC, and WebSocket API design.

---

## REST API Design

### Resource Naming Conventions

```
# Plural nouns, kebab-case
GET    /api/v1/users              # List users
POST   /api/v1/users              # Create user
GET    /api/v1/users/{id}         # Get user
PUT    /api/v1/users/{id}         # Replace user
PATCH  /api/v1/users/{id}         # Partial update
DELETE /api/v1/users/{id}         # Delete user

# Nested resources (max 2 levels deep)
GET    /api/v1/users/{id}/orders
GET    /api/v1/users/{id}/orders/{orderId}

# Actions that don't map to CRUD
POST   /api/v1/users/{id}/activate
POST   /api/v1/orders/{id}/cancel

# Filtering, sorting, pagination
GET    /api/v1/users?status=active&sort=-created_at&page[cursor]=abc123&page[size]=20
```

### Must Follow
- Use plural nouns for resources (`/users` not `/user`)
- Use kebab-case for multi-word resources (`/order-items` not `/orderItems`)
- Max 2 levels of nesting; use query params or links beyond that
- Use nouns for resources, verbs only for actions
- Consistent trailing slash policy (prefer no trailing slash)

### Must Avoid
- Verbs in resource names (`/getUsers`, `/createUser`)
- Deep nesting beyond 2 levels
- Mixing naming conventions in the same API
- Exposing internal IDs or implementation details in URLs

### HTTP Methods & Status Codes

| Method | Purpose | Idempotent | Safe | Request Body |
|--------|---------|------------|------|-------------|
| GET | Read resource(s) | Yes | Yes | No |
| POST | Create resource / trigger action | No | No | Yes |
| PUT | Full replacement | Yes | No | Yes |
| PATCH | Partial update | No* | No | Yes |
| DELETE | Remove resource | Yes | No | Optional |

*PATCH can be made idempotent with JSON Merge Patch.

### Status Codes

```
# Success
200 OK              - Successful GET, PUT, PATCH, DELETE
201 Created         - Successful POST creating resource (include Location header)
202 Accepted        - Async operation accepted
204 No Content      - Successful DELETE with no response body

# Redirection
301 Moved Permanently - Resource URL changed permanently
304 Not Modified      - Conditional GET, resource unchanged (ETags)

# Client Errors
400 Bad Request     - Malformed request, validation failure
401 Unauthorized    - Missing or invalid authentication
403 Forbidden       - Authenticated but not authorized
404 Not Found       - Resource doesn't exist
405 Method Not Allowed - HTTP method not supported
409 Conflict        - State conflict (duplicate, version mismatch)
422 Unprocessable   - Valid syntax but semantic errors
429 Too Many Requests - Rate limit exceeded (include Retry-After header)

# Server Errors
500 Internal Server Error - Unexpected failure
502 Bad Gateway     - Upstream service failure
503 Service Unavailable - Maintenance or overloaded (include Retry-After)
504 Gateway Timeout - Upstream timeout
```

### Error Response Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Request validation failed",
    "details": [
      {
        "field": "email",
        "code": "INVALID_FORMAT",
        "message": "Must be a valid email address"
      },
      {
        "field": "age",
        "code": "OUT_OF_RANGE",
        "message": "Must be between 1 and 150"
      }
    ],
    "request_id": "req_abc123",
    "documentation_url": "https://api.example.com/docs/errors#VALIDATION_ERROR"
  }
}
```

### Pagination Patterns

#### Cursor-Based (Recommended)
```json
// Request
GET /api/v1/users?cursor=eyJpZCI6MTAwfQ&limit=20

// Response
{
  "data": [...],
  "pagination": {
    "next_cursor": "eyJpZCI6MTIwfQ",
    "has_more": true,
    "limit": 20
  }
}
```

**Advantages**: Consistent with real-time data, no skipped/duplicated items, performant.
**Use when**: Default choice for most APIs, large datasets, real-time data.

#### Offset-Based
```json
// Request
GET /api/v1/users?offset=40&limit=20

// Response
{
  "data": [...],
  "pagination": {
    "total": 1543,
    "offset": 40,
    "limit": 20
  }
}
```

**Use when**: Small datasets, need total count, UI requires page numbers.
**Avoid when**: Large datasets (performance degrades), real-time data (inconsistent).

### Filtering & Sorting

```
# Filtering
GET /api/v1/orders?status=pending&created_after=2024-01-01T00:00:00Z

# Multiple values (OR)
GET /api/v1/products?category=electronics,books

# Sorting (- prefix for descending)
GET /api/v1/users?sort=-created_at,name

# Field selection (sparse fieldsets)
GET /api/v1/users?fields=id,name,email

# Search
GET /api/v1/products?q=wireless+headphones
```

### API Versioning Strategies

| Strategy | Example | Pros | Cons |
|----------|---------|------|------|
| URL Path (Recommended) | `/api/v1/users` | Clear, cacheable, easy routing | URL pollution |
| Header | `Accept: application/vnd.api+json;v=1` | Clean URLs | Hidden, harder to test |
| Query Parameter | `/api/users?version=1` | Easy to use | Can break caching |

**Recommendation**: URL path versioning (`/v1/`, `/v2/`) for public APIs. Header versioning for internal APIs.

### Idempotency

```
# Client sends idempotency key with mutating requests
POST /api/v1/payments
Idempotency-Key: key_abc123xyz

# Server behavior:
# 1. Check if key exists in idempotency store
# 2. If exists, return cached response (same status code + body)
# 3. If not, process request, store response with key, return response
# 4. Keys expire after 24-48 hours
```

### Content Negotiation

```
# Request
Accept: application/json
Content-Type: application/json; charset=utf-8

# Versioned media type
Accept: application/vnd.myapi.v2+json

# Response
Content-Type: application/json; charset=utf-8
```

### HATEOAS (Hypermedia)

```json
{
  "id": "order_123",
  "status": "pending",
  "total": 99.99,
  "_links": {
    "self": { "href": "/api/v1/orders/order_123" },
    "cancel": { "href": "/api/v1/orders/order_123/cancel", "method": "POST" },
    "payment": { "href": "/api/v1/orders/order_123/payment", "method": "POST" },
    "items": { "href": "/api/v1/orders/order_123/items" }
  }
}
```

**Use when**: Public APIs where clients should discover available actions.
**Skip when**: Internal APIs with known contracts.

---

## GraphQL API Design

### Schema Design Best Practices

```graphql
# Use clear, descriptive types
type User {
  id: ID!
  email: String!
  displayName: String!
  createdAt: DateTime!
  orders(first: Int, after: String): OrderConnection!
}

# Relay-style connections for pagination
type OrderConnection {
  edges: [OrderEdge!]!
  pageInfo: PageInfo!
  totalCount: Int!
}

type OrderEdge {
  node: Order!
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
}

# Input types for mutations
input CreateUserInput {
  email: String!
  displayName: String!
  role: UserRole = MEMBER
}

# Mutation responses with user errors
type CreateUserPayload {
  user: User
  userErrors: [UserError!]!
}

type UserError {
  field: [String!]
  message: String!
  code: UserErrorCode!
}

# Enums for fixed sets
enum UserRole {
  ADMIN
  MEMBER
  VIEWER
}
```

### N+1 Query Problem & DataLoader

```typescript
// WITHOUT DataLoader (N+1 problem)
// 1 query for users + N queries for each user's orders = N+1 queries

// WITH DataLoader (batched)
import DataLoader from 'dataloader';

const orderLoader = new DataLoader(async (userIds: string[]) => {
  // Single query: SELECT * FROM orders WHERE user_id IN (...)
  const orders = await db.orders.findMany({
    where: { userId: { in: userIds } }
  });
  // Map results back to input order
  return userIds.map(id => orders.filter(o => o.userId === id));
});

// In resolver
const resolvers = {
  User: {
    orders: (user) => orderLoader.load(user.id),
  },
};
```

### Security for GraphQL

```typescript
// 1. Query depth limiting
import depthLimit from 'graphql-depth-limit';
app.use('/graphql', graphqlHTTP({
  validationRules: [depthLimit(7)]
}));

// 2. Query complexity analysis
import { createComplexityLimitRule } from 'graphql-validation-complexity';
const complexityRule = createComplexityLimitRule(1000, {
  scalarCost: 1,
  objectCost: 2,
  listFactor: 10,
});

// 3. Rate limiting by complexity, not just requests
// 4. Disable introspection in production
// 5. Persisted queries for production (whitelist allowed queries)
```

### Error Handling in GraphQL

```json
{
  "data": {
    "createUser": {
      "user": null,
      "userErrors": [
        {
          "field": ["email"],
          "message": "Email already exists",
          "code": "DUPLICATE_EMAIL"
        }
      ]
    }
  }
}
```

**Pattern**: Return user errors in the payload, not in the top-level `errors` array. Reserve top-level `errors` for unexpected/system errors.

---

## gRPC API Design

### Proto File Best Practices

```protobuf
syntax = "proto3";

package myapp.user.v1;

option go_package = "myapp/user/v1;userv1";

import "google/protobuf/timestamp.proto";
import "google/protobuf/field_mask.proto";

// Service definition
service UserService {
  // Unary RPCs
  rpc GetUser(GetUserRequest) returns (GetUserResponse);
  rpc CreateUser(CreateUserRequest) returns (CreateUserResponse);
  rpc UpdateUser(UpdateUserRequest) returns (UpdateUserResponse);
  rpc DeleteUser(DeleteUserRequest) returns (DeleteUserResponse);

  // Server streaming
  rpc ListUsers(ListUsersRequest) returns (stream ListUsersResponse);

  // Bidirectional streaming
  rpc Chat(stream ChatMessage) returns (stream ChatMessage);
}

// Request/Response naming: {MethodName}Request/Response
message GetUserRequest {
  string user_id = 1;
}

message GetUserResponse {
  User user = 1;
}

// Use field masks for partial updates
message UpdateUserRequest {
  User user = 1;
  google.protobuf.FieldMask update_mask = 2;
}

message User {
  string id = 1;
  string email = 2;
  string display_name = 3;
  UserRole role = 4;
  google.protobuf.Timestamp created_at = 5;
}

enum UserRole {
  USER_ROLE_UNSPECIFIED = 0;
  USER_ROLE_ADMIN = 1;
  USER_ROLE_MEMBER = 2;
}

// Pagination
message ListUsersRequest {
  int32 page_size = 1;
  string page_token = 2;
}
```

### Streaming Patterns

| Pattern | Use Case | Example |
|---------|----------|---------|
| Unary | Simple request/response | CRUD operations |
| Server streaming | Server pushes multiple responses | Real-time feeds, large dataset streaming |
| Client streaming | Client sends multiple messages | File upload, batch operations |
| Bidirectional | Both sides stream | Chat, collaborative editing |

### gRPC Error Handling

```
# Standard gRPC status codes
OK (0)                  - Success
CANCELLED (1)           - Client cancelled
INVALID_ARGUMENT (3)    - Bad request (like HTTP 400)
NOT_FOUND (5)           - Resource not found (like HTTP 404)
ALREADY_EXISTS (6)      - Duplicate (like HTTP 409)
PERMISSION_DENIED (7)   - Forbidden (like HTTP 403)
UNAUTHENTICATED (16)    - Not authenticated (like HTTP 401)
RESOURCE_EXHAUSTED (8)  - Rate limited (like HTTP 429)
INTERNAL (13)           - Server error (like HTTP 500)
UNAVAILABLE (14)        - Service unavailable (like HTTP 503)
DEADLINE_EXCEEDED (4)   - Timeout (like HTTP 504)
```

### When to Choose gRPC

| Choose gRPC When | Choose REST When |
|------------------|-----------------|
| Service-to-service communication | Public-facing APIs |
| High-performance, low-latency needed | Browser clients (without proxy) |
| Strong typing important | Simple CRUD operations |
| Streaming required | Broad client compatibility |
| Polyglot microservices | Rapid prototyping |

---

## WebSocket API Design

### Connection Lifecycle

```
Client                          Server
  |                                |
  |--- HTTP Upgrade Request ------>|
  |<-- 101 Switching Protocols ----|
  |                                |
  |<====== WebSocket Open ========>|
  |                                |
  |--- Authentication Message ---->|
  |<-- Auth Acknowledgment --------|
  |                                |
  |--- Subscribe to channels ----->|
  |<-- Subscription confirmation --|
  |                                |
  |<-- Server push messages -------|
  |--- Client messages ----------->|
  |                                |
  |--- Ping ---------------------->|
  |<-- Pong -----------------------|
  |                                |
  |--- Close Frame --------------->|
  |<-- Close Frame ----------------|
```

### Message Format

```json
// Envelope pattern
{
  "type": "event",
  "event": "order.updated",
  "data": {
    "order_id": "order_123",
    "status": "shipped"
  },
  "id": "msg_abc123",
  "timestamp": "2024-01-15T10:30:00Z"
}

// Request/Response pattern
{
  "type": "request",
  "id": "req_001",
  "action": "subscribe",
  "channel": "orders.user_123"
}

{
  "type": "response",
  "id": "req_001",
  "status": "ok",
  "data": { "subscribed": true }
}

// Error
{
  "type": "error",
  "id": "req_002",
  "code": "UNAUTHORIZED",
  "message": "Invalid authentication token"
}
```

### Connection Management

```typescript
// Heartbeat (server-side)
const HEARTBEAT_INTERVAL = 30000; // 30 seconds
const HEARTBEAT_TIMEOUT = 10000;  // 10 seconds to respond

ws.on('open', () => {
  const heartbeat = setInterval(() => {
    if (!ws.isAlive) { ws.terminate(); return; }
    ws.isAlive = false;
    ws.ping();
  }, HEARTBEAT_INTERVAL);
  ws.on('pong', () => { ws.isAlive = true; });
  ws.on('close', () => clearInterval(heartbeat));
});

// Reconnection (client-side) with exponential backoff
class ReconnectingWebSocket {
  private retryCount = 0;
  private maxRetries = 10;
  private baseDelay = 1000;

  connect() {
    this.ws = new WebSocket(this.url);
    this.ws.onclose = () => {
      if (this.retryCount < this.maxRetries) {
        const delay = this.baseDelay * Math.pow(2, this.retryCount)
          + Math.random() * 1000; // jitter
        setTimeout(() => this.connect(), delay);
        this.retryCount++;
      }
    };
    this.ws.onopen = () => { this.retryCount = 0; };
  }
}
```

### Scaling WebSockets

| Challenge | Solution |
|-----------|----------|
| Sticky sessions needed | Use Redis pub/sub as message broker between instances |
| Memory per connection | Limit connections per instance, horizontal scaling |
| Authentication | Authenticate on HTTP upgrade, validate JWT |
| Load balancing | Layer 7 LB with WebSocket support (nginx, HAProxy) |
| State management | Externalize to Redis/shared store |

---

## API Design Anti-Patterns

| Anti-Pattern | Problem | Solution |
|-------------|---------|----------|
| Chatty API | Too many calls for one operation | Aggregate endpoints, batch APIs |
| God endpoint | One endpoint does everything | Separate into resource-specific endpoints |
| Leaky abstraction | Internal DB schema exposed | Design API contracts independent of storage |
| Breaking changes | Clients break on updates | Version API, deprecation policy |
| No pagination | Returns unbounded lists | Always paginate list endpoints |
| Ignoring caching | Unnecessary server load | ETags, Cache-Control headers |
| Inconsistent errors | Different error formats | Standardized error envelope |
| Missing idempotency | Duplicate operations | Idempotency keys for POST/PUT |
