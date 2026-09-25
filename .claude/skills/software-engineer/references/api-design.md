# API Design Best Practices

## REST API Design

### URL Structure

```
GET    /api/v1/users              # List users (collection)
GET    /api/v1/users/{id}         # Get single user (resource)
POST   /api/v1/users              # Create user
PUT    /api/v1/users/{id}         # Full update user
PATCH  /api/v1/users/{id}         # Partial update user
DELETE /api/v1/users/{id}         # Delete user
GET    /api/v1/users/{id}/orders  # Nested resource
```

### Naming Conventions
- Use **nouns**, not verbs: `/users` not `/getUsers`
- Use **plural** for collections: `/users` not `/user`
- Use **kebab-case** for multi-word: `/user-profiles`
- Use **camelCase** for JSON fields: `{ "firstName": "John" }`
- Max nesting depth: 2 levels (`/users/{id}/orders`)

### HTTP Status Codes

| Code | Meaning | When to Use |
|------|---------|-------------|
| 200 | OK | Successful GET, PUT, PATCH |
| 201 | Created | Successful POST (return created resource + Location header) |
| 204 | No Content | Successful DELETE |
| 400 | Bad Request | Validation errors, malformed request |
| 401 | Unauthorized | Missing or invalid authentication |
| 403 | Forbidden | Authenticated but insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 409 | Conflict | Duplicate resource, version conflict |
| 422 | Unprocessable Entity | Valid syntax but semantic errors |
| 429 | Too Many Requests | Rate limit exceeded (include Retry-After) |
| 500 | Internal Server Error | Unexpected server failure |

### Error Response Format (RFC 7807)

```json
{
  "type": "https://api.example.com/errors/validation",
  "title": "Validation Error",
  "status": 422,
  "detail": "The 'email' field must be a valid email address.",
  "instance": "/api/v1/users",
  "errors": [
    {
      "field": "email",
      "message": "Must be a valid email address",
      "value": "not-an-email"
    }
  ]
}
```

### Pagination

#### Cursor-Based (Recommended for Large Datasets)
```json
{
  "data": [...],
  "pagination": {
    "next_cursor": "eyJpZCI6MTAwfQ==",
    "has_more": true,
    "limit": 25
  }
}
```
Request: `GET /users?cursor=eyJpZCI6MTAwfQ==&limit=25`

#### Offset-Based (Simple Cases)
```json
{
  "data": [...],
  "pagination": {
    "total": 150,
    "page": 2,
    "per_page": 25,
    "total_pages": 6
  }
}
```
Request: `GET /users?page=2&per_page=25`

### Filtering, Sorting, and Field Selection

```
GET /users?status=active&role=admin        # Filtering
GET /users?sort=-created_at,name           # Sorting (- prefix = DESC)
GET /users?fields=id,name,email            # Field selection (sparse fieldsets)
GET /users?search=john                     # Full-text search
```

### Versioning Strategies

| Strategy | Example | Pros | Cons |
|----------|---------|------|------|
| URL Path | `/api/v1/users` | Simple, explicit, cacheable | URL pollution |
| Header | `Accept: application/vnd.api.v1+json` | Clean URLs | Less discoverable |
| Query Param | `/api/users?version=1` | Simple | Not RESTful |

**Recommendation**: URL path versioning for simplicity and clarity.

### Rate Limiting Headers

```
X-RateLimit-Limit: 100        # Max requests per window
X-RateLimit-Remaining: 45     # Remaining requests
X-RateLimit-Reset: 1672531200  # Window reset timestamp (Unix epoch)
Retry-After: 30                # Seconds to wait (on 429)
```

---

## GraphQL Design

### When to Choose GraphQL over REST
- Clients need flexible data fetching (mobile vs web)
- Multiple resources needed in single request
- Rapid frontend iteration without backend changes
- Real-time subscriptions needed

### Schema Design Principles

```graphql
type Query {
  user(id: ID!): User
  users(filter: UserFilter, pagination: PaginationInput): UserConnection!
}

type Mutation {
  createUser(input: CreateUserInput!): CreateUserPayload!
  updateUser(id: ID!, input: UpdateUserInput!): UpdateUserPayload!
}

type Subscription {
  orderStatusChanged(orderId: ID!): Order!
}

# Use Input types for mutations
input CreateUserInput {
  name: String!
  email: String!
}

# Use Payload types for mutation responses
type CreateUserPayload {
  user: User
  errors: [UserError!]
}

# Relay-style pagination
type UserConnection {
  edges: [UserEdge!]!
  pageInfo: PageInfo!
  totalCount: Int!
}
```

### Anti-Patterns
- **Over-fetching**: Querying fields you don't need
- **N+1 Queries**: Use DataLoader for batch loading
- **Deeply Nested Queries**: Set max depth limit (e.g., 10)
- **No Rate Limiting**: Implement query complexity analysis

---

## gRPC Design

### When to Choose gRPC
- Service-to-service communication (internal microservices)
- High performance, low latency requirements
- Bi-directional streaming needed
- Strong contract enforcement

### Proto File Best Practices

```protobuf
syntax = "proto3";
package user.v1;

service UserService {
  rpc GetUser(GetUserRequest) returns (GetUserResponse);
  rpc ListUsers(ListUsersRequest) returns (ListUsersResponse);
  rpc CreateUser(CreateUserRequest) returns (CreateUserResponse);
  rpc StreamUpdates(StreamUpdatesRequest) returns (stream UserUpdate);
}

message GetUserRequest {
  string user_id = 1;
}

message GetUserResponse {
  User user = 1;
}

// Use field masks for partial updates
message UpdateUserRequest {
  string user_id = 1;
  User user = 2;
  google.protobuf.FieldMask update_mask = 3;
}
```

### gRPC Error Handling
Use standard gRPC status codes: `OK`, `NOT_FOUND`, `INVALID_ARGUMENT`, `PERMISSION_DENIED`, `INTERNAL`, etc.

---

## API Security

| Practice | Implementation |
|----------|----------------|
| Authentication | JWT (short-lived access + refresh tokens) or OAuth 2.0 |
| Authorization | Check permissions on every endpoint, not just auth |
| Input Validation | Validate all inputs; reject unexpected fields |
| Rate Limiting | Per-user and per-IP; stricter for auth endpoints |
| CORS | Whitelist specific origins; never use `*` in production |
| Security Headers | `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options` |
| Request Size Limits | Max body size (e.g., 1MB), max upload size |
| Idempotency | Use idempotency keys for POST/PUT to prevent duplicates |

---

## API Documentation

### OpenAPI 3.1 Specification

Generate from code annotations when possible:
- **Node.js**: swagger-jsdoc, tsoa, NestJS Swagger
- **Python**: FastAPI (auto-generates), drf-spectacular
- **Go**: swag, oapi-codegen
- **Java**: springdoc-openapi

Include in every API doc:
- Authentication requirements
- Request/response examples
- Error response schemas
- Rate limiting details
- Changelog / versioning info

---

## Sources
- Microsoft REST API Guidelines: https://github.com/microsoft/api-guidelines
- Google API Design Guide: https://cloud.google.com/apis/design
- JSON:API Specification: https://jsonapi.org/
- GraphQL Best Practices: https://graphql.org/learn/best-practices/
- gRPC Documentation: https://grpc.io/docs/
