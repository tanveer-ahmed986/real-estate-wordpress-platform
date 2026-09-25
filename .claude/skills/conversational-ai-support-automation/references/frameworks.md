# Framework Comparison & Setup

Guide to choosing and implementing conversational AI frameworks.

---

## Framework Decision Matrix

| Approach | Best For | Complexity | Cost | Flexibility |
|----------|----------|------------|------|-------------|
| **LLM-Powered** | Natural conversations, complex understanding, rapid prototyping | Low (API-based) | Medium-High (per token) | Very High |
| **Traditional (Rasa, Dialogflow)** | Structured workflows, deterministic responses, compliance-heavy | Medium-High (training required) | Low (self-hosted) | Medium |
| **Hybrid** | Best of both worlds, structured actions + natural understanding | High | Medium | High |
| **No-Code (Voiceflow, Botpress Cloud)** | Quick MVP, non-technical teams | Very Low | Low-Medium (SaaS) | Low |

---

## LLM-Powered Approach

### When to Use

✅ **Use LLM-powered when**:
- Need natural, flexible conversations
- Requirements change frequently
- Building quickly with small team
- Complex understanding required (extracting intent from messy input)
- Want function calling for integrations

❌ **Avoid LLM-powered when**:
- Need deterministic, predictable responses (compliance, legal)
- Budget constraints on per-token costs
- Offline/on-premise required with no cloud API access
- Latency critical (<100ms responses)

---

### Architecture Pattern

```
User Message
    ↓
[LLM with System Prompt + Conversation History]
    ↓
LLM Response with Function Calls
    ↓
[Execute Functions] → CRM, Booking, Search, etc.
    ↓
Return Function Results to LLM
    ↓
LLM Generates Natural Response
    ↓
Send to User
```

---

### Implementation: OpenAI Function Calling

```typescript
import OpenAI from 'openai';

const client = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY
});

// Define available functions
const functions = [
  {
    name: 'book_appointment',
    description: 'Books an appointment for the user',
    parameters: {
      type: 'object',
      properties: {
        date: { type: 'string', description: 'Date in YYYY-MM-DD format' },
        time: { type: 'string', description: 'Time in HH:MM format' },
        name: { type: 'string', description: 'User\'s full name' },
        email: { type: 'string', description: 'User\'s email address' }
      },
      required: ['date', 'time', 'name', 'email']
    }
  },
  {
    name: 'create_lead',
    description: 'Creates a new lead in CRM',
    parameters: {
      type: 'object',
      properties: {
        name: { type: 'string' },
        email: { type: 'string' },
        company: { type: 'string' },
        budget: { type: 'number' },
        timeline: { type: 'string' }
      },
      required: ['name', 'email']
    }
  },
  {
    name: 'search_knowledge_base',
    description: 'Searches the knowledge base for answers',
    parameters: {
      type: 'object',
      properties: {
        query: { type: 'string', description: 'Search query' }
      },
      required: ['query']
    }
  }
];

// System prompt
const systemPrompt = `You are a helpful customer support assistant for Acme Corp.

Your role:
- Help customers with order tracking, returns, and general questions
- Book demos and consultations
- Qualify leads for the sales team
- Search the knowledge base when you don't know the answer
- Escalate to human agents for complex issues

Personality:
- Friendly and professional
- Empathetic when users have problems
- Concise but thorough

Guidelines:
- Always confirm before booking appointments or creating leads
- If you don't know something, search the knowledge base or escalate
- Use the user's name when you know it
- Ask one question at a time to avoid overwhelming users`;

// Conversation handler
async function handleConversation(
  messages: Array<{ role: string; content: string }>,
  availableFunctions: Record<string, Function>
) {
  // Add system prompt
  const messagesWithSystem = [
    { role: 'system', content: systemPrompt },
    ...messages
  ];

  // Call LLM
  const response = await client.chat.completions.create({
    model: 'gpt-4',
    messages: messagesWithSystem,
    functions: functions,
    function_call: 'auto',
    temperature: 0.7
  });

  const message = response.choices[0].message;

  // If LLM wants to call a function
  if (message.function_call) {
    const functionName = message.function_call.name;
    const functionArgs = JSON.parse(message.function_call.arguments);

    // Execute the function
    const functionToCall = availableFunctions[functionName];
    const functionResult = await functionToCall(functionArgs);

    // Add function result to conversation
    messages.push({
      role: 'assistant',
      content: null,
      function_call: message.function_call
    });
    messages.push({
      role: 'function',
      name: functionName,
      content: JSON.stringify(functionResult)
    });

    // Call LLM again with function result
    return handleConversation(messages, availableFunctions);
  }

  // No function call, return response
  return message.content;
}

// Example usage
const conversationHistory = [
  { role: 'user', content: 'I want to book a demo for next Tuesday at 2pm' }
];

const availableFunctions = {
  book_appointment: async (args) => {
    // Actual booking logic here
    return { success: true, confirmationId: 'ABC123' };
  },
  create_lead: async (args) => {
    // CRM integration here
    return { success: true, leadId: 'LEAD-456' };
  },
  search_knowledge_base: async (args) => {
    // KB search here
    return { results: ['Article 1...', 'Article 2...'] };
  }
};

const response = await handleConversation(conversationHistory, availableFunctions);
```

---

### Implementation: Anthropic Claude

```typescript
import Anthropic from '@anthropic-ai/sdk';

const client = new Anthropic({
  apiKey: process.env.ANTHROPIC_API_KEY
});

// Define tools (similar to OpenAI functions)
const tools = [
  {
    name: 'book_appointment',
    description: 'Books an appointment for the user',
    input_schema: {
      type: 'object',
      properties: {
        date: { type: 'string', description: 'Date in YYYY-MM-DD format' },
        time: { type: 'string', description: 'Time in HH:MM format' },
        name: { type: 'string', description: 'User full name' },
        email: { type: 'string', description: 'User email address' }
      },
      required: ['date', 'time', 'name', 'email']
    }
  }
];

async function handleConversationClaude(
  messages: Array<{ role: string; content: string }>,
  tools: any[]
) {
  const response = await client.messages.create({
    model: 'claude-3-5-sonnet-20241022',
    max_tokens: 1024,
    system: systemPrompt,
    messages: messages,
    tools: tools
  });

  // Handle tool use
  if (response.stop_reason === 'tool_use') {
    const toolUse = response.content.find(block => block.type === 'tool_use');

    // Execute tool
    const result = await executeTool(toolUse.name, toolUse.input);

    // Continue conversation with tool result
    messages.push({
      role: 'assistant',
      content: response.content
    });
    messages.push({
      role: 'user',
      content: [{
        type: 'tool_result',
        tool_use_id: toolUse.id,
        content: JSON.stringify(result)
      }]
    });

    return handleConversationClaude(messages, tools);
  }

  // Extract text response
  const textContent = response.content.find(block => block.type === 'text');
  return textContent?.text;
}
```

---

### Conversation Memory Management

```typescript
interface ConversationMemory {
  shortTerm: Message[];  // Last 10-20 messages
  longTerm: UserProfile; // Persistent user data
  summary: string;       // Conversation summary for context
}

class MemoryManager {
  private maxShortTermMessages = 20;

  async getContext(userId: string): Promise<ConversationMemory> {
    // Get user profile (long-term memory)
    const userProfile = await db.getUserProfile(userId);

    // Get recent messages (short-term memory)
    const recentMessages = await db.getRecentMessages(userId, this.maxShortTermMessages);

    // Generate summary if conversation is long
    let summary = '';
    if (recentMessages.length >= this.maxShortTermMessages) {
      summary = await this.summarizeConversation(recentMessages.slice(0, -10));
    }

    return {
      shortTerm: recentMessages,
      longTerm: userProfile,
      summary: summary
    };
  }

  async updateMemory(userId: string, message: Message) {
    // Store message
    await db.saveMessage(userId, message);

    // Update user profile if new information learned
    const extractedInfo = await this.extractUserInfo(message);
    if (extractedInfo) {
      await db.updateUserProfile(userId, extractedInfo);
    }
  }

  private async summarizeConversation(messages: Message[]): Promise<string> {
    const response = await llm.complete({
      prompt: `Summarize this conversation in 2-3 sentences:\n${JSON.stringify(messages)}`,
      maxTokens: 100
    });
    return response.text;
  }
}
```

---

## Traditional Framework Approach (Rasa)

### When to Use

✅ **Use traditional frameworks when**:
- Need full control and customization
- On-premise/offline deployment required
- Budget constraints (avoid per-token costs)
- Compliance requires deterministic behavior
- Have ML/NLP expertise on team

❌ **Avoid when**:
- Need quick prototype with small team
- Lack ML expertise for training
- Conversations are very free-form and unpredictable

---

### Rasa Setup

#### 1. Installation

```bash
pip install rasa

# Initialize project
rasa init
```

#### 2. Define Domain (`domain.yml`)

```yaml
version: "3.1"

intents:
  - greet
  - goodbye
  - book_appointment
  - track_order
  - affirm
  - deny

entities:
  - email
  - date
  - time
  - order_number

slots:
  email:
    type: text
    mappings:
      - type: from_entity
        entity: email
  appointment_date:
    type: text
    mappings:
      - type: from_entity
        entity: date
  appointment_time:
    type: text
    mappings:
      - type: from_entity
        entity: time

responses:
  utter_greet:
    - text: "Hi! How can I help you today?"
    - text: "Hello! What can I do for you?"

  utter_goodbye:
    - text: "Goodbye! Have a great day!"
    - text: "Thanks for chatting. See you soon!"

  utter_ask_email:
    - text: "What's your email address?"

  utter_ask_date:
    - text: "What date would you like to book?"

actions:
  - action_book_appointment
  - action_track_order

session_config:
  session_expiration_time: 60
  carry_over_slots_to_new_session: true
```

#### 3. Training Data (`data/nlu.yml`)

```yaml
version: "3.1"

nlu:
- intent: greet
  examples: |
    - hey
    - hello
    - hi
    - good morning
    - good evening
    - hey there

- intent: book_appointment
  examples: |
    - I want to book an appointment
    - Can I schedule a meeting?
    - Book a demo for me
    - I'd like to set up a call
    - Schedule an appointment for [next Tuesday](date) at [2pm](time)
    - Book me in for [tomorrow](date)

- intent: track_order
  examples: |
    - Where is my order?
    - Track order [12345](order_number)
    - I want to track my package
    - What's the status of order [ABC-123](order_number)?
```

#### 4. Stories (`data/stories.yml`)

```yaml
version: "3.1"

stories:
- story: book appointment happy path
  steps:
  - intent: greet
  - action: utter_greet
  - intent: book_appointment
  - action: utter_ask_email
  - intent: inform
    entities:
    - email
  - action: utter_ask_date
  - intent: inform
    entities:
    - date
  - action: utter_ask_time
  - intent: inform
    entities:
    - time
  - action: action_book_appointment
  - action: utter_goodbye

- story: track order
  steps:
  - intent: track_order
    entities:
    - order_number
  - action: action_track_order
```

#### 5. Custom Actions (`actions/actions.py`)

```python
from typing import Any, Text, Dict, List
from rasa_sdk import Action, Tracker
from rasa_sdk.executor import CollectingDispatcher
import requests

class ActionBookAppointment(Action):
    def name(self) -> Text:
        return "action_book_appointment"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any]
    ) -> List[Dict[Text, Any]]:
        # Get slot values
        email = tracker.get_slot("email")
        date = tracker.get_slot("appointment_date")
        time = tracker.get_slot("appointment_time")

        # Call booking API
        try:
            response = requests.post(
                "https://api.example.com/bookings",
                json={"email": email, "date": date, "time": time}
            )

            if response.status_code == 200:
                confirmation = response.json()["confirmation_id"]
                dispatcher.utter_message(
                    text=f"✓ Appointment booked! Confirmation: {confirmation}"
                )
            else:
                dispatcher.utter_message(
                    text="Sorry, I couldn't book the appointment. Please try again."
                )
        except Exception as e:
            dispatcher.utter_message(
                text="There was an error. Let me connect you with a team member."
            )

        return []
```

#### 6. Train and Run

```bash
# Train the model
rasa train

# Start action server (in one terminal)
rasa run actions

# Start Rasa server (in another terminal)
rasa run

# Or run with API
rasa run --enable-api --cors "*"
```

---

## Hybrid Approach

### Architecture

```
User Message
    ↓
[LLM for Intent Recognition + Entity Extraction]
    ↓
Recognized Intent + Entities
    ↓
[Structured Workflow Engine]
    ↓
Execute Actions (API calls, database, etc.)
    ↓
[LLM for Response Generation]
    ↓
Natural Language Response
```

### Implementation

```typescript
class HybridBot {
  private llm: LLMClient;
  private workflowEngine: WorkflowEngine;

  async handleMessage(userMessage: string, context: Context) {
    // Step 1: Use LLM to understand intent and extract entities
    const understanding = await this.llm.understand(userMessage, context);
    // Returns: { intent: 'book_appointment', entities: { date: '2024-03-05', time: '14:00' } }

    // Step 2: Route to structured workflow
    const workflow = this.workflowEngine.getWorkflow(understanding.intent);
    const result = await workflow.execute(understanding.entities, context);

    // Step 3: Use LLM to generate natural response
    const response = await this.llm.generateResponse({
      intent: understanding.intent,
      result: result,
      context: context
    });

    return response;
  }
}

// Workflow example
class BookingWorkflow {
  async execute(entities: any, context: Context) {
    // Structured, deterministic logic
    const slots = {
      date: entities.date || await this.askFor('date'),
      time: entities.time || await this.askFor('time'),
      email: context.user.email || await this.askFor('email')
    };

    // Validate
    if (!this.isValidDate(slots.date)) {
      throw new Error('Invalid date');
    }

    // Execute action
    const booking = await bookingAPI.create(slots);

    return { success: true, confirmationId: booking.id };
  }
}
```

**Benefits**:
- LLM flexibility for understanding + workflow reliability for actions
- Easier to audit and debug than pure LLM
- More natural than pure rule-based

---

## No-Code Platforms

### Voiceflow

**Best for**: Visual conversation design, rapid prototyping

```
[Visual Canvas]
  ├─ Start Block
  ├─ Capture: User Intent (button or voice)
  ├─ Condition: If intent == "book_demo"
  │   ├─ Capture: Email
  │   ├─ Capture: Date
  │   ├─ API Call: POST /bookings
  │   └─ Response: "Booked!"
  └─ Else: Fallback
```

**Export**: Can export to code or deploy via Voiceflow runtime

---

### Botpress

**Best for**: Self-hosted no-code with customization

**Features**:
- Visual flow builder
- Built-in NLU
- CMS for content management
- Code editor for custom logic
- Deployment options (cloud or self-hosted)

---

## Framework Comparison Summary

| Feature | LLM-Powered | Rasa | Hybrid | Voiceflow |
|---------|-------------|------|--------|-----------|
| **Speed to MVP** | ⚡⚡⚡ Fast | 🐢 Slow | ⚡⚡ Medium | ⚡⚡⚡ Very Fast |
| **Flexibility** | ⭐⭐⭐ High | ⭐⭐ Medium | ⭐⭐⭐ High | ⭐ Low |
| **Cost (at scale)** | 💰💰💰 High | 💰 Low | 💰💰 Medium | 💰💰 Medium |
| **Determinism** | ❌ Low | ✅ High | ⚡ Medium | ✅ High |
| **Customization** | ⭐⭐ Medium | ⭐⭐⭐ High | ⭐⭐⭐ High | ⭐ Low |
| **ML Expertise Needed** | ❌ No | ✅ Yes | ⚡ Some | ❌ No |

---

## Choosing Your Framework

**Start with LLM-powered if**:
- Building MVP or prototype
- Small-medium scale (<10k conversations/mo)
- Team has little ML expertise
- Need to iterate quickly

**Choose Traditional (Rasa) if**:
- Large scale (>100k conversations/mo)
- On-premise/offline required
- Have ML/NLP team
- Compliance requires deterministic behavior

**Go Hybrid if**:
- Need both flexibility and reliability
- Want natural understanding with structured actions
- Medium-large scale with budget for complexity

**Use No-Code if**:
- Non-technical team
- Need visual workflow management
- Simple use case with limited customization needs
