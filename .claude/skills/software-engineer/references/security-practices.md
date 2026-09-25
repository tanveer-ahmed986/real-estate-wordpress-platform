# Security Best Practices

## OWASP Top 10:2025

### A01:2025 - Broken Access Control

**Risk**: Users acting outside their intended permissions.

**Mitigations**:
- Deny by default; whitelist allowed actions
- Implement RBAC (Role-Based) or ABAC (Attribute-Based) access control
- Enforce access control server-side, never trust client
- Disable directory listing; protect file metadata
- Rate limit API access to minimize automated attacks
- Invalidate JWT/sessions on logout
- Use parameterized resource references, not direct object references

```typescript
// GOOD: Server-side authorization check
async function getOrder(userId: string, orderId: string) {
  const order = await db.orders.findById(orderId);
  if (order.userId !== userId) throw new ForbiddenError();
  return order;
}

// BAD: Trusting client-side role
app.get('/admin', (req, res) => {
  if (req.body.isAdmin) { /* Never trust client */ }
});
```

### A02:2025 - Security Misconfiguration

**Mitigations**:
- Remove default credentials and sample apps
- Disable unnecessary features, frameworks, and components
- Configure security headers:
  ```
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Content-Security-Policy: default-src 'self'
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: camera=(), microphone=(), geolocation=()
  ```
- Automate environment hardening with IaC
- Review cloud storage permissions (no public S3 buckets)

### A03:2025 - Software Supply Chain Failures

**Mitigations**:
- Use lock files (`package-lock.json`, `poetry.lock`, `go.sum`)
- Pin exact dependency versions in production
- Run dependency audits: `npm audit`, `pip audit`, `govulncheck`
- Use Subresource Integrity (SRI) for CDN assets
- Verify package signatures and checksums
- Use private registries for internal packages
- Implement Software Bill of Materials (SBOM)

### A04:2025 - Cryptographic Failures

**Mitigations**:
- Use TLS 1.3 for all data in transit
- Password hashing: bcrypt (cost 12+), Argon2id, or scrypt
- Never use: MD5, SHA1, DES, RC4, ECB mode
- Generate keys with cryptographically secure random: `crypto.randomBytes()`
- Store encryption keys in vault (HashiCorp Vault, AWS KMS, Azure Key Vault)
- Classify data; encrypt PII and sensitive data at rest
- Disable caching for responses containing sensitive data

### A05:2025 - Injection

**Mitigations**:
- Use parameterized queries / prepared statements (ALWAYS)
- Use ORMs with parameterized queries (Prisma, SQLAlchemy, GORM)
- Validate and sanitize all input at system boundaries
- Output encoding for HTML context (prevent XSS)
- Use allowlists for permitted characters, not denylists
- Escape special characters for OS commands, LDAP, XML

```typescript
// GOOD: Parameterized query
const user = await db.query('SELECT * FROM users WHERE id = $1', [userId]);

// BAD: String interpolation
const user = await db.query(`SELECT * FROM users WHERE id = '${userId}'`);
```

### A06:2025 - Insecure Design

**Mitigations**:
- Threat modeling during design phase (STRIDE, DREAD)
- Establish secure design patterns library
- Write abuse cases alongside use cases
- Implement defense in depth (multiple security layers)
- Limit resource consumption per user/session
- Segregate tenant data in multi-tenant systems

### A07:2025 - Authentication Failures

**Mitigations**:
- Implement multi-factor authentication (MFA/2FA)
- Never ship with default credentials
- Rate limit and exponential backoff for login attempts
- Use secure session management:
  - HttpOnly, Secure, SameSite=Strict cookies
  - Regenerate session ID on login
  - Set appropriate session timeouts
- Password requirements: minimum 8 chars, check against breached password lists
- JWT best practices:
  - Short expiry (15 min access, 7 day refresh)
  - Store refresh tokens server-side
  - Validate `iss`, `aud`, `exp` claims

### A08:2025 - Software or Data Integrity Failures

**Mitigations**:
- Verify digital signatures on updates and packages
- Ensure CI/CD pipelines have proper access control and audit logging
- Validate serialized data (don't deserialize untrusted data)
- Use integrity verification for critical data
- Code review for all CI/CD configuration changes

### A09:2025 - Security Logging and Alerting Failures

**Mitigations**:
- Log authentication events (success and failure)
- Log access control failures
- Log input validation failures
- Use structured logging (JSON format) with correlation IDs
- Never log sensitive data (passwords, tokens, PII)
- Set up real-time alerting for suspicious patterns
- Ensure logs are tamper-proof (append-only, centralized)
- Retain logs per compliance requirements

### A10:2025 - Mishandling of Exceptional Conditions

**Mitigations**:
- Never expose stack traces or internal errors to users
- Return generic error messages; log detailed errors server-side
- Implement global error handlers
- Handle all error paths explicitly
- Use circuit breakers for external service calls
- Graceful degradation on service failures

---

## Authentication Patterns

### JWT Authentication Flow

```
1. User sends credentials → POST /auth/login
2. Server validates → Returns { accessToken (15min), refreshToken (7d) }
3. Client stores accessToken in memory, refreshToken in HttpOnly cookie
4. Client sends: Authorization: Bearer <accessToken>
5. On 401 → POST /auth/refresh with refreshToken
6. On logout → DELETE /auth/logout (invalidate refresh token server-side)
```

### OAuth 2.0 / OIDC

```
1. Client redirects to Authorization Server (/authorize)
2. User authenticates and consents
3. Auth Server redirects back with authorization code
4. Client exchanges code for tokens (server-side, with client_secret)
5. Client uses access token for API calls
```

Use Authorization Code Flow with PKCE for SPAs and mobile apps.

---

## Security Headers Checklist

```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self' https://api.example.com; frame-ancestors 'none'
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
X-XSS-Protection: 0  (deprecated, CSP replaces it)
```

---

## Environment Security

| Practice | Implementation |
|----------|----------------|
| Secrets Management | Use vault, never commit `.env` files |
| `.gitignore` | Include `.env`, `*.key`, `*.pem`, credentials files |
| Environment Variables | Inject at runtime, not build time |
| Principle of Least Privilege | Minimal permissions for each service/user |
| Network Segmentation | Databases not publicly accessible |
| Regular Rotation | Rotate API keys, tokens, certificates |

---

## Sources
- OWASP Top 10:2025: https://owasp.org/Top10/2025/
- OWASP Cheat Sheet Series: https://cheatsheetseries.owasp.org/
- NIST Cybersecurity Framework
- CWE/SANS Top 25 Software Errors
