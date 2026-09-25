# Customer Lifecycle Automation

End-to-end workflows from first contact through ongoing support.

---

## Lifecycle Stages

```
Lead Capture → Qualification → Nurture → Conversion → Onboarding → Support → Retention
```

---

## 1. Lead Capture

### Goal
Collect contact information and initial interest from website visitors or inbound messages.

### Workflow

```typescript
interface LeadCaptureFlow {
  trigger: 'website_visitor' | 'whatsapp_inbound' | 'social_media';
  steps: [
    'greeting',
    'value_proposition',
    'capture_email',
    'capture_name',
    'optional_phone',
    'create_crm_lead',
    'send_welcome_email'
  ];
}
```

### Implementation

```
Bot: Hi! 👋 Looking to streamline your customer support?
User: Yes, tell me more
Bot: Great! We help companies automate 80% of support tickets with AI.
     What's your email? I'll send you a case study.
User: john@example.com
Bot: Perfect! And your name?
User: John
Bot: Nice to meet you, John! I've sent the case study to john@example.com.
     Would you like to book a quick 15-min demo to see it in action?
```

**Actions**:
1. Create lead in CRM with source tracking
2. Send welcome email with case study
3. Tag lead as "interested" in CRM
4. Trigger nurture sequence if no demo booked

**Code**:
```typescript
async function handleLeadCapture(conversation: Conversation) {
  // Collect data
  const data = await collectSlots(conversation, {
    required: ['email', 'name'],
    optional: ['phone', 'company']
  });

  // Create lead in CRM
  const lead = await crmClient.createLead({
    email: data.email,
    firstName: data.name.split(' ')[0],
    lastName: data.name.split(' ').slice(1).join(' '),
    phone: data.phone,
    company: data.company,
    source: 'Website Chatbot',
    status: 'New',
    tags: ['chatbot-captured']
  });

  // Send welcome email
  await emailClient.send({
    to: data.email,
    template: 'welcome',
    variables: {
      firstName: data.name.split(' ')[0],
      caseStudyUrl: 'https://...'
    }
  });

  // Offer next step
  await conversation.ask(
    'Would you like to book a quick demo?',
    ['Yes, book demo', 'Not right now', 'Tell me more first']
  );
}
```

---

## 2. Lead Qualification

### Goal
Assess fit using BANT (Budget, Authority, Need, Timeline) or custom criteria.

### Qualification Questions

```typescript
interface QualificationCriteria {
  budget: {
    question: 'What\'s your monthly support budget?',
    scoring: {
      '<$500': 1,
      '$500-$2000': 3,
      '$2000-$5000': 5,
      '>$5000': 7
    }
  },
  authority: {
    question: 'What\'s your role?',
    scoring: {
      'Individual Contributor': 1,
      'Manager': 3,
      'Director': 5,
      'VP/C-Level': 7
    }
  },
  need: {
    question: 'How many support tickets per month?',
    scoring: {
      '<100': 1,
      '100-500': 3,
      '500-2000': 5,
      '>2000': 7
    }
  },
  timeline: {
    question: 'When do you plan to implement?',
    scoring: {
      'Just researching': 1,
      'Next quarter': 3,
      'This quarter': 5,
      'Immediately': 7
    }
  }
}
```

### Scoring & Routing

```typescript
function calculateLeadScore(answers: Record<string, any>): number {
  let score = 0;

  for (const [key, criteria] of Object.entries(qualificationCriteria)) {
    const answer = answers[key];
    score += criteria.scoring[answer] || 0;
  }

  return score;
}

async function routeLead(leadId: string, score: number) {
  if (score >= 20) {
    // High-value lead: Immediate sales handoff
    await crmClient.updateLead(leadId, {
      status: 'Hot',
      assignedTo: await findAvailableSalesRep()
    });
    await slackClient.notify('#sales-hot-leads', {
      message: `🔥 Hot lead: ${leadId} (score: ${score})`
    });
    return 'immediate_sales_contact';
  } else if (score >= 12) {
    // Medium-value: Schedule demo
    await crmClient.updateLead(leadId, { status: 'Warm' });
    return 'book_demo';
  } else {
    // Low-value: Nurture campaign
    await crmClient.updateLead(leadId, { status: 'Nurture' });
    await emailClient.addToSequence(leadId, 'educational-nurture');
    return 'nurture_sequence';
  }
}
```

### Conversation Example

```
Bot: To see if we're a good fit, a few quick questions:
     How many support tickets do you handle per month?
User: Around 1000
Bot: Got it. What's your role?
User: I'm the Customer Success Director
Bot: Perfect. When are you looking to implement a solution?
User: We need something by next month
Bot: Excellent! Based on your needs, I'd love to connect you with our team.
     Are you available for a 30-minute call this week?
```

---

## 3. Nurture Campaign

### Goal
Keep low-score leads engaged until they're ready to buy.

### Automated Nurture Sequence

```typescript
const nurtureSequence = [
  {
    day: 0,
    channel: 'email',
    template: 'welcome_case_study',
    subject: 'How [Company] reduced support costs by 70%'
  },
  {
    day: 3,
    channel: 'email',
    template: 'educational_content',
    subject: '5 signs you need support automation'
  },
  {
    day: 7,
    channel: 'chatbot',
    action: 'check_in',
    message: 'Hi! Did you get a chance to review the case study?'
  },
  {
    day: 14,
    channel: 'email',
    template: 'customer_testimonial',
    subject: 'See how [Customer] saved 1000 hours/month'
  },
  {
    day: 21,
    channel: 'chatbot',
    action: 'offer_demo',
    message: 'Ready to see it in action? Book a quick demo:'
  }
];
```

### Re-engagement Trigger

```typescript
async function checkReEngagement(leadId: string) {
  const lead = await crmClient.getLead(leadId);
  const lastInteraction = lead.lastActivityDate;
  const daysSinceInteraction = dateDiff(lastInteraction, new Date());

  if (daysSinceInteraction >= 30) {
    // Re-engage via chatbot
    await chatbot.sendProactiveMessage(lead.email, {
      message: `Hi ${lead.firstName}! It's been a while. Anything I can help with?`,
      buttons: [
        { text: 'Book a demo', action: 'book_demo' },
        { text: 'Ask a question', action: 'start_conversation' },
        { text: 'Not interested', action: 'unsubscribe' }
      ]
    });
  }
}
```

---

## 4. Conversion (Demo Booking)

### Goal
Schedule and confirm qualified demos.

### Booking Flow

```typescript
async function handleDemoBooking(conversation: Conversation) {
  // 1. Fetch available slots
  const slots = await calendlyClient.getAvailableSlots({
    eventTypeId: 'demo-30min',
    startDate: new Date(),
    endDate: addDays(new Date(), 14),
    timezone: conversation.user.timezone
  });

  // 2. Present options
  await conversation.ask(
    'Great! When works best for you?',
    slots.slice(0, 5).map(slot => ({
      text: formatSlot(slot),
      value: slot.start
    }))
  );

  const selectedSlot = await conversation.waitForResponse();

  // 3. Collect additional info
  const context = await conversation.ask(
    'Perfect! Any specific use case you\'d like to discuss?',
    { type: 'text', optional: true }
  );

  // 4. Create booking
  const booking = await calendlyClient.createBooking({
    eventTypeId: 'demo-30min',
    start: selectedSlot,
    name: conversation.user.name,
    email: conversation.user.email,
    notes: context
  });

  // 5. Update CRM
  await crmClient.updateLead(conversation.user.crmId, {
    status: 'Demo Scheduled',
    nextAction: 'Prepare for demo',
    nextActionDate: selectedSlot
  });

  // 6. Send confirmations
  await emailClient.send({
    to: conversation.user.email,
    template: 'demo_confirmation',
    variables: {
      date: formatDate(selectedSlot),
      time: formatTime(selectedSlot),
      calendarLink: booking.addToCalendarUrl
    }
  });

  await conversation.say(
    `✓ Demo booked for ${formatDateTime(selectedSlot)}!\n` +
    `I've sent a calendar invite to ${conversation.user.email}.`
  );
}
```

### Pre-Demo Automation

```typescript
// 24 hours before demo
async function sendDemoReminder(bookingId: string) {
  const booking = await calendlyClient.getBooking(bookingId);

  await emailClient.send({
    to: booking.email,
    template: 'demo_reminder_24h',
    variables: {
      time: formatTime(booking.start),
      prepLink: 'https://...'
    }
  });

  // Send via chatbot too
  await chatbot.sendMessage(booking.email, {
    message: `Reminder: Your demo is tomorrow at ${formatTime(booking.start)}!`,
    buttons: [
      { text: 'Add to calendar', url: booking.addToCalendarUrl },
      { text: 'Reschedule', action: 'reschedule' }
    ]
  });
}
```

---

## 5. Onboarding

### Goal
Get new customers set up and successful quickly.

### Onboarding Checklist

```typescript
const onboardingSteps = [
  {
    step: 1,
    title: 'Create Account',
    action: async (user) => {
      await accountClient.create(user);
      await sendWelcomeEmail(user);
    }
  },
  {
    step: 2,
    title: 'Connect Data Sources',
    action: async (user) => {
      await chatbot.guide(user, 'data_source_setup');
    }
  },
  {
    step: 3,
    title: 'Configure First Bot',
    action: async (user) => {
      await chatbot.guide(user, 'bot_setup_wizard');
    }
  },
  {
    step: 4,
    title: 'Test & Deploy',
    action: async (user) => {
      await chatbot.guide(user, 'deployment_guide');
    }
  },
  {
    step: 5,
    title: 'Review Analytics',
    action: async (user) => {
      await chatbot.guide(user, 'analytics_tour');
    }
  }
];
```

### Proactive Guidance

```
Bot: Welcome to [Product]! Let's get you set up. First step: connect your data source.
     Which would you like to connect?
     [Zendesk] [Salesforce] [Custom API] [Skip for now]

User: Zendesk
Bot: Great choice! I'll guide you through connecting Zendesk.
     1. Go to your Zendesk settings
     2. Create an API token
     3. Paste it here

     Need help? Watch this 1-min video: [link]
```

---

## 6. Ongoing Support

### Goal
Resolve issues, answer questions, reduce churn.

### Support Tiers

```typescript
enum SupportTier {
  SELF_SERVICE,    // Bot handles
  TICKET,          // Create ticket for later
  LIVE_CHAT,       // Connect to agent now
  PRIORITY         // VIP/urgent
}

function determineSupportTier(context: SupportContext): SupportTier {
  // Priority customers
  if (context.user.plan === 'enterprise') {
    return SupportTier.PRIORITY;
  }

  // Critical issues
  if (context.sentiment === 'very_negative' || context.issue.includes('outage')) {
    return SupportTier.LIVE_CHAT;
  }

  // FAQ/simple issues
  if (context.confidence > 0.8 && context.hasKBArticle) {
    return SupportTier.SELF_SERVICE;
  }

  // Default: create ticket
  return SupportTier.TICKET;
}
```

### Self-Service Pattern

```
User: How do I reset my password?
Bot: I can help with that! Here's how to reset your password:

     1. Go to Settings > Account
     2. Click "Change Password"
     3. Enter your current password
     4. Enter your new password twice
     5. Click Save

     [Watch video] [Try it now]

     Did this help? [Yes] [No, I need more help]

User: No, I need more help
Bot: No problem! Let me connect you with a team member who can help.
     [Creating ticket with conversation context...]
```

---

## 7. Retention & Expansion

### Churn Risk Detection

```typescript
interface ChurnSignals {
  loginFrequency: number;      // Logins per week
  featureUsage: number;        // % of features used
  supportTickets: number;      // Recent tickets
  nps: number;                 // Net Promoter Score
  lastInteraction: Date;       // Days since last use
}

function calculateChurnRisk(signals: ChurnSignals): 'low' | 'medium' | 'high' {
  let riskScore = 0;

  if (signals.loginFrequency < 1) riskScore += 3;
  if (signals.featureUsage < 0.3) riskScore += 2;
  if (signals.supportTickets > 5) riskScore += 2;
  if (signals.nps < 6) riskScore += 3;
  if (dateDiff(signals.lastInteraction, new Date()) > 14) riskScore += 2;

  if (riskScore >= 7) return 'high';
  if (riskScore >= 4) return 'medium';
  return 'low';
}

async function handleChurnRisk(userId: string, risk: string) {
  if (risk === 'high') {
    // Proactive outreach
    await chatbot.sendMessage(userId, {
      message: 'Hey! We noticed you haven\'t been using [Product] much lately. Everything okay?',
      buttons: [
        { text: 'I need help', action: 'schedule_call' },
        { text: 'Missing a feature', action: 'feature_request' },
        { text: 'Just busy', action: 'dismiss' }
      ]
    });

    // Alert customer success
    await slackClient.notify('#cs-at-risk', {
      message: `⚠️ High churn risk: ${userId}`
    });
  }
}
```

### Upsell Opportunities

```typescript
async function detectUpsellOpportunity(user: User) {
  // Hitting plan limits
  if (user.usage.tickets > user.plan.ticketLimit * 0.8) {
    await chatbot.sendMessage(user.id, {
      message: `You're using 80% of your ticket quota. Upgrade to Pro for unlimited tickets?`,
      buttons: [
        { text: 'See plans', action: 'view_pricing' },
        { text: 'Not now', action: 'dismiss' }
      ]
    });
  }

  // Using advanced features on basic plan
  if (user.usage.advancedFeatures > 0 && user.plan === 'basic') {
    await chatbot.sendMessage(user.id, {
      message: `Love the advanced features? Upgrade to unlock them fully!`,
      buttons: [
        { text: 'Upgrade', action: 'upgrade_plan' },
        { text: 'Tell me more', action: 'view_features' }
      ]
    });
  }
}
```

---

## Complete Lifecycle Orchestration

```typescript
class LifecycleOrchestrator {
  async handleNewContact(contactInfo: ContactInfo) {
    // 1. Lead Capture
    const lead = await this.captureL
(contactInfo);

    // 2. Qualification
    const score = await this.qualifyLead(lead.id);

    // 3. Route based on score
    const route = await this.routeLead(lead.id, score);

    switch (route) {
      case 'immediate_sales_contact':
        await this.scheduleImmediateCall(lead.id);
        break;
      case 'book_demo':
        await this.offerDemoBooking(lead.id);
        break;
      case 'nurture_sequence':
        await this.startNurtureSequence(lead.id);
        break;
    }
  }

  async handleCustomerJourney(customerId: string) {
    const customer = await this.getCustomer(customerId);

    // Onboarding
    if (customer.status === 'new') {
      await this.runOnboarding(customerId);
    }

    // Ongoing engagement
    if (customer.status === 'active') {
      await this.monitorEngagement(customerId);
      await this.detectUpsellOpportunities(customerId);
    }

    // Churn prevention
    const churnRisk = await this.calculateChurnRisk(customerId);
    if (churnRisk !== 'low') {
      await this.handleChurnRisk(customerId, churnRisk);
    }
  }
}
```

---

## Best Practices

### DO
- ✅ Personalize based on user data and behavior
- ✅ Track progression through lifecycle stages
- ✅ Automate repetitive touchpoints
- ✅ Escalate high-value or at-risk accounts
- ✅ Measure conversion rates at each stage
- ✅ A/B test messaging and timing
- ✅ Provide easy opt-out from automation

### DON'T
- ❌ Spam users with too many messages
- ❌ Use same nurture for all lead scores
- ❌ Ignore signals of churn or expansion
- ❌ Automate without human oversight
- ❌ Forget to update CRM with interactions
- ❌ Send messages at bad times (respect timezone)
- ❌ Continue automation after user opts out
