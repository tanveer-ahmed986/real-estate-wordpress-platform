---
name: conversational-ai-support-automation
description: |
  Build production-grade conversational AI systems and support automation workflows.
  Replace customer support and sales agents with AI chatbots, sales assistants,
  lead qualification systems, booking automation, multichannel communication, and
  customer lifecycle workflows. Use when users ask to create chatbots, build AI
  assistants, automate customer support, design conversation flows, integrate CRM
  systems, set up booking automation, deploy multichannel bots, or orchestrate
  customer lifecycle automation.
---

# Conversational AI & Support Automation

Build production-grade conversational AI systems that replace customer support and sales agents with intelligent automation.

## What This Skill Does

- **Creates AI Chatbots & Sales Assistants** - Build conversational interfaces using traditional frameworks or modern LLM-powered approaches
- **Implements Lead Qualification & Booking** - Automate lead capture, qualification scoring, and appointment scheduling
- **Deploys Multichannel Communication** - Deploy bots across web, WhatsApp, Slack, Discord, voice, and other channels
- **Orchestrates Customer Lifecycle Automation** - Build end-to-end workflows from lead capture → qualification → nurture → conversion → support
- **Integrates Business Systems** - Connect to CRM (Salesforce, HubSpot), ticketing (Zendesk), booking (Calendly), and custom APIs
- **Ensures Security & Compliance** - Implement GDPR-compliant data handling, secure authentication, and privacy controls

## What This Skill Does NOT Do

- Build native mobile apps (focuses on conversational interfaces)
- Replace all human agents without oversight (provides automation with human-in-the-loop)
- Guarantee regulatory compliance without legal review (provides best practices, not legal advice)
- Handle real-time voice transcription infrastructure (integrates with existing services)
- Manage training data labeling (focuses on implementation)

---

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing chatbot code, API integrations, database schemas, authentication patterns |
| **Conversation** | User's specific use case (support/sales/both), target channels, existing systems to integrate |
| **Skill References** | Conversation design patterns, framework comparisons, integration guides, security practices from `references/` |
| **User Guidelines** | Company's tone/voice, compliance requirements, escalation policies, SLAs |

**Critical**: Only ask user for THEIR specific requirements (framework preference, CRM choice, channels). Domain expertise (conversation design, integration patterns, security) is embedded in this skill.

---

## Required Clarifications

Ask about the user's specific context before implementation:

### 1. Primary Use Case
**Question**: "What's your primary goal for this conversational AI system?"

**Options**:
- **Customer Support Automation** - Handle inquiries, troubleshooting, FAQs, ticket creation
- **Sales Assistant** - Product recommendations, lead capture, qualification, handoff to sales
- **Lead Qualification & Booking** - Qualify leads, schedule appointments, route to appropriate team
- **Full Customer Lifecycle** - End-to-end from first contact through support
- **Other** - Describe specific use case

### 2. Implementation Approach
**Question**: "What approach do you want to use?"

**Options**:
- **LLM-Powered** (OpenAI, Anthropic, etc.) - Modern, flexible, natural conversations with function calling
- **Traditional Framework** (Rasa, Dialogflow, Botpress) - Structured intents/entities, deterministic flows
- **Hybrid** - LLM for understanding + structured workflows for actions
- **Not sure** - I'll recommend based on use case

### 3. Integration Requirements
**Question**: "Which systems need integration?"

**Common integrations**:
- CRM: Salesforce, HubSpot, Pipedrive, Zoho, custom
- Ticketing: Zendesk, Freshdesk, Jira Service Desk, Intercom
- Booking: Calendly, Cal.com, Acuity, Microsoft Bookings, custom
- Communication: Email (SendGrid, Mailgun), SMS (Twilio), notifications
- Analytics: Mixpanel, Amplitude, custom dashboards
- Other: Specify APIs/webhooks needed

### 4. Channel Deployment
**Question**: "Where will users interact with the bot?"

**Channels**:
- Web widget (embedded on website)
- WhatsApp Business API
- Slack
- Discord
- Facebook Messenger
- Voice (Twilio, Amazon Connect)
- SMS
- Multiple channels

### 5. Existing Resources
**Question**: "Do you have existing resources?"

- Existing bot/codebase to enhance
- Conversation flow documents
- Training data or conversation logs
- API documentation for integrations
- Brand guidelines and sample responses
- None (starting from scratch)

### 6. Compliance & Security
**Question**: "Any specific compliance or security requirements?"

- GDPR (EU data privacy)
- HIPAA (healthcare data)
- PCI DSS (payment data)
- SOC 2
- Custom security policies
- Standard best practices

---

## Workflow

### Phase 1: Design Conversation Flow

1. **Define User Journeys**
   - Map conversation paths for each use case
   - Identify decision points and branching logic
   - Plan fallback and error handling paths
   - Design escalation to human agents

2. **Create Intent Structure**
   - Define core intents (greetings, help, FAQs, booking, escalation)
   - Map entities needed (names, dates, product IDs, emails)
   - Structure slot-filling sequences for data collection
   - Plan context/memory management

3. **Design Response Templates**
   - Write response variations for natural conversation
   - Define quick replies and suggested actions
   - Create rich media responses (cards, buttons, images)
   - Ensure brand voice consistency

**Reference**: See `references/conversation-design.md` for detailed patterns

---

### Phase 2: Choose & Configure Framework

**For LLM-Powered Approach**:

1. **Set up LLM Integration**
   - Configure OpenAI/Anthropic/other LLM provider
   - Implement function calling for actions (book, create ticket, search)
   - Design system prompts with role, constraints, examples
   - Set up conversation memory (short-term + long-term)

2. **Implement Function Tools**
   - Create functions for booking, CRM updates, ticket creation
   - Add input validation and error handling
   - Implement rate limiting and retry logic
   - Test function calling flows

**For Traditional Framework Approach**:

1. **Configure Framework**
   - Set up Rasa/Dialogflow/Botpress project
   - Define domain (intents, entities, actions)
   - Configure NLU pipeline
   - Set up dialogue management policies

2. **Train NLU Model**
   - Provide training examples for each intent
   - Define entity extraction patterns
   - Configure confidence thresholds
   - Test and iterate on accuracy

**For Hybrid Approach**:

1. **Combine LLM + Structured Workflows**
   - Use LLM for natural language understanding
   - Route to structured workflows for actions
   - Implement state management
   - Handle transitions between LLM and structured flows

**Reference**: See `references/frameworks.md` for framework comparison and setup guides

---

### Phase 3: Implement Integrations

1. **CRM Integration**
   - Authenticate with CRM API (OAuth, API keys)
   - Implement contact creation/update
   - Add lead scoring and qualification logic
   - Set up field mapping and validation
   - Handle rate limits and pagination

2. **Booking System Integration**
   - Connect to booking API
   - Fetch available time slots
   - Create/update/cancel appointments
   - Send confirmation notifications
   - Handle timezone conversions

3. **Ticketing System Integration**
   - Create tickets from conversations
   - Update ticket status
   - Fetch ticket information
   - Add conversation transcripts
   - Handle attachments

4. **Communication Channels**
   - Set up webhooks for incoming messages
   - Implement outbound messaging
   - Handle rich media (images, files, buttons)
   - Manage session state across channels

**Reference**: See `references/integrations.md` for integration patterns and code examples

---

### Phase 4: Deploy Multichannel

1. **Set Up Channel Adapters**
   - Configure webhooks for each channel
   - Implement channel-specific formatting
   - Handle authentication/verification
   - Test message delivery

2. **Implement Channel-Specific Features**
   - Quick replies (web, WhatsApp, Messenger)
   - Buttons and cards (web, Slack, Discord)
   - Voice prompts (Twilio, Amazon Connect)
   - Rich media handling per channel

3. **Unify Channel Experience**
   - Maintain conversation context across channels
   - Ensure consistent responses
   - Handle channel switching
   - Implement fallbacks for unsupported features

**Reference**: See `references/channels.md` for multichannel deployment guides

---

### Phase 5: Build Customer Lifecycle Automation

1. **Lead Capture Flow**
   - Implement initial engagement (greeting, value prop)
   - Collect contact information with validation
   - Create lead in CRM with source tracking
   - Send welcome sequence

2. **Lead Qualification Flow**
   - Ask qualifying questions (budget, timeline, authority, need)
   - Calculate lead score based on responses
   - Route high-value leads to sales
   - Nurture low-score leads with content

3. **Appointment Booking Flow**
   - Check calendar availability
   - Offer time slots with timezone handling
   - Collect additional context/requirements
   - Send confirmation and reminders
   - Handle rescheduling/cancellation

4. **Customer Support Flow**
   - Route by issue type (technical, billing, general)
   - Attempt self-service resolution (KB search, FAQs)
   - Escalate to human agent with context
   - Create ticket with conversation transcript
   - Follow up on resolution

5. **Post-Interaction Flow**
   - Send conversation summary
   - Request feedback/NPS score
   - Update CRM with interaction data
   - Trigger nurture campaigns
   - Schedule follow-up if needed

**Reference**: See `references/lifecycle-automation.md` for workflow patterns

---

### Phase 6: Implement Security & Compliance

1. **Data Privacy**
   - Implement PII detection and masking
   - Add data retention policies
   - Enable user data deletion (GDPR right to erasure)
   - Encrypt sensitive data at rest and in transit
   - Log consent for data processing

2. **Authentication & Authorization**
   - Implement user authentication (if required)
   - Add role-based access control
   - Secure API keys and credentials (use environment variables, secrets management)
   - Implement session management
   - Add rate limiting per user/IP

3. **Audit & Monitoring**
   - Log all conversations (with PII masking)
   - Track data access and modifications
   - Monitor for suspicious activity
   - Implement alerting for security events
   - Regular security audits

**Reference**: See `references/security-compliance.md` for security patterns

---

### Phase 7: Add Analytics & Optimization

1. **Track Key Metrics**
   - Conversation completion rate
   - Resolution rate (automated vs escalated)
   - Average handling time
   - User satisfaction (CSAT, NPS)
   - Intent recognition accuracy
   - Conversion rate (for sales bots)

2. **Implement Analytics**
   - Set up event tracking
   - Create dashboards for key metrics
   - Monitor conversation funnels
   - Track integration performance
   - Analyze fallback triggers

3. **Optimize Performance**
   - Review failed conversations
   - Improve intent recognition with new training data
   - Refine response templates based on feedback
   - A/B test conversation flows
   - Optimize integration latency

**Reference**: See `references/analytics.md` for analytics patterns

---

## Domain Standards

### Must Follow

- [ ] **Natural Conversation Flow** - Avoid robotic responses, use variations, maintain context
- [ ] **Clear Error Handling** - Graceful fallbacks, helpful error messages, escalation paths
- [ ] **User Control** - Easy escalation to humans, conversation reset, data deletion
- [ ] **Accessibility** - Screen reader compatible, keyboard navigation (web), clear language
- [ ] **Privacy by Design** - Minimal data collection, explicit consent, secure storage
- [ ] **Response Time** - <2s for acknowledgment, <5s for response, show typing indicators
- [ ] **Context Preservation** - Remember conversation history, avoid re-asking questions
- [ ] **Secure Authentication** - Never store API keys in code, use environment variables/secrets management
- [ ] **Input Validation** - Validate all user inputs, sanitize before database/API calls
- [ ] **Monitoring** - Log conversations (with PII masking), track errors, monitor integrations

### Must Avoid

- Hardcoded API keys or credentials
- Storing PII without encryption
- Infinite loops without escape (always provide escalation)
- Single-intent assumptions (allow conversation changes)
- Channel-specific code in core logic (use adapters)
- Synchronous blocking calls (use async for integrations)
- Overly complex flows without testing
- No fallback for low-confidence responses
- Missing rate limiting on expensive operations
- Verbose error messages exposing system details

---

## Output Specification

### For LLM-Powered Implementation

**Directory Structure**:
```
project/
├── src/
│   ├── llm/
│   │   ├── client.ts           # LLM provider client
│   │   ├── prompts.ts          # System prompts and templates
│   │   └── functions.ts        # Function calling definitions
│   ├── integrations/
│   │   ├── crm.ts              # CRM integration
│   │   ├── booking.ts          # Booking integration
│   │   └── ticketing.ts        # Ticketing integration
│   ├── channels/
│   │   ├── web.ts              # Web widget adapter
│   │   ├── whatsapp.ts         # WhatsApp adapter
│   │   └── slack.ts            # Slack adapter
│   ├── memory/
│   │   └── conversation.ts     # Conversation memory management
│   ├── security/
│   │   ├── auth.ts             # Authentication
│   │   └── privacy.ts          # PII handling
│   └── main.ts                 # Entry point
├── config/
│   └── config.yaml             # Configuration
├── tests/
└── README.md                   # Setup and usage docs
```

### For Traditional Framework Implementation

**Directory Structure** (example: Rasa):
```
project/
├── data/
│   ├── nlu.yml                 # Training data
│   ├── rules.yml               # Conversation rules
│   └── stories.yml             # Conversation flows
├── actions/
│   ├── actions.py              # Custom actions
│   ├── crm.py                  # CRM integration
│   └── booking.py              # Booking integration
├── domain.yml                  # Intents, entities, responses
├── config.yml                  # NLU pipeline config
├── credentials.yml             # Channel credentials
├── endpoints.yml               # Action server config
└── README.md
```

### Required Documentation

**README.md must include**:
- Setup instructions (dependencies, environment variables)
- Configuration guide (API keys, webhooks)
- Testing instructions
- Deployment guide
- Security best practices
- Troubleshooting common issues

**Environment Variables Template** (`.env.example`):
```
# LLM Provider (if applicable)
OPENAI_API_KEY=your_key_here
ANTHROPIC_API_KEY=your_key_here

# CRM
SALESFORCE_CLIENT_ID=
SALESFORCE_CLIENT_SECRET=
HUBSPOT_API_KEY=

# Booking
CALENDLY_API_KEY=

# Channels
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
SLACK_BOT_TOKEN=
SLACK_SIGNING_SECRET=

# Database
DATABASE_URL=

# Security
JWT_SECRET=
ENCRYPTION_KEY=
```

---

## Output Checklist

Before delivering, verify ALL items:

### Functional
- [ ] Core conversation flow works end-to-end
- [ ] All required intents/functions implemented
- [ ] Integrations tested with real APIs (or mocked)
- [ ] Error states handled gracefully
- [ ] Escalation to human works
- [ ] Context preserved across conversation
- [ ] Multi-turn conversations work correctly

### Integrations
- [ ] CRM: Contact creation/update works
- [ ] Booking: Appointments created successfully
- [ ] Ticketing: Tickets created with context
- [ ] Channels: Messages sent/received on all channels
- [ ] All APIs handle rate limits and retries

### Security
- [ ] No API keys hardcoded
- [ ] Environment variables documented
- [ ] PII masked in logs
- [ ] Input validation on all user inputs
- [ ] Authentication implemented (if required)
- [ ] HTTPS enforced for webhooks

### Quality
- [ ] Response times acceptable (<5s)
- [ ] Natural conversation flow (not robotic)
- [ ] Consistent brand voice
- [ ] Error messages helpful and user-friendly
- [ ] Code follows project conventions
- [ ] Comprehensive comments on complex logic

### Documentation
- [ ] README with setup instructions
- [ ] Environment variables documented
- [ ] API integration guide included
- [ ] Testing instructions provided
- [ ] Deployment guide included
- [ ] Conversation flow diagrams (if complex)

### Testing
- [ ] Happy path tested
- [ ] Error cases tested (API failures, invalid inputs)
- [ ] Edge cases covered (empty inputs, very long messages)
- [ ] Integration tests for each API
- [ ] Channel-specific features tested

---

## Reference Files

| File | When to Read |
|------|--------------|
| `references/conversation-design.md` | When designing conversation flows, intents, entities, responses |
| `references/frameworks.md` | When choosing framework or setting up LLM/traditional/hybrid approach |
| `references/integrations.md` | When integrating CRM, booking, ticketing, or other systems |
| `references/channels.md` | When deploying to web, WhatsApp, Slack, or other channels |
| `references/lifecycle-automation.md` | When building end-to-end customer lifecycle workflows |
| `references/security-compliance.md` | When implementing authentication, data privacy, or compliance requirements |
| `references/analytics.md` | When adding metrics, dashboards, or optimization |

---

## Templates

| Asset | Use For |
|-------|---------|
| `assets/templates/conversation-flow.json` | Template for conversation flow structure |
| `assets/templates/intent-schema.json` | Template for intent/entity definitions |
| `assets/templates/integration-config.json` | Template for integration configurations |
| `assets/templates/llm-system-prompt.txt` | Template for LLM system prompts |
