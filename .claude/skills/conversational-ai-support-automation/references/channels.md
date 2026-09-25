# Multichannel Deployment

Deploy conversational AI across web, messaging platforms, and voice channels.

---

## Channel Overview

| Channel | Best For | Rich Media | Complexity | Cost |
|---------|----------|------------|------------|------|
| **Web Widget** | Website visitors | ✅ Full | Low | Free |
| **WhatsApp** | Customer support | ⚡ Limited | Medium | Per message |
| **Slack** | Internal/B2B | ✅ Full | Low | Free |
| **Discord** | Community | ✅ Full | Low | Free |
| **SMS** | Notifications | ❌ Text only | Low | Per message |
| **Voice** | Phone support | ❌ Audio | High | Per minute |

---

## Web Widget

### Embedding Script

```html
<!-- index.html -->
<!DOCTYPE html>
<html>
<head>
  <title>Chat Widget Demo</title>
</head>
<body>
  <h1>Welcome to Our Site</h1>

  <!-- Chat Widget Container -->
  <div id="chat-widget"></div>

  <!-- Widget Script -->
  <script src="https://cdn.yourdomain.com/chat-widget.js"></script>
  <script>
    ChatWidget.init({
      apiUrl: 'https://api.yourdomain.com/chat',
      theme: 'light',
      position: 'bottom-right',
      welcomeMessage: 'Hi! How can I help you today?'
    });
  </script>
</body>
</html>
```

### Widget Implementation (React)

```typescript
import React, { useState, useEffect, useRef } from 'react';
import './ChatWidget.css';

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: Date;
}

export function ChatWidget({ apiUrl }: { apiUrl: string }) {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [isTyping, setIsTyping] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(scrollToBottom, [messages]);

  const sendMessage = async () => {
    if (!input.trim()) return;

    const userMessage: Message = {
      role: 'user',
      content: input,
      timestamp: new Date()
    };

    setMessages(prev => [...prev, userMessage]);
    setInput('');
    setIsTyping(true);

    try {
      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: input,
          history: messages
        })
      });

      const data = await response.json();

      const assistantMessage: Message = {
        role: 'assistant',
        content: data.response,
        timestamp: new Date()
      };

      setMessages(prev => [...prev, assistantMessage]);
    } catch (error) {
      console.error('Chat error:', error);
    } finally {
      setIsTyping(false);
    }
  };

  return (
    <>
      {/* Chat Button */}
      {!isOpen && (
        <button className="chat-button" onClick={() => setIsOpen(true)}>
          <span>💬</span>
        </button>
      )}

      {/* Chat Window */}
      {isOpen && (
        <div className="chat-window">
          {/* Header */}
          <div className="chat-header">
            <h3>Chat with us</h3>
            <button onClick={() => setIsOpen(false)}>✕</button>
          </div>

          {/* Messages */}
          <div className="chat-messages">
            {messages.map((msg, i) => (
              <div key={i} className={`message ${msg.role}`}>
                <div className="message-content">{msg.content}</div>
                <div className="message-time">
                  {msg.timestamp.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                  })}
                </div>
              </div>
            ))}
            {isTyping && (
              <div className="message assistant typing">
                <span></span><span></span><span></span>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Input */}
          <div className="chat-input">
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && sendMessage()}
              placeholder="Type a message..."
            />
            <button onClick={sendMessage}>Send</button>
          </div>
        </div>
      )}
    </>
  );
}
```

---

## WhatsApp Business API

### Setup

1. Create Meta Business App
2. Get WhatsApp Business API access
3. Register phone number
4. Set webhook URL

### Webhook Handler

```typescript
import express from 'express';
import axios from 'axios';

const app = express();
app.use(express.json());

// Webhook verification (Meta requirement)
app.get('/webhook/whatsapp', (req, res) => {
  const mode = req.query['hub.mode'];
  const token = req.query['hub.verify_token'];
  const challenge = req.query['hub.challenge'];

  if (mode === 'subscribe' && token === process.env.WHATSAPP_VERIFY_TOKEN) {
    res.status(200).send(challenge);
  } else {
    res.sendStatus(403);
  }
});

// Receive messages
app.post('/webhook/whatsapp', async (req, res) => {
  const { entry } = req.body;

  for (const item of entry) {
    for (const change of item.changes) {
      if (change.field === 'messages') {
        const message = change.value.messages?.[0];
        if (message && message.type === 'text') {
          await handleWhatsAppMessage(message);
        }
      }
    }
  }

  res.sendStatus(200);
});

async function handleWhatsAppMessage(message: any) {
  const from = message.from; // User phone number
  const text = message.text.body;

  // Get bot response
  const response = await getBotResponse(text, from);

  // Send reply
  await sendWhatsAppMessage(from, response);
}

async function sendWhatsAppMessage(to: string, text: string) {
  await axios.post(
    `https://graph.facebook.com/v18.0/${process.env.WHATSAPP_PHONE_NUMBER_ID}/messages`,
    {
      messaging_product: 'whatsapp',
      to: to,
      type: 'text',
      text: { body: text }
    },
    {
      headers: {
        Authorization: `Bearer ${process.env.WHATSAPP_ACCESS_TOKEN}`,
        'Content-Type': 'application/json'
      }
    }
  );
}

// Send interactive buttons
async function sendWhatsAppButtons(to: string, text: string, buttons: Array<{ id: string; title: string }>) {
  await axios.post(
    `https://graph.facebook.com/v18.0/${process.env.WHATSAPP_PHONE_NUMBER_ID}/messages`,
    {
      messaging_product: 'whatsapp',
      to: to,
      type: 'interactive',
      interactive: {
        type: 'button',
        body: { text: text },
        action: {
          buttons: buttons.map(btn => ({
            type: 'reply',
            reply: { id: btn.id, title: btn.title }
          }))
        }
      }
    },
    {
      headers: {
        Authorization: `Bearer ${process.env.WHATSAPP_ACCESS_TOKEN}`,
        'Content-Type': 'application/json'
      }
    }
  );
}
```

---

## Slack

### Setup with Bolt Framework

```typescript
import { App } from '@slack/bolt';

const app = new App({
  token: process.env.SLACK_BOT_TOKEN,
  signingSecret: process.env.SLACK_SIGNING_SECRET,
  socketMode: true, // or use webhooks
  appToken: process.env.SLACK_APP_TOKEN
});

// Listen for messages
app.message(async ({ message, say }) => {
  // @ts-ignore
  const text = message.text;
  // @ts-ignore
  const userId = message.user;

  // Get bot response
  const response = await getBotResponse(text, userId);

  await say(response);
});

// Listen for slash commands
app.command('/book-demo', async ({ command, ack, say }) => {
  await ack();

  await say({
    blocks: [
      {
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: 'When would you like to book a demo?'
        }
      },
      {
        type: 'actions',
        elements: [
          {
            type: 'button',
            text: { type: 'plain_text', text: 'This Week' },
            action_id: 'book_this_week'
          },
          {
            type: 'button',
            text: { type: 'plain_text', text: 'Next Week' },
            action_id: 'book_next_week'
          }
        ]
      }
    ]
  });
});

// Handle button clicks
app.action('book_this_week', async ({ ack, say }) => {
  await ack();
  await say('Great! Let me find available slots for this week...');
});

(async () => {
  await app.start(process.env.PORT || 3000);
  console.log('⚡️ Slack bot is running!');
})();
```

---

## Discord

```typescript
import { Client, GatewayIntentBits, Message } from 'discord.js';

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
    GatewayIntentBits.MessageContent
  ]
});

client.on('ready', () => {
  console.log(`Logged in as ${client.user?.tag}`);
});

client.on('messageCreate', async (message: Message) => {
  // Ignore bot messages
  if (message.author.bot) return;

  // Only respond to DMs or mentions
  if (message.mentions.has(client.user!) || message.channel.type === 1) {
    const userMessage = message.content.replace(`<@${client.user!.id}>`, '').trim();

    // Show typing indicator
    await message.channel.sendTyping();

    // Get bot response
    const response = await getBotResponse(userMessage, message.author.id);

    await message.reply(response);
  }
});

client.login(process.env.DISCORD_BOT_TOKEN);
```

---

## Voice (Twilio)

```typescript
import express from 'express';
import twilio from 'twilio';

const app = express();
const VoiceResponse = twilio.twiml.VoiceResponse;

app.post('/voice/incoming', (req, res) => {
  const twiml = new VoiceResponse();

  // Gather speech input
  const gather = twiml.gather({
    input: ['speech'],
    action: '/voice/process',
    method: 'POST',
    speechTimeout: 'auto',
    language: 'en-US'
  });

  gather.say('Hi! How can I help you today?');

  res.type('text/xml');
  res.send(twiml.toString());
});

app.post('/voice/process', async (req, res) => {
  const speechResult = req.body.SpeechResult;

  // Get bot response
  const response = await getBotResponse(speechResult, req.body.From);

  const twiml = new VoiceResponse();
  twiml.say(response);

  // Continue conversation
  twiml.redirect('/voice/incoming');

  res.type('text/xml');
  res.send(twiml.toString());
});

app.listen(3000);
```

---

## Channel Abstraction Layer

```typescript
interface ChannelAdapter {
  sendMessage(userId: string, message: string): Promise<void>;
  sendButtons(userId: string, text: string, buttons: Button[]): Promise<void>;
  sendCards(userId: string, cards: Card[]): Promise<void>;
}

class WebAdapter implements ChannelAdapter {
  async sendMessage(userId: string, message: string) {
    // Send via WebSocket or HTTP
  }

  async sendButtons(userId: string, text: string, buttons: Button[]) {
    // Full button support
  }

  async sendCards(userId: string, cards: Card[]) {
    // Full card support
  }
}

class WhatsAppAdapter implements ChannelAdapter {
  async sendMessage(userId: string, message: string) {
    await sendWhatsAppMessage(userId, message);
  }

  async sendButtons(userId: string, text: string, buttons: Button[]) {
    // WhatsApp: max 3 buttons
    const limitedButtons = buttons.slice(0, 3);
    await sendWhatsAppButtons(userId, text, limitedButtons);
  }

  async sendCards(userId: string, cards: Card[]) {
    // Fallback: send as text list
    for (const card of cards) {
      await this.sendMessage(userId, cardToText(card));
    }
  }
}

class SMSAdapter implements ChannelAdapter {
  async sendMessage(userId: string, message: string) {
    await twilioClient.sendSMS(userId, message);
  }

  async sendButtons(userId: string, text: string, buttons: Button[]) {
    // Fallback: numbered list
    const numberedOptions = buttons.map((btn, i) => `${i + 1}. ${btn.text}`).join('\n');
    await this.sendMessage(userId, `${text}\n\n${numberedOptions}\n\nReply with a number.`);
  }

  async sendCards(userId: string, cards: Card[]) {
    // Fallback: text
    for (const card of cards) {
      await this.sendMessage(userId, cardToText(card));
    }
  }
}

// Usage
class Bot {
  async respond(userId: string, channel: ChannelAdapter) {
    // Same logic works across channels
    await channel.sendMessage(userId, 'What would you like to do?');
    await channel.sendButtons(userId, 'Choose:', [
      { id: '1', text: 'Track Order' },
      { id: '2', text: 'Book Demo' },
      { id: '3', text: 'Contact Support' }
    ]);
  }
}
```

---

## Best Practices

### DO
- ✅ Abstract channel differences with adapters
- ✅ Gracefully degrade rich features on limited channels
- ✅ Verify webhooks with signatures
- ✅ Handle channel-specific limits (button count, message length)
- ✅ Maintain consistent experience across channels
- ✅ Use typing indicators when processing
- ✅ Test on each target channel

### DON'T
- ❌ Hardcode channel-specific logic in core bot
- ❌ Send rich media to channels that don't support it
- ❌ Exceed message length limits (truncate or split)
- ❌ Ignore webhook verification
- ❌ Assume all channels support same features
- ❌ Leave users waiting without feedback
- ❌ Deploy without testing on actual channel
