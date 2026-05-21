# S-Hub Chat — Flutter Integration Guide

## Credentials

```dart
const String REVERB_APP_KEY = 'shub_reverb_key';
const String REVERB_HOST    = '192.168.101.117'; // Your server IP
const int    REVERB_PORT    = 8080;
const String API_BASE_URL   = 'http://192.168.101.117:8000/api/v1';
```

> ⚠️ In production replace IP with your domain. Use port `443` + `useTLS: true` for HTTPS.

---

## 1. pubspec.yaml Dependencies

```yaml
dependencies:
  pusher_channels_flutter: ^2.1.0
  http: ^1.2.0
  dio: ^5.4.0
```

```bash
flutter pub get
```

Add to `android/app/src/main/AndroidManifest.xml`:
```xml
<uses-permission android:name="android.permission.INTERNET"/>
```

---

## 2. ChatService — `lib/services/chat_service.dart`

```dart
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class ChatService {
  static const _base   = 'http://192.168.101.117:8000/api/v1';
  static const _appKey = 'shub_reverb_key';
  static const _host   = '192.168.101.117';
  static const _port   = 8080;

  final String token;
  PusherChannelsFlutter? _pusher;

  ChatService({required this.token});

  Map<String, String> get _headers => {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };

  // GET /chat/users — contact list (role-filtered)
  Future<List<dynamic>> getContacts() async {
    final res  = await http.get(Uri.parse('$_base/chat/users'), headers: _headers);
    final body = jsonDecode(res.body);
    if (body['success']) return body['data'];
    throw Exception(body['message']);
  }

  // POST /chat/conversations — start or find conversation
  Future<Map<String, dynamic>> startConversation(int recipientId) async {
    final res  = await http.post(Uri.parse('$_base/chat/conversations'),
        headers: _headers, body: jsonEncode({'recipient_id': recipientId}));
    final body = jsonDecode(res.body);
    if (body['success']) return body['data'];
    throw Exception(body['message']);
  }

  // GET /chat/conversations — inbox
  Future<List<dynamic>> getInbox() async {
    final res  = await http.get(Uri.parse('$_base/chat/conversations'), headers: _headers);
    final body = jsonDecode(res.body);
    if (body['success']) return body['data'];
    throw Exception(body['message']);
  }

  // GET /chat/conversations/{id} — messages (auto-marks read)
  Future<Map<String, dynamic>> getMessages(int id, {int page = 1}) async {
    final res  = await http.get(Uri.parse('$_base/chat/conversations/$id?page=$page'), headers: _headers);
    final body = jsonDecode(res.body);
    if (body['success']) return body['data'];
    throw Exception(body['message']);
  }

  // POST /chat/conversations/{id}/messages — send text
  Future<Map<String, dynamic>> sendMessage(int id, String text) async {
    final res  = await http.post(Uri.parse('$_base/chat/conversations/$id/messages'),
        headers: _headers, body: jsonEncode({'body': text}));
    final body = jsonDecode(res.body);
    if (body['success']) return body['data'];
    throw Exception(body['message']);
  }

  // POST /chat/conversations/{id}/messages — send file/image
  Future<Map<String, dynamic>> sendAttachment(int id, File file, {String? text}) async {
    final req = http.MultipartRequest('POST', Uri.parse('$_base/chat/conversations/$id/messages'));
    req.headers.addAll({'Authorization': 'Bearer $token', 'Accept': 'application/json'});
    if (text != null) req.fields['body'] = text;
    req.files.add(await http.MultipartFile.fromPath('attachment', file.path));
    final res  = await http.Response.fromStream(await req.send());
    final body = jsonDecode(res.body);
    if (body['success']) return body['data'];
    throw Exception(body['message']);
  }

  // PATCH /chat/conversations/{id}/read — mark as read
  Future<void> markAsRead(int id) async {
    await http.patch(Uri.parse('$_base/chat/conversations/$id/read'), headers: _headers);
  }

  // Connect to Reverb WebSocket server
  Future<void> connect() async {
    _pusher = PusherChannelsFlutter.getInstance();
    await _pusher!.init(
      apiKey:  _appKey,
      cluster: 'mt1',          // Required by SDK, ignored by Reverb
      host:    _host,          // Your server, NOT pusher.com
      wsPort:  _port,
      useTLS:  false,          // Set true in production (HTTPS)
      authEndpoint: 'http://$_host:8000/broadcasting/auth',
      authParams: {
        'headers': {'Authorization': 'Bearer $token'},
      },
    );
    await _pusher!.connect();
  }

  // Subscribe to a conversation's private channel
  // Fires [onMessage] when the other person sends a message in real-time
  Future<void> subscribe(int conversationId, void Function(Map<String, dynamic>) onMessage) async {
    await _pusher!.subscribe(
      channelName: 'private-chat.$conversationId',
      onEvent: (event) {
        if (event.eventName == 'message.sent') {
          onMessage(jsonDecode(event.data ?? '{}'));
        }
      },
    );
  }

  Future<void> unsubscribe(int conversationId) async {
    await _pusher!.unsubscribe(channelName: 'private-chat.$conversationId');
  }

  Future<void> disconnect() async => _pusher!.disconnect();
}
```

---

## 3. Chat Screen Usage

```dart
class _ChatScreenState extends State<ChatScreen> {
  late final ChatService _service;
  List<Map<String, dynamic>> _messages = [];

  @override
  void initState() {
    super.initState();
    _service = ChatService(token: widget.token);
    _load();
  }

  Future<void> _load() async {
    final data = await _service.getMessages(widget.conversationId);
    setState(() => _messages = List<Map<String, dynamic>>.from(data['messages']));

    await _service.connect();
    await _service.subscribe(widget.conversationId, (msg) {
      setState(() => _messages.insert(0, msg)); // real-time: insert at top
    });
  }

  @override
  void dispose() {
    _service.unsubscribe(widget.conversationId);
    super.dispose();
  }
}
```

---

## 4. Broadcast Event Payload (what arrives via WebSocket)

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

Event name to listen for: **`message.sent`**  
Channel: **`private-chat.{conversation_id}`**
