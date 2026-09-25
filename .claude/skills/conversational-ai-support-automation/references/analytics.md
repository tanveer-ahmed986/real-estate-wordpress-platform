# Analytics & Optimization

Track performance, measure success, and continuously improve conversational AI systems.

---

## Key Metrics

### Conversation Metrics

| Metric | Formula | Target | Purpose |
|--------|---------|--------|---------|
| **Completion Rate** | Completed conversations / Total conversations | >75% | Measures if users achieve their goal |
| **Resolution Rate** | Resolved without escalation / Total conversations | >60% | Bot's ability to handle requests |
| **Containment Rate** | Handled by bot / (Handled by bot + Escalated) | >70% | Efficiency of automation |
| **Average Conversation Length** | Total messages / Total conversations | 4-8 messages | Conversation efficiency |
| **First Response Time** | Time to first bot response | <2s | System performance |
| **Average Handling Time** | Total conversation duration / Conversations | 2-5 min | Efficiency |

### Quality Metrics

| Metric | Formula | Target | Purpose |
|--------|---------|--------|---------|
| **Intent Recognition Accuracy** | Correct intents / Total intents | >85% | NLU performance |
| **CSAT (Customer Satisfaction)** | Satisfied users / Total responses | >4/5 | User satisfaction |
| **NPS (Net Promoter Score)** | Promoters - Detractors | >30 | Loyalty |
| **Fallback Rate** | Fallback triggers / Total messages | <10% | Understanding quality |

### Business Metrics

| Metric | Formula | Target | Purpose |
|--------|---------|--------|---------|
| **Lead Conversion Rate** | Leads converted / Total leads | >15% | Sales effectiveness |
| **Cost per Conversation** | Total costs / Total conversations | <$0.50 | Cost efficiency |
| **Cost Savings** | (Agent cost - Bot cost) * Conversations | - | ROI calculation |
| **Booking Rate** | Bookings / Booking attempts | >60% | Conversion |

---

## Implementation

### Event Tracking

```typescript
interface ConversationEvent {
  type: 'conversation_start' | 'message_sent' | 'message_received' |
        'intent_recognized' | 'fallback_triggered' | 'escalated' |
        'conversation_completed' | 'conversation_abandoned';
  timestamp: Date;
  conversationId: string;
  userId: string;
  metadata?: Record<string, any>;
}

class AnalyticsTracker {
  async trackEvent(event: ConversationEvent) {
    // Store in database
    await db.events.insert(event);

    // Send to analytics platform
    await this.sendToAnalytics(event);

    // Update real-time metrics
    await this.updateMetrics(event);
  }

  private async sendToAnalytics(event: ConversationEvent) {
    // Example: Mixpanel
    mixpanel.track(event.type, {
      conversationId: event.conversationId,
      userId: event.userId,
      timestamp: event.timestamp,
      ...event.metadata
    });

    // Example: Google Analytics
    gtag('event', event.type, {
      conversation_id: event.conversationId,
      user_id: event.userId
    });
  }

  private async updateMetrics(event: ConversationEvent) {
    // Real-time dashboards
    switch (event.type) {
      case 'conversation_start':
        await redis.incr('metrics:conversations:today');
        break;
      case 'fallback_triggered':
        await redis.incr('metrics:fallbacks:today');
        break;
      case 'escalated':
        await redis.incr('metrics:escalations:today');
        break;
    }
  }
}

// Usage
const tracker = new AnalyticsTracker();

await tracker.trackEvent({
  type: 'intent_recognized',
  timestamp: new Date(),
  conversationId: conv.id,
  userId: user.id,
  metadata: {
    intent: 'book_appointment',
    confidence: 0.92,
    channel: 'web'
  }
});
```

---

## Dashboard Implementation

### Real-Time Metrics Dashboard

```typescript
import express from 'express';
import { createClient } from 'redis';

const app = express();
const redis = createClient();

app.get('/api/metrics/realtime', async (req, res) => {
  const metrics = {
    activeConversations: await redis.get('metrics:active_conversations'),
    conversationsToday: await redis.get('metrics:conversations:today'),
    fallbacksToday: await redis.get('metrics:fallbacks:today'),
    escalationsToday: await redis.get('metrics:escalations:today'),
    averageResponseTime: await calculateAverageResponseTime(),
    topIntents: await getTopIntents()
  };

  res.json(metrics);
});

async function calculateAverageResponseTime(): Promise<number> {
  const result = await db.query(`
    SELECT AVG(response_time_ms) as avg_response_time
    FROM conversation_events
    WHERE type = 'message_received'
    AND timestamp > NOW() - INTERVAL '1 hour'
  `);

  return result.rows[0].avg_response_time;
}

async function getTopIntents(): Promise<Array<{ intent: string; count: number }>> {
  const result = await db.query(`
    SELECT metadata->>'intent' as intent, COUNT(*) as count
    FROM conversation_events
    WHERE type = 'intent_recognized'
    AND timestamp > NOW() - INTERVAL '24 hours'
    GROUP BY intent
    ORDER BY count DESC
    LIMIT 10
  `);

  return result.rows;
}
```

---

## Conversation Funnel Analysis

```typescript
interface ConversationFunnel {
  step: string;
  entered: number;
  completed: number;
  dropoffRate: number;
}

async function analyzeBookingFunnel(): Promise<ConversationFunnel[]> {
  const funnel = [
    { step: 'Started booking', intent: 'book_appointment' },
    { step: 'Provided email', slot: 'email' },
    { step: 'Selected date', slot: 'date' },
    { step: 'Confirmed booking', action: 'booking_confirmed' }
  ];

  const results: ConversationFunnel[] = [];

  for (let i = 0; i < funnel.length; i++) {
    const step = funnel[i];
    const entered = await countConversations(step);
    const completed = i < funnel.length - 1 ? await countConversations(funnel[i + 1]) : entered;

    results.push({
      step: step.step,
      entered,
      completed,
      dropoffRate: ((entered - completed) / entered) * 100
    });
  }

  return results;
}

// Example output:
// [
//   { step: 'Started booking', entered: 1000, completed: 800, dropoffRate: 20 },
//   { step: 'Provided email', entered: 800, completed: 650, dropoffRate: 18.75 },
//   { step: 'Selected date', entered: 650, completed: 600, dropoffRate: 7.69 },
//   { step: 'Confirmed booking', entered: 600, completed: 600, dropoffRate: 0 }
// ]
```

---

## User Satisfaction Tracking

### In-Conversation CSAT

```typescript
async function collectCSAT(conversationId: string) {
  await chatbot.sendMessage(conversationId, {
    text: 'How would you rate your experience today?',
    options: [
      { text: '😄 Great', value: 5 },
      { text: '🙂 Good', value: 4 },
      { text: '😐 Okay', value: 3 },
      { text: '🙁 Poor', value: 2 },
      { text: '😞 Terrible', value: 1 }
    ]
  });

  const response = await chatbot.waitForResponse(conversationId);

  // Store feedback
  await db.feedback.insert({
    conversationId,
    rating: response.value,
    timestamp: new Date()
  });

  // Ask for details if low rating
  if (response.value <= 2) {
    await chatbot.sendMessage(conversationId, {
      text: 'We\'re sorry to hear that. What went wrong?',
      type: 'text'
    });

    const details = await chatbot.waitForResponse(conversationId);

    await db.feedback.update(conversationId, {
      details: details.text
    });

    // Alert team
    await slackClient.notify('#customer-feedback', {
      message: `⚠️ Low rating (${response.value}/5) - ${details.text}`,
      conversationUrl: `https://dashboard.com/conversations/${conversationId}`
    });
  } else if (response.value >= 4) {
    await chatbot.sendMessage(conversationId, {
      text: 'Thank you! Glad we could help. 🎉'
    });
  }
}
```

### NPS Survey

```typescript
async function sendNPSSurvey(userId: string) {
  await chatbot.sendMessage(userId, {
    text: 'On a scale of 0-10, how likely are you to recommend us to a friend?',
    options: Array.from({ length: 11 }, (_, i) => ({
      text: i.toString(),
      value: i
    }))
  });

  const score = await chatbot.waitForResponse(userId);

  // Calculate NPS category
  let category: 'promoter' | 'passive' | 'detractor';
  if (score.value >= 9) category = 'promoter';
  else if (score.value >= 7) category = 'passive';
  else category = 'detractor';

  await db.nps.insert({
    userId,
    score: score.value,
    category,
    timestamp: new Date()
  });

  // Follow-up question
  const followUp = category === 'promoter'
    ? 'What do you love most about our service?'
    : 'What could we improve?';

  await chatbot.sendMessage(userId, { text: followUp, type: 'text' });
  const feedback = await chatbot.waitForResponse(userId);

  await db.nps.update(userId, { feedback: feedback.text });
}

async function calculateNPS(): Promise<number> {
  const result = await db.query(`
    SELECT category, COUNT(*) as count
    FROM nps
    WHERE timestamp > NOW() - INTERVAL '30 days'
    GROUP BY category
  `);

  const promoters = result.rows.find(r => r.category === 'promoter')?.count || 0;
  const detractors = result.rows.find(r => r.category === 'detractor')?.count || 0;
  const total = result.rows.reduce((sum, r) => sum + r.count, 0);

  return ((promoters - detractors) / total) * 100;
}
```

---

## A/B Testing

```typescript
interface ABTest {
  id: string;
  name: string;
  variants: {
    control: any;
    variant: any;
  };
  allocation: number; // 0-1 (0.5 = 50/50 split)
  metrics: string[];
}

class ABTestManager {
  async assignVariant(userId: string, testId: string): Promise<'control' | 'variant'> {
    // Check if user already assigned
    const existing = await db.abTests.getUserAssignment(userId, testId);
    if (existing) return existing.variant;

    // Assign based on allocation
    const test = await db.abTests.get(testId);
    const variant = Math.random() < test.allocation ? 'variant' : 'control';

    await db.abTests.assignUser(userId, testId, variant);

    return variant;
  }

  async trackMetric(userId: string, testId: string, metric: string, value: number) {
    await db.abTests.trackMetric({
      userId,
      testId,
      metric,
      value,
      timestamp: new Date()
    });
  }

  async getResults(testId: string) {
    const results = await db.query(`
      SELECT
        variant,
        metric,
        AVG(value) as avg_value,
        COUNT(*) as count
      FROM ab_test_metrics
      WHERE test_id = $1
      GROUP BY variant, metric
    `, [testId]);

    return this.calculateStatisticalSignificance(results.rows);
  }
}

// Usage: Test different greeting messages
const variant = await abTestManager.assignVariant(user.id, 'greeting-test');

const greeting = variant === 'control'
  ? 'Hi! How can I help you?'
  : 'Hey there! 👋 What brings you here today?';

await chatbot.sendMessage(user.id, { text: greeting });

// Track if conversation was completed
if (conversationCompleted) {
  await abTestManager.trackMetric(user.id, 'greeting-test', 'completion', 1);
}
```

---

## Intent Confidence Analysis

```typescript
async function analyzeIntentConfidence() {
  const results = await db.query(`
    SELECT
      metadata->>'intent' as intent,
      CAST(metadata->>'confidence' AS FLOAT) as confidence,
      CASE
        WHEN CAST(metadata->>'confidence' AS FLOAT) < 0.6 THEN 'low'
        WHEN CAST(metadata->>'confidence' AS FLOAT) < 0.8 THEN 'medium'
        ELSE 'high'
      END as confidence_level,
      COUNT(*) as count
    FROM conversation_events
    WHERE type = 'intent_recognized'
    AND timestamp > NOW() - INTERVAL '7 days'
    GROUP BY intent, confidence_level
    ORDER BY count DESC
  `);

  // Flag intents with low confidence
  const lowConfidence = results.rows.filter(r => r.confidence_level === 'low');

  if (lowConfidence.length > 0) {
    await slackClient.notify('#bot-training', {
      message: `⚠️ Intents needing training:\n${
        lowConfidence.map(i => `- ${i.intent}: ${i.count} low confidence recognitions`).join('\n')
      }`
    });
  }

  return results.rows;
}
```

---

## Failed Conversation Analysis

```typescript
async function analyzeFailed Conversations() {
  const failed = await db.query(`
    SELECT
      conversation_id,
      COUNT(*) FILTER (WHERE type = 'fallback_triggered') as fallback_count,
      COUNT(*) FILTER (WHERE type = 'escalated') as escalated
    FROM conversation_events
    WHERE timestamp > NOW() - INTERVAL '24 hours'
    GROUP BY conversation_id
    HAVING COUNT(*) FILTER (WHERE type = 'fallback_triggered') >= 3
    OR COUNT(*) FILTER (WHERE type = 'escalated') > 0
  `);

  // Review transcripts
  for (const conv of failed.rows) {
    const transcript = await db.conversations.getTranscript(conv.conversation_id);

    // Extract patterns
    const userMessages = transcript.filter(m => m.role === 'user').map(m => m.content);

    // Flag for review
    await db.reviewQueue.add({
      conversationId: conv.conversation_id,
      reason: conv.escalated ? 'escalated' : 'multiple_fallbacks',
      transcript: userMessages
    });
  }

  return failed.rows;
}
```

---

## Cost Analytics

```typescript
interface CostBreakdown {
  llmCosts: number;
  apiCosts: number;
  infrastructureCosts: number;
  totalCosts: number;
  conversationCount: number;
  costPerConversation: number;
}

async function calculateCosts(startDate: Date, endDate: Date): Promise<CostBreakdown> {
  // LLM costs (e.g., OpenAI)
  const llmUsage = await db.query(`
    SELECT
      SUM(CAST(metadata->>'prompt_tokens' AS INT)) as prompt_tokens,
      SUM(CAST(metadata->>'completion_tokens' AS INT)) as completion_tokens
    FROM conversation_events
    WHERE type = 'llm_call'
    AND timestamp BETWEEN $1 AND $2
  `, [startDate, endDate]);

  const llmCosts =
    (llmUsage.rows[0].prompt_tokens * 0.00001) +  // $0.01 per 1K tokens
    (llmUsage.rows[0].completion_tokens * 0.00003); // $0.03 per 1K tokens

  // API costs (integrations)
  const apiCalls = await db.query(`
    SELECT COUNT(*) as count
    FROM conversation_events
    WHERE type IN ('crm_call', 'booking_call', 'ticketing_call')
    AND timestamp BETWEEN $1 AND $2
  `, [startDate, endDate]);

  const apiCosts = apiCalls.rows[0].count * 0.001; // $0.001 per API call

  // Infrastructure (estimate)
  const days = (endDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24);
  const infrastructureCosts = days * 5; // $5/day

  const conversationCount = await db.query(`
    SELECT COUNT(DISTINCT conversation_id) as count
    FROM conversation_events
    WHERE timestamp BETWEEN $1 AND $2
  `, [startDate, endDate]);

  const totalCosts = llmCosts + apiCosts + infrastructureCosts;

  return {
    llmCosts,
    apiCosts,
    infrastructureCosts,
    totalCosts,
    conversationCount: conversationCount.rows[0].count,
    costPerConversation: totalCosts / conversationCount.rows[0].count
  };
}

// Calculate ROI
async function calculateROI(costs: CostBreakdown) {
  // Agent cost: $20/hour, handles 4 conversations/hour = $5/conversation
  const agentCost = 5;
  const botCost = costs.costPerConversation;

  const savingsPerConversation = agentCost - botCost;
  const totalSavings = savingsPerConversation * costs.conversationCount;

  return {
    savingsPerConversation,
    totalSavings,
    roi: (totalSavings / costs.totalCosts) * 100
  };
}
```

---

## Continuous Improvement

```typescript
async function generateImprovementReport() {
  return {
    // 1. Low confidence intents
    lowConfidenceIntents: await analyzeIntentConfidence(),

    // 2. High fallback topics
    highFallbackTopics: await identifyFallbackPatterns(),

    // 3. Conversation dropoffs
    dropoffPoints: await analyzeBookingFunnel(),

    // 4. User feedback themes
    feedbackThemes: await analyzeFeedbackThemes(),

    // 5. Cost optimization opportunities
    costOptimization: await identifyCostSavings(),

    // 6. A/B test winners
    abTestResults: await getCompletedABTests()
  };
}

async function identifyFallbackPatterns(): Promise<Array<{ pattern: string; count: number }>> {
  const fallbacks = await db.query(`
    SELECT
      metadata->>'user_message' as message,
      COUNT(*) as count
    FROM conversation_events
    WHERE type = 'fallback_triggered'
    AND timestamp > NOW() - INTERVAL '7 days'
    GROUP BY message
    ORDER BY count DESC
    LIMIT 20
  `);

  // Cluster similar messages
  return clusterSimilarMessages(fallbacks.rows);
}
```

---

## Best Practices

### DO
- ✅ Track every conversation event
- ✅ Monitor real-time metrics
- ✅ Set up alerts for anomalies
- ✅ Collect user satisfaction feedback
- ✅ Analyze failed conversations
- ✅ A/B test improvements
- ✅ Calculate ROI regularly
- ✅ Review low-confidence intents
- ✅ Monitor costs
- ✅ Create automated improvement reports

### DON'T
- ❌ Track without acting on insights
- ❌ Ignore low satisfaction scores
- ❌ Skip failed conversation analysis
- ❌ Change too many things at once (A/B test instead)
- ❌ Focus only on vanity metrics
- ❌ Forget to track costs
- ❌ Leave low-confidence intents unaddressed
- ❌ Deploy changes without measuring impact
