# S-Hub Chat — React JS Integration Guide (Laravel Reverb)

## Credentials

```js
// config/chat.js
export const REVERB_APP_KEY = 'shub_reverb_key';
export const REVERB_HOST    = '192.168.101.117'; // Your server IP
export const REVERB_PORT    = 8080;
export const API_BASE_URL   = 'http://192.168.101.117:8000/api/v1';
```

> ⚠️ In production replace IP with your actual domain. Use port `443` + `forceTLS: true`.

---

## 1. Install Dependencies

```bash
npm install laravel-echo pusher-js axios
```

| Package | Purpose |
|---|---|
| `laravel-echo` | Higher-level WebSocket wrapper (channels, events) |
| `pusher-js` | Pusher-protocol transport (Reverb uses same protocol) |
| `axios` | HTTP client for REST calls |

---

## 2. Initialize Laravel Echo (one-time, after login)

Create `lib/echo.js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Call this ONCE after the user logs in.
 * Pass the Bearer token so Echo can authenticate private channels.
 */
export function createEcho(token) {
  return new Echo({
    broadcaster:      'reverb',
    key:              'shub_reverb_key',
    wsHost:           '192.168.101.117',   // Your server IP
    wsPort:           8080,
    wssPort:          8080,
    forceTLS:         false,               // Set true in production (HTTPS)
    enabledTransports: ['ws', 'wss'],

    // Private channel auth — sends token to /broadcasting/auth
    authEndpoint: 'http://192.168.101.117:8000/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept:        'application/json',
      },
    },
  });
}
```

---

## 3. Chat API Service — `lib/chatApi.js`

```js
import axios from 'axios';

const BASE = 'http://192.168.101.117:8000/api/v1';

function api(token) {
  return axios.create({
    baseURL: BASE,
    headers: {
      Authorization: `Bearer ${token}`,
      Accept:        'application/json',
    },
  });
}

// GET /chat/users — who can I chat with? (role-filtered)
export const getContacts = (token) =>
  api(token).get('/chat/users').then(r => r.data.data);

// POST /chat/conversations — start or find a conversation
export const startConversation = (token, recipientId) =>
  api(token).post('/chat/conversations', { recipient_id: recipientId }).then(r => r.data.data);

// GET /chat/conversations — inbox
export const getInbox = (token) =>
  api(token).get('/chat/conversations').then(r => r.data.data);

// GET /chat/conversations/{id}?page=N — paginated messages (auto-marks read)
export const getMessages = (token, conversationId, page = 1) =>
  api(token).get(`/chat/conversations/${conversationId}?page=${page}`).then(r => r.data.data);

// POST /chat/conversations/{id}/messages — send text message
export const sendMessage = (token, conversationId, body) =>
  api(token).post(`/chat/conversations/${conversationId}/messages`, { body }).then(r => r.data.data);

// POST /chat/conversations/{id}/messages — send file/image attachment
export const sendAttachment = (token, conversationId, file, body = '') => {
  const form = new FormData();
  form.append('attachment', file);
  if (body) form.append('body', body);
  return api(token)
    .post(`/chat/conversations/${conversationId}/messages`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then(r => r.data.data);
};

// PATCH /chat/conversations/{id}/read — mark all as read
export const markAsRead = (token, conversationId) =>
  api(token).patch(`/chat/conversations/${conversationId}/read`);
```

---

## 4. WebSocket Hook — `hooks/useChatSocket.js`

```js
import { useEffect, useRef } from 'react';
import { createEcho } from '../lib/echo';

/**
 * Subscribes to a private conversation channel and fires
 * onMessage whenever the other person sends a real-time message.
 *
 * Usage:
 *   useChatSocket({ token, conversationId, onMessage: (msg) => setMessages(prev => [msg, ...prev]) });
 */
export function useChatSocket({ token, conversationId, onMessage }) {
  const echoRef   = useRef(null);
  const channelRef = useRef(null);

  useEffect(() => {
    if (!token || !conversationId) return;

    // Create Echo instance
    echoRef.current = createEcho(token);

    // Subscribe to the private channel for this conversation
    channelRef.current = echoRef.current
      .private(`chat.${conversationId}`)
      .listen('.message.sent', (event) => {
        // event is the broadcastWith() payload from MessageSent.php
        onMessage(event);
      });

    return () => {
      // Cleanup: leave channel and disconnect on unmount
      echoRef.current?.leave(`chat.${conversationId}`);
      echoRef.current?.disconnect();
    };
  }, [token, conversationId]);
}
```

---

## 5. Inbox Component Example

```jsx
import { useEffect, useState } from 'react';
import { getInbox, startConversation } from '../lib/chatApi';

export default function Inbox({ token, onOpenChat }) {
  const [conversations, setConversations] = useState([]);

  useEffect(() => {
    getInbox(token).then(setConversations);
  }, []);

  return (
    <ul>
      {conversations.map(conv => (
        <li key={conv.id} onClick={() => onOpenChat(conv.id, conv.participant.full_name)}>
          <img src={conv.participant.profile_picture} alt="" width={40} />
          <div>
            <strong>{conv.participant.full_name}</strong>
            <p>{conv.last_message?.body ?? 'No messages yet'}</p>
          </div>
          {conv.unread_count > 0 && (
            <span className="badge">{conv.unread_count}</span>
          )}
        </li>
      ))}
    </ul>
  );
}
```

---

## 6. Chat Screen Component Example

```jsx
import { useEffect, useState } from 'react';
import { getMessages, sendMessage } from '../lib/chatApi';
import { useChatSocket } from '../hooks/useChatSocket';

export default function ChatScreen({ token, conversationId, participantName }) {
  const [messages, setMessages] = useState([]);
  const [text, setText]         = useState('');
  const [page, setPage]         = useState(1);

  // Load initial messages
  useEffect(() => {
    getMessages(token, conversationId, page).then(data => {
      setMessages(data.messages);
    });
  }, [conversationId, page]);

  // Real-time subscription — adds incoming messages instantly
  useChatSocket({
    token,
    conversationId,
    onMessage: (msg) => setMessages(prev => [msg, ...prev]),
  });

  const handleSend = async () => {
    if (!text.trim()) return;
    const sent = await sendMessage(token, conversationId, text);
    setMessages(prev => [sent, ...prev]); // optimistic update for sender
    setText('');
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100vh' }}>
      <h2>{participantName}</h2>

      {/* Messages list — newest at bottom, list reversed */}
      <div style={{ flex: 1, overflowY: 'auto', display: 'flex', flexDirection: 'column-reverse' }}>
        {messages.map(msg => (
          <div
            key={msg.id}
            style={{
              alignSelf:    msg.is_mine ? 'flex-end' : 'flex-start',
              background:   msg.is_mine ? '#0084ff' : '#e5e5ea',
              color:        msg.is_mine ? '#fff' : '#000',
              borderRadius: 12,
              padding:      '8px 14px',
              margin:       4,
              maxWidth:     '70%',
            }}
          >
            {msg.body && <p style={{ margin: 0 }}>{msg.body}</p>}
            {msg.attachment && (
              msg.attachment_type === 'image'
                ? <img src={msg.attachment} alt="attachment" style={{ maxWidth: 200 }} />
                : <a href={msg.attachment} target="_blank" rel="noreferrer">📎 File</a>
            )}
            <small style={{ opacity: 0.7, fontSize: 10 }}>
              {new Date(msg.created_at).toLocaleTimeString()}
              {msg.is_mine && msg.read_at && ' ✓✓'}
            </small>
          </div>
        ))}
      </div>

      {/* Input bar */}
      <div style={{ display: 'flex', padding: 8, borderTop: '1px solid #ccc' }}>
        <input
          value={text}
          onChange={e => setText(e.target.value)}
          onKeyDown={e => e.key === 'Enter' && handleSend()}
          placeholder="Type a message..."
          style={{ flex: 1, padding: '8px 12px', borderRadius: 20, border: '1px solid #ccc' }}
        />
        <button onClick={handleSend} style={{ marginLeft: 8 }}>Send</button>
      </div>
    </div>
  );
}
```

---

## 7. Broadcast Event Reference

When the other person sends a message, Reverb pushes this event:

```
Channel:    private-chat.{conversationId}
Event name: .message.sent   ← note the leading dot in Echo listener
```

```json
{
  "id": 13,
  "conversation_id": 3,
  "sender": { "id": 8, "full_name": "Ali Hassan", "profile_picture": "..." },
  "body": "Hello!",
  "attachment": null,
  "attachment_type": null,
  "read_at": null,
  "created_at": "2026-05-19T10:47:00.000000Z"
}
```

> The leading `.` in `.message.sent` in Echo tells it to use the exact event name
> without prepending the app namespace. This matches `broadcastAs(): 'message.sent'` in `MessageSent.php`.
