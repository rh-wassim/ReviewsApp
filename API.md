# API Documentation

Base URL: `http://localhost:8000/api`
Auth: Bearer token from Sanctum. Send `Authorization: Bearer <token>` on protected routes.

## Authentication

### POST /api/register
Request:
```json
{ "name": "Alice", "email": "a@a.com", "password": "secret123", "password_confirmation": "secret123" }
```
Response `201`:
```json
{ "user": { "id": 3, "name": "Alice", "email": "a@a.com", "role": "user" }, "token": "1|abc..." }
```

### POST /api/login
Request: `{ "email": "...", "password": "..." }`
Response `200`: `{ "user": {...}, "token": "..." }`
Failure `422`: `{ "message": "Validation failed", "errors": { "email": ["Invalid credentials."] } }`

### POST /api/logout *(auth)*
Response: `{ "message": "Logged out" }`

### GET /api/me *(auth)*
Response: `{ "user": { "id": 1, "name": "...", "email": "...", "role": "user" } }`

## AI Analysis

### POST /api/analyze *(auth)*
Request: `{ "text": "Great product!" }`
Response:
```json
{ "sentiment": "positive", "score": 0.98, "topics": ["product"] }
```

Always returns a valid response even if the Hugging Face API is unreachable (fallback is rule-based).

## Reviews (CRUD) *(auth)*

Normal users see/manage **only their own** reviews. Admins see/manage **all**.

### GET /api/reviews
Response:
```json
{ "data": [
  { "id": 1, "user_id": 2, "content": "...", "sentiment": "positive",
    "score": 0.95, "topics": ["delivery","quality"],
    "created_at": "2026-04-18T10:00:00.000000Z", "updated_at": "...",
    "user": { "id": 2, "name": "Demo User", "email": "user@example.com" } }
] }
```

### POST /api/reviews
Request: `{ "content": "The service was excellent and fast." }`
Response `201`:
```json
{ "id": 6, "user_id": 2, "content": "...", "sentiment": "positive",
  "score": 0.97, "topics": ["service"], "created_at": "...", "updated_at": "..." }
```
The review is automatically analyzed at creation time.

### GET /api/reviews/{id}
Response: review object (404 if not found, 403 if not owner and not admin).

### PUT /api/reviews/{id}
Request: `{ "content": "updated text..." }`
Re-runs analysis and updates sentiment/score/topics.

### DELETE /api/reviews/{id}
Response: `{ "message": "Deleted" }`

## Dashboard *(auth)*

### GET /api/dashboard/stats
```json
{
  "total": 5,
  "positive_count": 3, "negative_count": 1, "neutral_count": 1,
  "positive_percent": 60.0, "negative_percent": 20.0, "neutral_percent": 20.0,
  "average_score": 0.7123
}
```

### GET /api/dashboard/recent-reviews
5 most recent reviews (same scoping as `GET /api/reviews`).

### GET /api/dashboard/topics
Top 3 detected topics:
```json
{ "data": [ { "topic": "delivery", "count": 4 }, { "topic": "quality", "count": 3 }, { "topic": "price", "count": 2 } ] }
```

## Error format

All `/api/*` errors return JSON:

| Status | Shape |
|-------:|-------|
| 401 | `{ "message": "Unauthenticated" }` |
| 403 | `{ "message": "Forbidden" }` |
| 404 | `{ "message": "Not found" }` |
| 422 | `{ "message": "Validation failed", "errors": { "field": ["..."] } }` |
| 500 | `{ "message": "Server error", "error": "..." }` |

## Testing guide

### With curl
See examples in [README.md](README.md).

### With Postman
1. Create an environment variable `token`.
2. Send `POST {{base}}/login` with body `{"email":"user@example.com","password":"password"}`. In the test tab: `pm.environment.set("token", pm.response.json().token)`.
3. On the other requests, add header `Authorization: Bearer {{token}}`.

### Verifying Hugging Face integration
- With token configured: first call may take 5–15s (cold model). Subsequent calls are fast.
- `storage/logs/laravel.log` logs a warning if the HF API fails — the response still succeeds via fallback.
- Temporarily blank `HUGGINGFACE_API_TOKEN` to confirm fallback; sentiment still returned.

### Verifying automatic analysis
```bash
curl -X POST http://localhost:8000/api/reviews \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"content":"absolutely terrible, broke in one day"}'
# → sentiment: "negative", topics include "quality"
```
