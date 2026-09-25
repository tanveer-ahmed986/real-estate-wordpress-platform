# Authentication & Authorization

JWT, OAuth 2.0, OIDC, API keys, RBAC, ABAC, and session management patterns.

---

## Authentication Strategy Selection

| Method | Best For | Not For |
|--------|----------|---------|
| JWT (stateless) | SPAs, mobile apps, microservices | Long sessions needing revocation |
| Session cookies | Traditional web apps, SSR | Mobile apps, cross-domain |
| OAuth 2.0 / OIDC | Third-party login, delegated auth | Simple internal apps |
| API Keys | Service-to-service, developer APIs | End-user authentication |
| mTLS | Service mesh, high-security internal | Public-facing APIs |

---

## JWT (JSON Web Tokens)

### Token Structure

```
Header.Payload.Signature

Header:  { "alg": "RS256", "typ": "JWT", "kid": "key-2024-01" }
Payload: { "sub": "user_123", "iat": 1705312200, "exp": 1705313100,
           "roles": ["user"], "iss": "api.example.com" }
Signature: RSASHA256(base64url(header) + "." + base64url(payload), privateKey)
```

### Best Practices

| Practice | Guidance |
|----------|----------|
| Algorithm | Use RS256 or ES256 (asymmetric). Never HS256 in multi-service. Never `none`. |
| Expiry | Access token: 15 minutes. Refresh token: 7-30 days. |
| Claims | Minimal: `sub`, `iat`, `exp`, `iss`, `aud`, roles/permissions |
| Storage (browser) | HttpOnly + Secure + SameSite=Strict cookie. Never localStorage. |
| Storage (mobile) | Secure Keychain (iOS) / Keystore (Android) |
| Revocation | Short-lived access + refresh rotation + deny list for compromised tokens |
| Key rotation | Rotate signing keys periodically, use `kid` header |
| Validation | Verify signature, exp, iss, aud on EVERY request |

### Implementation Pattern

```typescript
// Token generation
import jwt from 'jsonwebtoken';

function generateTokens(user) {
  const accessToken = jwt.sign(
    { sub: user.id, roles: user.roles },
    process.env.JWT_PRIVATE_KEY,
    {
      algorithm: 'RS256',
      expiresIn: '15m',
      issuer: 'api.example.com',
      audience: 'app.example.com',
    }
  );

  const refreshToken = crypto.randomBytes(64).toString('hex');
  // Store refresh token hash in database with user_id, expiry, device info
  await db.refreshTokens.create({
    token_hash: await bcrypt.hash(refreshToken, 10),
    user_id: user.id,
    expires_at: addDays(new Date(), 30),
    device: req.headers['user-agent'],
  });

  return { accessToken, refreshToken };
}

// Token refresh with rotation
async function refreshAccessToken(refreshToken) {
  const stored = await db.refreshTokens.findByHash(refreshToken);
  if (!stored || stored.expires_at < new Date()) {
    throw new UnauthorizedError('Invalid refresh token');
  }

  // Rotate: invalidate old, issue new
  await db.refreshTokens.delete(stored.id);
  return generateTokens(stored.user);
}

// Middleware
function authenticate(req, res, next) {
  const token = req.cookies.access_token || extractBearerToken(req);
  if (!token) return res.status(401).json({ error: 'Authentication required' });

  try {
    req.user = jwt.verify(token, process.env.JWT_PUBLIC_KEY, {
      algorithms: ['RS256'],
      issuer: 'api.example.com',
      audience: 'app.example.com',
    });
    next();
  } catch (err) {
    if (err.name === 'TokenExpiredError') {
      return res.status(401).json({ error: 'Token expired', code: 'TOKEN_EXPIRED' });
    }
    return res.status(401).json({ error: 'Invalid token' });
  }
}
```

---

## OAuth 2.0 & OIDC

### Flow Selection

| Flow | Use Case | Security Level |
|------|----------|---------------|
| Authorization Code + PKCE | SPAs, mobile apps, web apps | High |
| Client Credentials | Machine-to-machine | High |
| Device Code | Smart TVs, CLI tools | Medium |

**Deprecated** (do NOT use): Implicit flow, Resource Owner Password.

### Authorization Code + PKCE Flow

```
1. Client generates code_verifier (random string) and code_challenge (SHA256 hash)
2. Client redirects user to authorization server:
   GET /authorize?response_type=code&client_id=...&redirect_uri=...
     &scope=openid profile email&state=...&code_challenge=...
     &code_challenge_method=S256

3. User authenticates and consents
4. Authorization server redirects back with code:
   GET /callback?code=AUTH_CODE&state=...

5. Client exchanges code for tokens:
   POST /token
   grant_type=authorization_code&code=AUTH_CODE&redirect_uri=...
   &client_id=...&code_verifier=...

6. Server returns:
   { access_token, id_token, refresh_token, token_type, expires_in }
```

### OIDC Claims

```json
// ID Token claims
{
  "iss": "https://auth.example.com",
  "sub": "user_123",
  "aud": "client_abc",
  "exp": 1705313100,
  "iat": 1705312200,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "email_verified": true,
  "picture": "https://example.com/avatar.jpg"
}
```

---

## API Key Authentication

### Best Practices

```typescript
// Generate secure API keys
function generateApiKey() {
  const prefix = 'sk_live_'; // Identifiable prefix
  const key = crypto.randomBytes(32).toString('base64url');
  return prefix + key;
}

// Store only the hash
async function createApiKey(userId, name) {
  const key = generateApiKey();
  const hash = await bcrypt.hash(key, 10);

  await db.apiKeys.create({
    key_prefix: key.substring(0, 12), // For display/identification
    key_hash: hash,
    user_id: userId,
    name: name,
    scopes: ['read:orders', 'write:orders'],
    last_used_at: null,
    expires_at: addMonths(new Date(), 12),
  });

  return key; // Return full key ONCE, never retrievable again
}

// Validate API key
async function validateApiKey(key) {
  const prefix = key.substring(0, 12);
  const candidates = await db.apiKeys.findByPrefix(prefix);

  for (const candidate of candidates) {
    if (await bcrypt.compare(key, candidate.key_hash)) {
      if (candidate.expires_at < new Date()) throw new Error('Key expired');
      await db.apiKeys.updateLastUsed(candidate.id);
      return candidate;
    }
  }
  throw new Error('Invalid API key');
}
```

### API Key Security Rules
- Prefix keys for identification (e.g., `sk_live_`, `pk_test_`)
- Store only hashes, never plaintext
- Support expiration and rotation
- Scope keys to minimum required permissions
- Rate limit per API key
- Log usage for audit trail
- Allow users to revoke keys immediately

---

## RBAC (Role-Based Access Control)

### Design Pattern

```typescript
// Role hierarchy
const ROLES = {
  super_admin: { inherits: ['admin'] },
  admin: { inherits: ['manager'] },
  manager: { inherits: ['user'] },
  user: { inherits: ['viewer'] },
  viewer: { inherits: [] },
};

// Permission assignments
const PERMISSIONS = {
  super_admin: ['*'],
  admin: ['users:*', 'orders:*', 'settings:*'],
  manager: ['users:read', 'orders:*', 'reports:read'],
  user: ['orders:read', 'orders:create', 'profile:*'],
  viewer: ['orders:read', 'products:read'],
};

// Middleware
function requirePermission(...perms) {
  return (req, res, next) => {
    const userPerms = resolvePermissions(req.user.role);
    const hasAll = perms.every(p =>
      userPerms.includes('*') || userPerms.includes(p) ||
      userPerms.includes(p.split(':')[0] + ':*')
    );
    if (!hasAll) return res.status(403).json({ error: 'Forbidden' });
    next();
  };
}

// Usage
app.delete('/api/users/:id', authenticate, requirePermission('users:delete'), deleteUser);
```

---

## ABAC (Attribute-Based Access Control)

### When to Use ABAC over RBAC

| Signal | Use ABAC |
|--------|----------|
| Fine-grained per-resource policies | Yes |
| Multi-tenant with different rules per tenant | Yes |
| Context-dependent access (time, location, IP) | Yes |
| Dynamic policies without code changes | Yes |

### Policy Pattern

```typescript
// Policy engine
interface Policy {
  effect: 'allow' | 'deny';
  condition: (context: PolicyContext) => boolean;
}

interface PolicyContext {
  subject: { id: string; role: string; department: string; };
  resource: { type: string; owner: string; sensitivity: string; };
  action: string;
  environment: { time: Date; ip: string; };
}

const policies: Policy[] = [
  {
    effect: 'allow',
    condition: (ctx) =>
      ctx.action === 'read' &&
      ctx.resource.owner === ctx.subject.id,
  },
  {
    effect: 'allow',
    condition: (ctx) =>
      ctx.subject.role === 'admin' &&
      ctx.resource.sensitivity !== 'top-secret',
  },
  {
    effect: 'deny',
    condition: (ctx) =>
      ctx.environment.time.getHours() < 6 ||
      ctx.environment.time.getHours() > 22,
    // No access outside business hours
  },
];

function evaluate(context: PolicyContext): boolean {
  // Deny-first: any deny policy blocks access
  if (policies.some(p => p.effect === 'deny' && p.condition(context))) return false;
  // Then check for allow
  return policies.some(p => p.effect === 'allow' && p.condition(context));
}
```

---

## Multi-Tenant Authentication

### Strategies

| Strategy | Isolation | Complexity | Use Case |
|----------|-----------|------------|----------|
| Database per tenant | Highest | High | Enterprise SaaS, compliance |
| Schema per tenant | High | Medium | Multi-tenant with data isolation |
| Row-level security | Medium | Low | Cost-effective multi-tenancy |
| Tenant ID in JWT | Low | Lowest | Simple multi-tenant apps |

### Row-Level Security Pattern

```typescript
// Middleware: inject tenant context
function tenantContext(req, res, next) {
  const tenantId = req.user.tenant_id;
  if (!tenantId) return res.status(403).json({ error: 'No tenant context' });
  req.tenantId = tenantId;
  next();
}

// All queries scoped to tenant
async function getOrders(req) {
  return db.orders.findMany({
    where: {
      tenant_id: req.tenantId,  // Always filter by tenant
      ...req.filters,
    },
  });
}

// PostgreSQL RLS
// CREATE POLICY tenant_isolation ON orders
//   USING (tenant_id = current_setting('app.tenant_id')::uuid);
```

---

## Session Management Security

### Secure Session Configuration

```typescript
app.use(session({
  name: '__session',                // Non-default name
  secret: process.env.SESSION_SECRET,
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,                 // Not accessible via JavaScript
    secure: true,                   // HTTPS only
    sameSite: 'strict',             // CSRF protection
    maxAge: 24 * 60 * 60 * 1000,   // 24 hours
    domain: '.example.com',
    path: '/',
  },
  store: new RedisStore({ client: redisClient }),
  rolling: true,                    // Reset expiry on activity
}));
```

### Session Security Rules
- Regenerate session ID after authentication
- Invalidate sessions on logout (server-side deletion)
- Set absolute session timeout (not just idle)
- Bind sessions to IP/user-agent for sensitive apps
- Limit concurrent sessions per user
