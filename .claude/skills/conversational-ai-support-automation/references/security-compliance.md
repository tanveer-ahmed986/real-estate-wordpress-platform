# Security & Compliance

Security best practices and compliance patterns for conversational AI systems.

---

## Data Privacy & GDPR

### PII Detection & Masking

```typescript
const PII_PATTERNS = {
  email: /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/g,
  phone: /\b(\+\d{1,3}[-.]?)?\(?\d{3}\)?[-.]?\d{3}[-.]?\d{4}\b/g,
  ssn: /\b\d{3}-\d{2}-\d{4}\b/g,
  creditCard: /\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/g
};

function maskPII(text: string): { masked: string; pii: Array<{type: string; value: string}> } {
  let masked = text;
  const pii: Array<{type: string; value: string}> = [];

  for (const [type, pattern] of Object.entries(PII_PATTERNS)) {
    const matches = text.match(pattern);
    if (matches) {
      matches.forEach(match => {
        pii.push({ type, value: match });
        masked = masked.replace(match, `[${type.toUpperCase()}_REDACTED]`);
      });
    }
  }

  return { masked, pii };
}

// Usage in logging
function logConversation(message: string) {
  const { masked, pii } = maskPII(message);

  // Log masked version
  logger.info('Conversation:', masked);

  // Store PII separately in encrypted storage (if needed for business purposes)
  if (pii.length > 0 && hasUserConsent) {
    encryptedStorage.store(pii);
  }
}
```

### GDPR Compliance

```typescript
class GDPRCompliance {
  // Right to Access (Article 15)
  async exportUserData(userId: string): Promise<UserDataExport> {
    return {
      personalInfo: await db.getUserProfile(userId),
      conversations: await db.getConversations(userId),
      crmData: await crmClient.getContact(userId),
      consentRecords: await db.getConsents(userId)
    };
  }

  // Right to Erasure (Article 17)
  async deleteUserData(userId: string): Promise<void> {
    await db.deleteUserProfile(userId);
    await db.deleteConversations(userId);
    await crmClient.deleteContact(userId);
    await db.deleteConsents(userId);

    // Anonymize audit logs (keep for compliance, remove PII)
    await db.anonymizeAuditLogs(userId);
  }

  // Right to Rectification (Article 16)
  async updateUserData(userId: string, updates: Partial<UserProfile>): Promise<void> {
    await db.updateUserProfile(userId, updates);
    await crmClient.updateContact(userId, updates);
  }

  // Consent Management
  async recordConsent(userId: string, consentType: string): Promise<void> {
    await db.storeConsent({
      userId,
      type: consentType,
      timestamp: new Date(),
      ipAddress: request.ip,
      method: 'chatbot'
    });
  }

  async withdrawConsent(userId: string, consentType: string): Promise<void> {
    await db.withdrawConsent(userId, consentType);

    // Stop processing if marketing consent withdrawn
    if (consentType === 'marketing') {
      await emailClient.unsubscribe(userId);
      await chatbot.disableProactiveMessages(userId);
    }
  }
}
```

### Data Retention Policy

```typescript
const RETENTION_POLICY = {
  conversations: 90, // days
  personalInfo: 730, // 2 years
  auditLogs: 2555,   // 7 years
  analytics: 365     // 1 year
};

async function enforceRetentionPolicy() {
  const now = new Date();

  // Delete old conversations
  await db.deleteConversationsBefore(
    subDays(now, RETENTION_POLICY.conversations)
  );

  // Delete inactive user data
  const inactiveUsers = await db.getUsersInactiveSince(
    subDays(now, RETENTION_POLICY.personalInfo)
  );
  for (const user of inactiveUsers) {
    await gdprCompliance.deleteUserData(user.id);
  }
}
```

---

## Authentication & Authorization

### API Key Management

```typescript
// NEVER do this
const API_KEY = 'sk-1234567890abcdef'; // ❌ Hardcoded

// Instead, use environment variables
const API_KEY = process.env.OPENAI_API_KEY; // ✅ Correct

// Or use secrets management
import { SecretsManager } from '@aws-sdk/client-secrets-manager';

async function getApiKey(): Promise<string> {
  const client = new SecretsManager({ region: 'us-east-1' });
  const response = await client.getSecretValue({ SecretId: 'openai-api-key' });
  return JSON.parse(response.SecretString!).apiKey;
}
```

### User Authentication (if required)

```typescript
import jwt from 'jsonwebtoken';

interface UserSession {
  userId: string;
  email: string;
  role: string;
}

function generateToken(user: UserSession): string {
  return jwt.sign(user, process.env.JWT_SECRET!, {
    expiresIn: '24h'
  });
}

function verifyToken(token: string): UserSession {
  try {
    return jwt.verify(token, process.env.JWT_SECRET!) as UserSession;
  } catch (error) {
    throw new Error('Invalid token');
  }
}

// Middleware
function authenticateRequest(req, res, next) {
  const token = req.headers.authorization?.replace('Bearer ', '');

  if (!token) {
    return res.status(401).json({ error: 'No token provided' });
  }

  try {
    req.user = verifyToken(token);
    next();
  } catch (error) {
    return res.status(401).json({ error: 'Invalid token' });
  }
}
```

### Rate Limiting

```typescript
import rateLimit from 'express-rate-limit';

// Prevent abuse
const chatLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // limit each IP to 100 requests per windowMs
  message: 'Too many requests, please try again later.',
  standardHeaders: true,
  legacyHeaders: false
});

app.use('/api/chat', chatLimiter);

// Per-user rate limiting
const userLimiter = new Map<string, { count: number; resetAt: Date }>();

function checkUserRateLimit(userId: string): boolean {
  const now = new Date();
  const limit = userLimiter.get(userId);

  if (!limit || limit.resetAt < now) {
    userLimiter.set(userId, {
      count: 1,
      resetAt: new Date(now.getTime() + 60000) // 1 minute
    });
    return true;
  }

  if (limit.count >= 20) {
    return false; // Rate limit exceeded
  }

  limit.count++;
  return true;
}
```

---

## Secure Data Storage

### Encryption at Rest

```typescript
import crypto from 'crypto';

const ENCRYPTION_KEY = process.env.ENCRYPTION_KEY!; // 32-byte key
const ALGORITHM = 'aes-256-gcm';

function encrypt(text: string): { encrypted: string; iv: string; tag: string } {
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv(ALGORITHM, Buffer.from(ENCRYPTION_KEY, 'hex'), iv);

  let encrypted = cipher.update(text, 'utf8', 'hex');
  encrypted += cipher.final('hex');

  return {
    encrypted,
    iv: iv.toString('hex'),
    tag: cipher.getAuthTag().toString('hex')
  };
}

function decrypt(encrypted: string, iv: string, tag: string): string {
  const decipher = crypto.createDecipheriv(
    ALGORITHM,
    Buffer.from(ENCRYPTION_KEY, 'hex'),
    Buffer.from(iv, 'hex')
  );

  decipher.setAuthTag(Buffer.from(tag, 'hex'));

  let decrypted = decipher.update(encrypted, 'hex', 'utf8');
  decrypted += decipher.final('utf8');

  return decrypted;
}

// Usage
async function storeConversation(userId: string, message: string) {
  const { encrypted, iv, tag } = encrypt(message);

  await db.conversations.insert({
    userId,
    message: encrypted,
    iv,
    tag,
    createdAt: new Date()
  });
}
```

### Secure Database Connections

```typescript
// PostgreSQL with SSL
import { Pool } from 'pg';

const pool = new Pool({
  host: process.env.DB_HOST,
  database: process.env.DB_NAME,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  ssl: {
    rejectUnauthorized: true,
    ca: fs.readFileSync('/path/to/ca-cert.pem').toString()
  }
});
```

---

## Input Validation & Sanitization

### Prevent Injection Attacks

```typescript
// SQL Injection Prevention
// ❌ BAD: String concatenation
const query = `SELECT * FROM users WHERE email = '${email}'`;

// ✅ GOOD: Parameterized queries
const query = 'SELECT * FROM users WHERE email = $1';
const result = await pool.query(query, [email]);

// XSS Prevention
import DOMPurify from 'isomorphic-dompurify';

function sanitizeHTML(dirty: string): string {
  return DOMPurify.sanitize(dirty);
}

// Usage before rendering user input
const userMessage = sanitizeHTML(input);

// Command Injection Prevention
import { exec } from 'child_process';

// ❌ BAD: Unsanitized input
exec(`process ${userInput}`);

// ✅ GOOD: Validate and escape
import { escapeShellArg } from 'shell-escape';
if (/^[a-zA-Z0-9_-]+$/.test(userInput)) {
  exec(`process ${escapeShellArg(userInput)}`);
}
```

### Input Validation

```typescript
import Joi from 'joi';

const messageSchema = Joi.object({
  text: Joi.string().max(2000).required(),
  userId: Joi.string().uuid().required(),
  timestamp: Joi.date().iso().required()
});

function validateMessage(data: any): { valid: boolean; error?: string } {
  const { error } = messageSchema.validate(data);

  if (error) {
    return { valid: false, error: error.message };
  }

  return { valid: true };
}
```

---

## Audit Logging

```typescript
interface AuditLog {
  timestamp: Date;
  userId: string;
  action: string;
  resource: string;
  changes?: any;
  ipAddress: string;
  userAgent: string;
}

async function logAction(log: AuditLog) {
  await db.auditLogs.insert({
    ...log,
    id: uuidv4()
  });

  // Alert on sensitive actions
  if (['data_export', 'data_deletion', 'admin_access'].includes(log.action)) {
    await slackClient.notify('#security-alerts', {
      message: `🔒 Sensitive action: ${log.action} by ${log.userId}`
    });
  }
}

// Usage
await logAction({
  timestamp: new Date(),
  userId: user.id,
  action: 'export_data',
  resource: 'user_profile',
  ipAddress: req.ip,
  userAgent: req.headers['user-agent']
});
```

---

## Webhook Security

### Signature Verification

```typescript
import crypto from 'crypto';

function verifyWebhookSignature(
  payload: string,
  signature: string,
  secret: string
): boolean {
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');

  // Use timing-safe comparison
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expectedSignature)
  );
}

// Webhook handler
app.post('/webhook', express.raw({ type: 'application/json' }), (req, res) => {
  const signature = req.headers['x-signature'] as string;
  const payload = req.body.toString();

  if (!verifyWebhookSignature(payload, signature, process.env.WEBHOOK_SECRET!)) {
    return res.status(401).send('Invalid signature');
  }

  // Process webhook
  const event = JSON.parse(payload);
  handleWebhookEvent(event);

  res.sendStatus(200);
});
```

---

## HIPAA Compliance (Healthcare)

```typescript
class HIPAACompliance {
  // Encrypt PHI (Protected Health Information)
  async storePHI(data: any) {
    const encrypted = encrypt(JSON.stringify(data));
    await secureDb.store(encrypted);
  }

  // Access logging
  async accessPHI(userId: string, patientId: string, reason: string) {
    await logAction({
      timestamp: new Date(),
      userId,
      action: 'access_phi',
      resource: patientId,
      changes: { reason },
      ipAddress: req.ip,
      userAgent: req.headers['user-agent']
    });

    return await this.retrievePHI(patientId);
  }

  // Minimum necessary standard
  async getMinimumPHI(patientId: string, purpose: string) {
    const full = await this.retrievePHI(patientId);

    switch (purpose) {
      case 'appointment_reminder':
        return { name: full.name, appointmentDate: full.appointmentDate };
      case 'billing':
        return { name: full.name, billing: full.billing };
      default:
        return full;
    }
  }

  // Breach notification
  async detectBreach(incident: SecurityIncident) {
    if (incident.severity === 'critical') {
      // Notify affected individuals within 60 days
      await this.notifyAffectedIndividuals(incident);

      // Notify HHS if >500 individuals affected
      if (incident.affectedCount > 500) {
        await this.notifyHHS(incident);
      }

      // Media notification if >500 in same state
      if (incident.affectedCount > 500) {
        await this.notifyMedia(incident);
      }
    }
  }
}
```

---

## Best Practices Summary

### DO
- ✅ Encrypt sensitive data at rest and in transit (HTTPS)
- ✅ Use parameterized queries to prevent SQL injection
- ✅ Validate and sanitize all user inputs
- ✅ Store API keys in environment variables or secrets manager
- ✅ Implement rate limiting to prevent abuse
- ✅ Log all security-relevant actions
- ✅ Verify webhook signatures
- ✅ Mask PII in logs and analytics
- ✅ Implement GDPR data subject rights
- ✅ Use JWT or session tokens for authentication
- ✅ Set up data retention policies
- ✅ Conduct regular security audits

### DON'T
- ❌ Store passwords in plain text
- ❌ Hardcode API keys or secrets
- ❌ Trust user input without validation
- ❌ Expose detailed error messages to users
- ❌ Log sensitive data unencrypted
- ❌ Skip webhook signature verification
- ❌ Store unnecessary PII
- ❌ Ignore GDPR/privacy regulations
- ❌ Use weak encryption (use AES-256)
- ❌ Allow unlimited API requests
- ❌ Keep data forever without retention policy
- ❌ Deploy without security review
