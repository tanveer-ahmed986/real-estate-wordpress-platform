# Security Hardening

OWASP API Security Top 10 (2023), input validation, encryption, and security best practices.

---

## OWASP API Security Top 10 (2023)

### API1:2023 - Broken Object Level Authorization (BOLA)

**Risk**: Users access objects they don't own by manipulating IDs.

```typescript
// VULNERABLE
app.get('/api/orders/:id', async (req, res) => {
  const order = await db.orders.findById(req.params.id);
  res.json(order); // Anyone can access any order!
});

// SECURE
app.get('/api/orders/:id', async (req, res) => {
  const order = await db.orders.findOne({
    id: req.params.id,
    userId: req.user.id  // Ownership check
  });
  if (!order) return res.status(404).json({ error: 'Not found' });
  res.json(order);
});
```

**Prevention**:
- Check object ownership on EVERY access
- Use authorization middleware that validates resource ownership
- Prefer UUIDs over sequential IDs (harder to enumerate)
- Implement row-level security at the database level

### API2:2023 - Broken Authentication

**Prevention**:
- Use proven auth libraries (never roll your own crypto)
- Enforce strong password policies
- Implement MFA for sensitive operations
- Use secure session management
- Rate limit authentication endpoints aggressively
- Lock accounts after failed attempts (with progressive delays)
- Use secure token storage (HttpOnly, Secure, SameSite cookies)

### API3:2023 - Broken Object Property Level Authorization

**Risk**: API returns more data than the client should see.

```typescript
// VULNERABLE - returns all fields including internal ones
app.get('/api/users/:id', async (req, res) => {
  const user = await db.users.findById(req.params.id);
  res.json(user); // Exposes password_hash, internal_notes, etc.
});

// SECURE - explicit field selection based on role
app.get('/api/users/:id', async (req, res) => {
  const user = await db.users.findById(req.params.id);
  const fields = getFieldsByRole(req.user.role);
  res.json(pick(user, fields));
});

function getFieldsByRole(role) {
  const base = ['id', 'name', 'email', 'avatar'];
  if (role === 'admin') return [...base, 'role', 'created_at', 'last_login'];
  return base;
}
```

**Prevention**:
- Never return raw database objects
- Use DTOs/serializers with explicit field allowlists
- Filter response properties based on user role
- Validate write properties (prevent mass assignment)

### API4:2023 - Unrestricted Resource Consumption

**Prevention**:
- Rate limiting per user/IP/API key
- Pagination with maximum page size
- Request body size limits
- File upload size limits
- Query complexity limits (GraphQL)
- Timeout on all operations
- Resource quotas per tenant

```typescript
// Rate limiting configuration
const rateLimiter = rateLimit({
  windowMs: 60 * 1000,      // 1 minute window
  max: 100,                  // 100 requests per window
  standardHeaders: true,     // Return rate limit info in headers
  legacyHeaders: false,
  keyGenerator: (req) => req.user?.id || req.ip,
  handler: (req, res) => {
    res.status(429).json({
      error: {
        code: 'RATE_LIMIT_EXCEEDED',
        message: 'Too many requests',
        retry_after: res.getHeader('Retry-After')
      }
    });
  }
});

// Tiered rate limits
const tiers = {
  free:       { rpm: 60,   rpd: 1000 },
  pro:        { rpm: 300,  rpd: 10000 },
  enterprise: { rpm: 1000, rpd: 100000 }
};
```

### API5:2023 - Broken Function Level Authorization

**Prevention**:
- Implement RBAC/ABAC consistently across all endpoints
- Deny by default, explicitly grant access
- Separate admin and user API routes
- Centralized authorization middleware

```typescript
// Role-based middleware
function authorize(...roles) {
  return (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: 'Unauthenticated' });
    if (!roles.includes(req.user.role)) {
      return res.status(403).json({ error: 'Insufficient permissions' });
    }
    next();
  };
}

// Usage
app.delete('/api/users/:id', authorize('admin'), deleteUser);
app.get('/api/users/me', authorize('user', 'admin'), getProfile);
```

### API6:2023 - Unrestricted Access to Sensitive Business Flows

**Prevention**:
- CAPTCHA for registration, password reset
- Rate limit sensitive operations (purchases, transfers)
- Device fingerprinting for unusual activity
- Step-up authentication for high-value operations

### API7:2023 - Server-Side Request Forgery (SSRF)

**Prevention**:
- Validate and allowlist URLs for server-side requests
- Block requests to internal IP ranges (10.x, 172.16.x, 192.168.x, 127.x)
- Use a URL parser to normalize before validation
- Disable redirects or limit redirect depth
- Network segmentation (outbound proxy/firewall)

### API8:2023 - Security Misconfiguration

**Prevention checklist**:
- [ ] Disable debug mode in production
- [ ] Remove default credentials
- [ ] Disable unnecessary HTTP methods
- [ ] Set security headers (see below)
- [ ] Enable HTTPS only (HSTS)
- [ ] Disable directory listing
- [ ] Remove server version headers
- [ ] Configure CORS properly
- [ ] Disable introspection in production (GraphQL)
- [ ] Keep dependencies updated

### API9:2023 - Improper Inventory Management

**Prevention**:
- Document all APIs (OpenAPI/Swagger)
- Track API versions and deprecation status
- Remove or restrict access to deprecated APIs
- Separate production and non-production environments
- Inventory all endpoints including internal/debug

### API10:2023 - Unsafe Consumption of Third-Party APIs

**Prevention**:
- Validate responses from third-party APIs
- Set timeouts and circuit breakers
- Don't trust external data (treat as untrusted input)
- Use TLS for all external connections
- Implement retry with backoff

---

## Security Headers

```typescript
// Essential security headers
app.use((req, res, next) => {
  // Prevent MIME-type sniffing
  res.setHeader('X-Content-Type-Options', 'nosniff');

  // Prevent clickjacking
  res.setHeader('X-Frame-Options', 'DENY');

  // XSS protection (legacy browsers)
  res.setHeader('X-XSS-Protection', '1; mode=block');

  // HTTPS enforcement
  res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

  // Content Security Policy
  res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'");

  // Referrer policy
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

  // Permissions policy
  res.setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

  next();
});
```

---

## CORS Configuration

```typescript
// NEVER in production
app.use(cors({ origin: '*' })); // DANGEROUS!

// Proper CORS
const allowedOrigins = [
  'https://app.example.com',
  'https://admin.example.com',
];

app.use(cors({
  origin: (origin, callback) => {
    if (!origin || allowedOrigins.includes(origin)) {
      callback(null, true);
    } else {
      callback(new Error('Not allowed by CORS'));
    }
  },
  methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
  allowedHeaders: ['Content-Type', 'Authorization'],
  credentials: true,
  maxAge: 86400, // Cache preflight for 24 hours
}));
```

---

## Input Validation

### Validation Strategy

```
Client Input → Schema Validation → Business Validation → Process
                    ↓                     ↓
              400 Bad Request      422 Unprocessable Entity
```

### Schema Validation (Zod example)

```typescript
import { z } from 'zod';

const CreateUserSchema = z.object({
  email: z.string().email().max(255),
  password: z.string().min(8).max(128)
    .regex(/[A-Z]/, 'Must contain uppercase')
    .regex(/[a-z]/, 'Must contain lowercase')
    .regex(/[0-9]/, 'Must contain number')
    .regex(/[^A-Za-z0-9]/, 'Must contain special character'),
  name: z.string().min(1).max(100).trim(),
  role: z.enum(['user', 'admin']).default('user'),
  age: z.number().int().min(13).max(150).optional(),
});

// Middleware
function validate(schema) {
  return (req, res, next) => {
    const result = schema.safeParse(req.body);
    if (!result.success) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          details: result.error.issues
        }
      });
    }
    req.validated = result.data;
    next();
  };
}
```

### SQL Injection Prevention

```typescript
// NEVER - string interpolation
const query = `SELECT * FROM users WHERE email = '${email}'`;

// ALWAYS - parameterized queries
const result = await db.query('SELECT * FROM users WHERE email = $1', [email]);

// Or use an ORM with built-in parameterization
const user = await prisma.user.findUnique({ where: { email } });
```

### Path Traversal Prevention

```typescript
import path from 'path';

function safePath(baseDir, userPath) {
  const resolved = path.resolve(baseDir, userPath);
  if (!resolved.startsWith(path.resolve(baseDir))) {
    throw new Error('Path traversal detected');
  }
  return resolved;
}
```

---

## Secrets Management

### Rules
1. **Never** hardcode secrets in source code
2. **Never** commit `.env` files to version control
3. **Never** log secrets or include in error messages
4. **Never** pass secrets in URL query parameters
5. **Always** use environment variables or secret managers
6. **Always** rotate compromised credentials immediately
7. **Always** use different secrets per environment

### Secret Management Solutions

| Solution | Use Case |
|----------|----------|
| Environment variables | Simple deployments |
| AWS Secrets Manager / Azure Key Vault | Cloud deployments |
| HashiCorp Vault | Multi-cloud, on-premises |
| Docker secrets | Container orchestration |
| `.env` file (local only) | Development only |

### .env.example Template
```bash
# Authentication
JWT_SECRET=                    # Generate: openssl rand -hex 32
JWT_EXPIRY=15m
REFRESH_TOKEN_EXPIRY=7d

# Database
DATABASE_URL=postgresql://user:pass@host:5432/db?sslmode=require
DATABASE_POOL_SIZE=20

# Redis
REDIS_URL=redis://host:6379

# External APIs
STRIPE_SECRET_KEY=
SENDGRID_API_KEY=

# Application
NODE_ENV=production
PORT=3000
LOG_LEVEL=info
```

---

## Data Protection

### Encryption at Rest
- Encrypt sensitive database columns (PII, financial data)
- Use database-level encryption (TDE) or application-level encryption
- Encrypt backups and log files containing sensitive data

### Encryption in Transit
- TLS 1.2+ for all connections (enforce via HSTS)
- mTLS for service-to-service communication
- Certificate pinning for mobile clients

### Data Handling

```typescript
// Hash passwords with bcrypt (cost factor 12+)
import bcrypt from 'bcrypt';
const hash = await bcrypt.hash(password, 12);

// Mask sensitive data in logs
function maskEmail(email) {
  const [local, domain] = email.split('@');
  return `${local[0]}***@${domain}`;
}

function maskCard(number) {
  return `****-****-****-${number.slice(-4)}`;
}

// Sanitize error responses
function sanitizeError(error) {
  if (process.env.NODE_ENV === 'production') {
    return { code: error.code, message: 'An error occurred' };
  }
  return { code: error.code, message: error.message, stack: error.stack };
}
```
