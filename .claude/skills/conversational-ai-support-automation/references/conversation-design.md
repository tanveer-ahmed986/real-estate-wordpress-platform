# Conversation Design Patterns

Best practices for designing natural, effective conversational experiences.

---

## Core Principles

### 1. Natural Language First

**Good Conversation**:
```
Bot: Hi! I'm here to help with your order. What can I do for you today?
User: I haven't received my package yet
Bot: I'm sorry to hear that. Let me look into this for you. Could you share your order number?
User: #12345
Bot: Thanks! I found order #12345. It was shipped on March 1st and should arrive by March 5th.
     Would you like me to check the current tracking status?
```

**Bad Conversation** (robotic, rigid):
```
Bot: Welcome. Please select: [1] Track Order [2] Return [3] Support
User: I haven't received my package yet
Bot: Invalid input. Please select 1, 2, or 3.
```

**Principles**:
- Allow free-form input, not just button clicks
- Understand intent regardless of phrasing
- Respond conversationally, not like a menu system
- Show empathy and personality

---

### 2. Progressive Disclosure

Don't overwhelm with options. Guide step-by-step.

**Good** (focused):
```
Bot: I can help you book a demo. When would work best for you?
     [This week] [Next week] [Later]
```

**Bad** (overwhelming):
```
Bot: To book a demo, I need:
     - Preferred date
     - Preferred time
     - Team size
     - Use case
     - Budget
     - Current tools
     Please provide all information.
```

**Pattern**: Ask for one thing at a time, unless user provides everything upfront.

---

### 3. Context Preservation

Remember what was discussed, don't re-ask.

**Good**:
```
User: I want to book a demo for next Tuesday
Bot: Great! What time works for you on Tuesday?
User: Actually, can we do Wednesday instead?
Bot: Sure, Wednesday works. What time on Wednesday?
```

**Bad**:
```
User: I want to book a demo for next Tuesday
Bot: Great! What day would you like?
User: Tuesday
Bot: And what week?
```

**Implementation**:
- Store conversation context (slots, entities)
- Reference previous messages naturally
- Allow users to change their mind
- Clear context when topic changes

---

## Intent Design

### Intent Hierarchy

```
Greeting
├── greeting.hello
├── greeting.goodbye
└── greeting.how_are_you

Booking
├── booking.schedule
├── booking.reschedule
├── booking.cancel
└── booking.check_availability

Support
├── support.track_order
├── support.return_item
├── support.complaint
└── support.general_inquiry

Lead_Qualification
├── qualification.budget
├── qualification.timeline
├── qualification.use_case
└── qualification.decision_maker
```

**Best Practices**:
- Keep intents focused (one user goal per intent)
- Use hierarchical structure for organization
- Avoid overlapping intents (confuses NLU)
- Test with real user phrasings

---

### Training Examples

**Intent**: `booking.schedule`

**Good Training Examples** (diverse, natural):
```
- I'd like to book a meeting
- Can we schedule a demo?
- When are you available for a call?
- Let's set up a time to chat
- Book an appointment for me
- I want to schedule a consultation
- What times do you have open?
- Can I get on your calendar?
```

**Bad Training Examples** (too similar):
```
- I want to book a meeting
- I want to book an appointment
- I want to book a demo
- I want to book a call
```

**Guidelines**:
- 10-20 examples per intent minimum
- Vary sentence structure
- Include typos and informal language
- Cover edge cases ("asap", "whenever", "not sure")

---

## Entity Extraction

### Common Entities

| Entity Type | Examples | Pattern |
|-------------|----------|---------|
| **Date/Time** | "tomorrow", "March 5th", "next Tuesday 2pm" | Use date parsing libraries |
| **Email** | "john@example.com" | Regex: `[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}` |
| **Phone** | "+1 555-123-4567", "(555) 123-4567" | Regex or library (libphonenumber) |
| **Order Number** | "#12345", "ORD-2024-001" | Regex or pattern matching |
| **Product Name** | "iPhone 15", "Premium Plan" | Entity list or fuzzy matching |
| **Budget** | "$5000", "5k", "five thousand dollars" | Number extraction + normalization |

### Slot Filling Pattern

**Conversation**:
```
Bot: I can help you book a demo. What's your name?
User: John Smith
Bot: Nice to meet you, John! What's your email address?
User: john@example.com
Bot: Great! When would you like to meet?
User: Next Tuesday at 2pm
Bot: Perfect! I'll send a calendar invite to john@example.com for Tuesday, March 5th at 2:00 PM.
     Does that work?
User: Yes
Bot: ✓ Demo booked! You'll receive a confirmation email shortly.
```

**Implementation**:
```typescript
interface BookingSlots {
  name?: string;
  email?: string;
  date?: Date;
  time?: string;
  confirmed?: boolean;
}

// Check which slots are filled
function getNextQuestion(slots: BookingSlots): string {
  if (!slots.name) return "What's your name?";
  if (!slots.email) return "What's your email address?";
  if (!slots.date) return "When would you like to meet?";
  if (!slots.confirmed) return confirmationMessage(slots);
  return null; // All done
}
```

---

## Response Design

### Variation and Personality

**Bad** (repetitive):
```
Bot: Thank you
Bot: Thank you
Bot: Thank you
```

**Good** (varied):
```
Bot: Thanks!
Bot: Got it, appreciate it.
Bot: Perfect, thank you.
```

**Implementation**:
```typescript
const acknowledgments = [
  "Thanks!",
  "Got it!",
  "Perfect.",
  "Awesome, thank you.",
  "Great, thanks!"
];

function getAcknowledgment(): string {
  return acknowledgments[Math.floor(Math.random() * acknowledgments.length)];
}
```

---

### Rich Media Responses

**Text Only**:
```
Bot: Our plans are: Basic ($10/mo), Pro ($50/mo), Enterprise (custom pricing)
```

**Rich Media** (better):
```
Bot: Here are our plans:

[Card: Basic]
$10/month
- 10 users
- 100GB storage
- Email support
[Select]

[Card: Pro]
$50/month
- Unlimited users
- 1TB storage
- Priority support
[Select] ← Most Popular

[Card: Enterprise]
Custom pricing
- Everything in Pro
- Dedicated support
- Custom integrations
[Contact Sales]
```

**Channel Considerations**:
- Web: Cards, buttons, carousels supported
- WhatsApp: Buttons (3 max), lists supported
- SMS: Text only, use numbered lists
- Slack: Blocks, buttons, select menus supported

---

## Error Handling

### Low Confidence Response

**Pattern**:
```
Bot: I'm not sure I understood. Did you mean:
     [Track an order]
     [Return an item]
     [Something else]
```

**Implementation**:
```typescript
if (confidence < 0.6) {
  // Offer top 2-3 likely intents
  showDisambiguation(topIntents.slice(0, 3));
} else if (confidence < 0.8) {
  // Proceed but confirm
  respond(intent);
  ask("Is that what you were looking for?");
} else {
  // High confidence, proceed
  respond(intent);
}
```

---

### Fallback Strategy

**Levels**:

1. **First fallback** (gentle):
   ```
   Bot: I didn't quite get that. Could you rephrase?
   ```

2. **Second fallback** (offer options):
   ```
   Bot: I'm still not sure what you need. I can help with:
        - Tracking orders
        - Processing returns
        - General questions
        What would you like to do?
   ```

3. **Third fallback** (escalate):
   ```
   Bot: I'm having trouble understanding. Let me connect you with a team member who can help.
        [Connect to Agent] [Try Again] [End Chat]
   ```

**Implementation**:
```typescript
let fallbackCount = 0;

function handleFallback() {
  fallbackCount++;

  if (fallbackCount === 1) {
    return "I didn't quite get that. Could you rephrase?";
  } else if (fallbackCount === 2) {
    return showOptions();
  } else {
    return escalateToHuman();
  }
}

// Reset on successful intent
function onIntentRecognized() {
  fallbackCount = 0;
}
```

---

## Conversation Flows

### Linear Flow (Simple)

```
Start → Greeting → Collect Info → Confirm → Complete → Goodbye
```

**Example**: Appointment booking with all required fields.

---

### Branching Flow (Conditional)

```
Start → Greeting → Identify Intent
                       ├→ Track Order → Get Order # → Show Status → Done
                       ├→ Return Item → Get Order # → Check Eligibility
                       │                                ├→ Eligible → Process Return
                       │                                └→ Not Eligible → Explain + Offer Alternatives
                       └→ General Question → Search KB → Provide Answer → Done
```

---

### State Machine Flow (Complex)

```
[Idle]
  ↓ user message
[Understanding] → NLU processing
  ↓ intent recognized
[Collecting] → Slot filling
  ↓ all slots filled
[Confirming] → User confirmation
  ├→ confirmed → [Executing] → Action (API call)
  │                  ↓ success
  │              [Complete] → [Idle]
  └→ rejected → [Collecting] (re-ask)

From any state:
  - "cancel" → [Idle]
  - "help" → [Help] → return to previous state
  - low confidence → [Fallback] → [Understanding]
```

**Implementation**:
```typescript
enum ConversationState {
  IDLE,
  UNDERSTANDING,
  COLLECTING,
  CONFIRMING,
  EXECUTING,
  COMPLETE,
  FALLBACK,
  HELP
}

interface Context {
  state: ConversationState;
  intent?: string;
  slots: Record<string, any>;
  previousState?: ConversationState;
}

function handleMessage(message: string, context: Context): Response {
  // Global interruptions
  if (message.toLowerCase() === 'cancel') {
    context.state = ConversationState.IDLE;
    return { text: "Cancelled. How can I help you?" };
  }

  switch (context.state) {
    case ConversationState.IDLE:
      return handleIdle(message, context);
    case ConversationState.COLLECTING:
      return collectSlots(message, context);
    // ... other states
  }
}
```

---

## Context Management

### Short-Term Context (Current Conversation)

**Store**:
- Current intent
- Collected entities/slots
- Last few messages
- Current state
- Fallback count

**Duration**: Current session only

---

### Long-Term Context (User Memory)

**Store**:
- User profile (name, email, preferences)
- Previous intents/interactions
- Purchase history
- Conversation summaries
- Preferences learned over time

**Duration**: Persistent across sessions

**Example**:
```
// First conversation (March 1)
User: I want to book a demo
Bot: Great! What's your name?
User: John Smith
Bot: What's your email?
User: john@example.com

// Second conversation (March 5)
User: Hi
Bot: Welcome back, John! How can I help you today?
User: I want to reschedule my demo
Bot: No problem. Your current demo is scheduled for March 10 at 2pm.
     When would you like to reschedule to?
```

**Implementation**:
```typescript
interface UserProfile {
  userId: string;
  name?: string;
  email?: string;
  phone?: string;
  preferences: Record<string, any>;
  conversationHistory: ConversationSummary[];
}

interface ConversationSummary {
  date: Date;
  intents: string[];
  outcome: string;
  sentiment: 'positive' | 'neutral' | 'negative';
}

// Retrieve user context
async function getUserContext(userId: string): Promise<UserProfile> {
  return await database.getUserProfile(userId);
}

// Update user context
async function updateUserContext(userId: string, updates: Partial<UserProfile>) {
  await database.updateUserProfile(userId, updates);
}
```

---

## Multi-Turn Conversations

### Pattern: Iterative Refinement

```
User: I'm looking for a laptop
Bot: Great! What's your budget?
User: Around $1000
Bot: Perfect. What will you mainly use it for?
User: Video editing
Bot: For video editing at $1000, I'd recommend:
     - MacBook Air M2 ($999) - Great performance
     - Dell XPS 15 ($1050) - Larger screen
     Which interests you more?
User: The MacBook
Bot: Excellent choice! The MacBook Air M2 is perfect for video editing.
     Would you like me to add it to your cart?
```

**Implementation**: Maintain conversation context, progressively narrow down options.

---

### Pattern: Confirmation Loops

**Always confirm before taking action**:

```
User: Cancel my subscription
Bot: I can help with that. Just to confirm, you want to cancel your Pro subscription ($50/mo)?
User: Yes
Bot: ✓ Subscription cancelled. You'll have access until March 31st.
     We'd love to know why you're canceling - would you mind sharing? [Optional]
```

**Never**:
```
User: Cancel my subscription
Bot: ✓ Done! Subscription cancelled.
```

---

## Escalation Patterns

### When to Escalate

1. **User Requests** - "I want to speak to a human", "transfer me", "agent please"
2. **Repeated Failures** - 3+ fallbacks in a row
3. **Complex Issues** - Detected keywords ("angry", "frustrated", "complaint", "legal")
4. **High-Value** - VIP customers or large deals
5. **Out of Scope** - Request beyond bot capabilities

---

### Escalation Flow

```
User: [triggers escalation]
Bot: I'll connect you with a team member who can help better.
     While I find someone available, can you briefly describe your issue?
User: [describes issue]
Bot: Thanks! I'm searching for an available agent...
     [Transfer with context: conversation history, user profile, issue summary]

Agent receives:
- Full conversation transcript
- User profile
- Issue category
- Urgency level
```

**Implementation**:
```typescript
async function escalateToHuman(context: Context, reason: string) {
  const summary = {
    userId: context.userId,
    conversationTranscript: context.messages,
    detectedIntent: context.intent,
    collectedData: context.slots,
    escalationReason: reason,
    urgency: calculateUrgency(context)
  };

  // Find available agent
  const agent = await findAvailableAgent(context.skillRequired);

  if (agent) {
    await transferToAgent(agent, summary);
    return "Connecting you now...";
  } else {
    await createTicket(summary);
    return "All agents are busy. I've created a ticket and someone will reach out within 1 hour.";
  }
}
```

---

## Best Practices Summary

### DO
- ✅ Allow free-form natural language input
- ✅ Maintain conversation context
- ✅ Provide clear error messages with recovery options
- ✅ Confirm before taking destructive actions
- ✅ Show empathy and personality
- ✅ Offer easy escalation to humans
- ✅ Use varied responses to avoid repetition
- ✅ Progressively disclose information
- ✅ Test with real users and iterate

### DON'T
- ❌ Force rigid menu navigation
- ❌ Re-ask questions already answered
- ❌ Leave users stuck (always provide escape)
- ❌ Use jargon or technical terms
- ❌ Make assumptions without confirmation
- ❌ Hide escalation options
- ❌ Repeat exact same responses
- ❌ Overwhelm with too many options at once
- ❌ Launch without testing real conversations
