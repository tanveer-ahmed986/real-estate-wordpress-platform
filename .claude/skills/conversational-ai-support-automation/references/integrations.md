# Integration Patterns

Patterns and code examples for integrating conversational AI with business systems.

---

## Integration Architecture

```
Conversational AI Bot
        ↓
[Integration Layer]
        ↓
    ┌───────┴───────┬──────────┬───────────┐
    ↓               ↓          ↓           ↓
  CRM          Booking    Ticketing    Email/SMS
(Salesforce)  (Calendly)  (Zendesk)   (Twilio)
```

**Key Principles**:
- **Async First** - Don't block conversation on slow APIs
- **Retry Logic** - Handle transient failures
- **Graceful Degradation** - Fallback when integration fails
- **Rate Limiting** - Respect API quotas
- **Error Context** - Give users helpful error messages

---

## CRM Integration

### Salesforce

#### Authentication (OAuth 2.0)

```typescript
import axios from 'axios';

interface SalesforceAuth {
  accessToken: string;
  instanceUrl: string;
  expiresAt: Date;
}

class SalesforceClient {
  private auth: SalesforceAuth | null = null;

  async authenticate() {
    const response = await axios.post(
      'https://login.salesforce.com/services/oauth2/token',
      new URLSearchParams({
        grant_type: 'password',
        client_id: process.env.SF_CLIENT_ID!,
        client_secret: process.env.SF_CLIENT_SECRET!,
        username: process.env.SF_USERNAME!,
        password: process.env.SF_PASSWORD + process.env.SF_SECURITY_TOKEN!
      })
    );

    this.auth = {
      accessToken: response.data.access_token,
      instanceUrl: response.data.instance_url,
      expiresAt: new Date(Date.now() + 2 * 60 * 60 * 1000) // 2 hours
    };
  }

  private async ensureAuthenticated() {
    if (!this.auth || this.auth.expiresAt < new Date()) {
      await this.authenticate();
    }
  }

  async createLead(data: {
    firstName: string;
    lastName: string;
    email: string;
    company: string;
    phone?: string;
  }) {
    await this.ensureAuthenticated();

    const response = await axios.post(
      `${this.auth!.instanceUrl}/services/data/v58.0/sobjects/Lead`,
      {
        FirstName: data.firstName,
        LastName: data.lastName,
        Email: data.email,
        Company: data.company,
        Phone: data.phone,
        LeadSource: 'Chatbot',
        Status: 'Open - Not Contacted'
      },
      {
        headers: {
          Authorization: `Bearer ${this.auth!.accessToken}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return {
      success: response.data.success,
      leadId: response.data.id
    };
  }

  async updateContact(contactId: string, updates: any) {
    await this.ensureAuthenticated();

    await axios.patch(
      `${this.auth!.instanceUrl}/services/data/v58.0/sobjects/Contact/${contactId}`,
      updates,
      {
        headers: {
          Authorization: `Bearer ${this.auth!.accessToken}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return { success: true };
  }

  async searchContact(email: string) {
    await this.ensureAuthenticated();

    const query = `SELECT Id, FirstName, LastName, Email, Phone FROM Contact WHERE Email = '${email}'`;
    const response = await axios.get(
      `${this.auth!.instanceUrl}/services/data/v58.0/query`,
      {
        params: { q: query },
        headers: {
          Authorization: `Bearer ${this.auth!.accessToken}`
        }
      }
    );

    return response.data.records[0] || null;
  }
}
```

---

### HubSpot

```typescript
import axios from 'axios';

class HubSpotClient {
  private apiKey: string;
  private baseUrl = 'https://api.hubapi.com';

  constructor(apiKey: string) {
    this.apiKey = apiKey;
  }

  async createContact(data: {
    email: string;
    firstName?: string;
    lastName?: string;
    phone?: string;
    company?: string;
  }) {
    try {
      const response = await axios.post(
        `${this.baseUrl}/crm/v3/objects/contacts`,
        {
          properties: {
            email: data.email,
            firstname: data.firstName,
            lastname: data.lastName,
            phone: data.phone,
            company: data.company,
            lifecyclestage: 'lead'
          }
        },
        {
          headers: {
            Authorization: `Bearer ${this.apiKey}`,
            'Content-Type': 'application/json'
          }
        }
      );

      return {
        success: true,
        contactId: response.data.id
      };
    } catch (error: any) {
      if (error.response?.status === 409) {
        // Contact already exists
        const existingContact = await this.searchContactByEmail(data.email);
        return {
          success: true,
          contactId: existingContact.id,
          alreadyExists: true
        };
      }
      throw error;
    }
  }

  async searchContactByEmail(email: string) {
    const response = await axios.post(
      `${this.baseUrl}/crm/v3/objects/contacts/search`,
      {
        filterGroups: [{
          filters: [{
            propertyName: 'email',
            operator: 'EQ',
            value: email
          }]
        }]
      },
      {
        headers: {
          Authorization: `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return response.data.results[0] || null;
  }

  async createDeal(data: {
    dealName: string;
    amount: number;
    pipeline: string;
    stage: string;
    contactId?: string;
  }) {
    const response = await axios.post(
      `${this.baseUrl}/crm/v3/objects/deals`,
      {
        properties: {
          dealname: data.dealName,
          amount: data.amount.toString(),
          pipeline: data.pipeline,
          dealstage: data.stage
        },
        associations: data.contactId ? [{
          to: { id: data.contactId },
          types: [{ associationCategory: 'HUBSPOT_DEFINED', associationTypeId: 3 }]
        }] : []
      },
      {
        headers: {
          Authorization: `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return {
      success: true,
      dealId: response.data.id
    };
  }
}
```

---

## Booking Integration

### Calendly

```typescript
import axios from 'axios';

class CalendlyClient {
  private apiToken: string;
  private baseUrl = 'https://api.calendly.com';

  constructor(apiToken: string) {
    this.apiToken = apiToken;
  }

  async getUser() {
    const response = await axios.get(
      `${this.baseUrl}/users/me`,
      {
        headers: {
          Authorization: `Bearer ${this.apiToken}`
        }
      }
    );
    return response.data.resource;
  }

  async getEventTypes() {
    const user = await this.getUser();
    const response = await axios.get(
      `${this.baseUrl}/event_types`,
      {
        params: {
          user: user.uri,
          active: true
        },
        headers: {
          Authorization: `Bearer ${this.apiToken}`
        }
      }
    );

    return response.data.collection.map((event: any) => ({
      uri: event.uri,
      name: event.name,
      duration: event.duration,
      schedulingUrl: event.scheduling_url
    }));
  }

  async createInvitee(eventTypeUri: string, data: {
    email: string;
    name: string;
    startTime: string; // ISO 8601
  }) {
    // Note: Calendly doesn't support direct invitee creation via API
    // You need to use their scheduling page or webhook to capture bookings
    // This is a conceptual example

    const response = await axios.post(
      `${this.baseUrl}/scheduled_events`,
      {
        event_type: eventTypeUri,
        start_time: data.startTime,
        invitees: [{
          email: data.email,
          name: data.name
        }]
      },
      {
        headers: {
          Authorization: `Bearer ${this.apiToken}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return {
      success: true,
      eventUri: response.data.resource.uri,
      schedulingUrl: response.data.resource.uri
    };
  }

  // Alternative: Generate scheduling link
  getSchedulingLink(eventType: string, prefill?: {
    name?: string;
    email?: string;
  }): string {
    const params = new URLSearchParams();
    if (prefill?.name) params.append('name', prefill.name);
    if (prefill?.email) params.append('email', prefill.email);

    return `https://calendly.com/your-org/${eventType}?${params.toString()}`;
  }
}
```

---

### Cal.com (Open Source Alternative)

```typescript
import axios from 'axios';

class CalComClient {
  private apiKey: string;
  private baseUrl: string; // self-hosted or cal.com

  constructor(apiKey: string, baseUrl = 'https://api.cal.com/v1') {
    this.apiKey = apiKey;
    this.baseUrl = baseUrl;
  }

  async getAvailableSlots(params: {
    eventTypeId: number;
    startTime: string;
    endTime: string;
    timeZone: string;
  }) {
    const response = await axios.get(
      `${this.baseUrl}/availability`,
      {
        params: {
          eventTypeId: params.eventTypeId,
          startTime: params.startTime,
          endTime: params.endTime,
          timeZone: params.timeZone
        },
        headers: {
          'x-api-key': this.apiKey
        }
      }
    );

    return response.data.slots;
  }

  async createBooking(data: {
    eventTypeId: number;
    start: string;
    timeZone: string;
    name: string;
    email: string;
    metadata?: Record<string, any>;
  }) {
    const response = await axios.post(
      `${this.baseUrl}/bookings`,
      {
        eventTypeId: data.eventTypeId,
        start: data.start,
        timeZone: data.timeZone,
        responses: {
          name: data.name,
          email: data.email
        },
        metadata: data.metadata || {}
      },
      {
        headers: {
          'x-api-key': this.apiKey,
          'Content-Type': 'application/json'
        }
      }
    );

    return {
      success: true,
      bookingId: response.data.id,
      bookingUid: response.data.uid
    };
  }

  async cancelBooking(bookingUid: string, reason?: string) {
    await axios.delete(
      `${this.baseUrl}/bookings/${bookingUid}`,
      {
        data: { reason },
        headers: {
          'x-api-key': this.apiKey
        }
      }
    );

    return { success: true };
  }
}
```

---

## Ticketing Integration

### Zendesk

```typescript
import axios from 'axios';

class ZendeskClient {
  private subdomain: string;
  private email: string;
  private apiToken: string;
  private baseUrl: string;

  constructor(subdomain: string, email: string, apiToken: string) {
    this.subdomain = subdomain;
    this.email = email;
    this.apiToken = apiToken;
    this.baseUrl = `https://${subdomain}.zendesk.com/api/v2`;
  }

  private getAuth() {
    return Buffer.from(`${this.email}/token:${this.apiToken}`).toString('base64');
  }

  async createTicket(data: {
    subject: string;
    description: string;
    requesterName: string;
    requesterEmail: string;
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    tags?: string[];
  }) {
    const response = await axios.post(
      `${this.baseUrl}/tickets`,
      {
        ticket: {
          subject: data.subject,
          comment: { body: data.description },
          requester: {
            name: data.requesterName,
            email: data.requesterEmail
          },
          priority: data.priority || 'normal',
          tags: data.tags || ['chatbot']
        }
      },
      {
        headers: {
          Authorization: `Basic ${this.getAuth()}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return {
      success: true,
      ticketId: response.data.ticket.id,
      ticketUrl: `https://${this.subdomain}.zendesk.com/agent/tickets/${response.data.ticket.id}`
    };
  }

  async addComment(ticketId: number, comment: string, isPublic = false) {
    await axios.put(
      `${this.baseUrl}/tickets/${ticketId}`,
      {
        ticket: {
          comment: {
            body: comment,
            public: isPublic
          }
        }
      },
      {
        headers: {
          Authorization: `Basic ${this.getAuth()}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return { success: true };
  }

  async getTicket(ticketId: number) {
    const response = await axios.get(
      `${this.baseUrl}/tickets/${ticketId}`,
      {
        headers: {
          Authorization: `Basic ${this.getAuth()}`
        }
      }
    );

    return response.data.ticket;
  }

  async searchTickets(requesterEmail: string) {
    const response = await axios.get(
      `${this.baseUrl}/search`,
      {
        params: {
          query: `type:ticket requester:${requesterEmail}`
        },
        headers: {
          Authorization: `Basic ${this.getAuth()}`
        }
      }
    );

    return response.data.results;
  }
}
```

---

## Communication Channels

### Twilio (SMS/WhatsApp)

```typescript
import twilio from 'twilio';

class TwilioClient {
  private client: any;
  private fromNumber: string;

  constructor(accountSid: string, authToken: string, fromNumber: string) {
    this.client = twilio(accountSid, authToken);
    this.fromNumber = fromNumber;
  }

  async sendSMS(to: string, message: string) {
    try {
      const result = await this.client.messages.create({
        from: this.fromNumber,
        to: to,
        body: message
      });

      return {
        success: true,
        messageId: result.sid
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.message
      };
    }
  }

  async sendWhatsApp(to: string, message: string) {
    // WhatsApp numbers must be prefixed with 'whatsapp:'
    const result = await this.client.messages.create({
      from: `whatsapp:${this.fromNumber}`,
      to: `whatsapp:${to}`,
      body: message
    });

    return {
      success: true,
      messageId: result.sid
    };
  }

  async sendWhatsAppTemplate(to: string, templateSid: string, contentVariables: Record<string, string>) {
    const result = await this.client.messages.create({
      from: `whatsapp:${this.fromNumber}`,
      to: `whatsapp:${to}`,
      contentSid: templateSid,
      contentVariables: JSON.stringify(contentVariables)
    });

    return {
      success: true,
      messageId: result.sid
    };
  }
}
```

---

## Integration Error Handling Pattern

```typescript
class IntegrationClient {
  async executeWithRetry<T>(
    operation: () => Promise<T>,
    maxRetries = 3,
    backoffMs = 1000
  ): Promise<T> {
    let lastError: any;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        return await operation();
      } catch (error: any) {
        lastError = error;

        // Don't retry on client errors (4xx)
        if (error.response?.status >= 400 && error.response?.status < 500) {
          throw error;
        }

        // Don't retry on last attempt
        if (attempt === maxRetries) {
          break;
        }

        // Exponential backoff
        const delay = backoffMs * Math.pow(2, attempt - 1);
        await new Promise(resolve => setTimeout(resolve, delay));
      }
    }

    throw lastError;
  }

  async safeExecute<T>(
    operation: () => Promise<T>,
    fallback: T
  ): Promise<{ success: boolean; data?: T; error?: string }> {
    try {
      const result = await this.executeWithRetry(operation);
      return { success: true, data: result };
    } catch (error: any) {
      console.error('Integration error:', error);
      return {
        success: false,
        error: error.message,
        data: fallback
      };
    }
  }
}

// Usage in conversation
async function handleBooking(userInput: any) {
  const integration = new IntegrationClient();

  const result = await integration.safeExecute(
    async () => {
      return await calendlyClient.createBooking(userInput);
    },
    null // fallback value
  );

  if (result.success) {
    return "✓ Booking confirmed! You'll receive a confirmation email shortly.";
  } else {
    return "I'm having trouble with the booking system right now. Let me create a ticket for our team to follow up with you.";
  }
}
```

---

## Rate Limiting Pattern

```typescript
class RateLimiter {
  private requests: Map<string, number[]> = new Map();

  async checkLimit(key: string, limit: number, windowMs: number): Promise<boolean> {
    const now = Date.now();
    const timestamps = this.requests.get(key) || [];

    // Remove expired timestamps
    const validTimestamps = timestamps.filter(t => now - t < windowMs);

    if (validTimestamps.length >= limit) {
      return false; // Rate limit exceeded
    }

    validTimestamps.push(now);
    this.requests.set(key, validTimestamps);
    return true;
  }

  async executeWithLimit<T>(
    key: string,
    operation: () => Promise<T>,
    limit = 10,
    windowMs = 60000 // 1 minute
  ): Promise<T> {
    const allowed = await this.checkLimit(key, limit, windowMs);

    if (!allowed) {
      throw new Error('Rate limit exceeded. Please try again later.');
    }

    return operation();
  }
}

// Usage
const rateLimiter = new RateLimiter();

async function createLead(data: any) {
  return rateLimiter.executeWithLimit(
    'crm:create_lead',
    () => crmClient.createLead(data),
    100,  // 100 requests
    60000 // per minute
  );
}
```

---

## Webhook Handling Pattern

```typescript
import express from 'express';
import crypto from 'crypto';

const app = express();

// Verify webhook signature (Stripe example)
function verifyWebhookSignature(payload: string, signature: string, secret: string): boolean {
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expectedSignature)
  );
}

// Webhook endpoint
app.post('/webhooks/calendly', express.raw({ type: 'application/json' }), async (req, res) => {
  const signature = req.headers['calendly-webhook-signature'] as string;
  const payload = req.body.toString();

  // Verify signature
  if (!verifyWebhookSignature(payload, signature, process.env.CALENDLY_WEBHOOK_SECRET!)) {
    return res.status(401).send('Invalid signature');
  }

  const event = JSON.parse(payload);

  // Handle different event types
  switch (event.event) {
    case 'invitee.created':
      await handleBookingCreated(event.payload);
      break;
    case 'invitee.canceled':
      await handleBookingCanceled(event.payload);
      break;
  }

  res.sendStatus(200);
});

async function handleBookingCreated(data: any) {
  // Create lead in CRM
  await crmClient.createLead({
    firstName: data.first_name,
    lastName: data.last_name,
    email: data.email,
    source: 'Calendly - Chatbot'
  });

  // Send confirmation SMS
  await twilioClient.sendSMS(
    data.phone,
    `Hi ${data.first_name}, your appointment is confirmed for ${data.start_time}!`
  );
}
```

---

## Best Practices Summary

### DO
- ✅ Use async/await for all API calls
- ✅ Implement retry logic with exponential backoff
- ✅ Add rate limiting to respect API quotas
- ✅ Validate webhook signatures
- ✅ Store API credentials in environment variables
- ✅ Log all integration errors for debugging
- ✅ Provide user-friendly error messages
- ✅ Test integrations in sandbox/staging first

### DON'T
- ❌ Block conversation on slow API calls (use async)
- ❌ Expose API error details to users
- ❌ Hardcode API credentials
- ❌ Retry indefinitely (set max retry limit)
- ❌ Ignore rate limits (will get banned)
- ❌ Trust webhook data without signature verification
- ❌ Skip error handling
- ❌ Test integrations directly in production
