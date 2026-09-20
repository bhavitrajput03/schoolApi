# Teacher Chat API

All endpoints require a Teacher access token in `Authorization: Bearer <accessToken>`.
The backend always limits conversations to the authenticated teacher's `ApiUserID`.

## 1. Teacher inbox

`GET /api/teacher/chat/conversations`

Returns Parent-created conversations with `conversationId`, student/parent identity, latest message and unread count.

```json
{
  "success": true,
  "data": {
    "conversations": [
      {
        "conversationId": 1,
        "studentId": 14687,
        "studentName": "ARYAN",
        "admissionNo": "3573",
        "parentId": 1,
        "parentName": "PRAVEEN",
        "lastMessage": "Please call me.",
        "lastSenderType": "parent",
        "lastMessageAt": "2026-09-19T10:20:00",
        "unreadCount": 1
      }
    ]
  }
}
```

## 2. Conversation messages

`GET /api/teacher/chat/conversations/{conversationId}/messages`

## 3. Reply to parent

`POST /api/teacher/chat/conversations/{conversationId}/messages`

```json
{
  "message": "Thank you. I have received your message."
}
```

## 4. Mark parent messages read

`POST /api/teacher/chat/conversations/{conversationId}/read`

```json
{}
```

Recommended refresh flow: fetch inbox, open a `conversationId`, fetch its messages, mark it read, and post replies to the same conversation.
