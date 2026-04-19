# ReviewsApp — Final Project Report

**Goal.** Build a platform that lets companies automatically analyze customer reviews with AI.

## 1. Architecture

Two decoupled layers communicating over JSON:

```
┌────────────────────┐   fetch + Bearer token   ┌──────────────────────────┐
│  Frontend (static) │ ───────────────────────► │  Laravel 12 REST API     │
│  HTML / CSS / JS   │ ◄─────────────────────── │  Sanctum · SQLite        │
└────────────────────┘       JSON responses     │                          │
                                                │  HuggingFaceService ─────┼─► Hugging Face Inference API
                                                │  (fallback: rule-based)  │
                                                └──────────────────────────┘
```

- **Backend (Laravel 12, API-only, no Blade)**
  - Sanctum personal-access tokens for authentication
  - Form Requests for validation, Policy for per-review authorization
  - Single `HuggingFaceService` encapsulates all AI logic
  - Role column on `users` (`admin` / `user`) — admin sees all reviews
- **Frontend**
  - Plain HTML + CSS + vanilla JS, `fetch` for all API calls
  - Token stored in `localStorage`, sent as `Authorization: Bearer …`
  - 4 pages: login, reviews list, add review, statistics dashboard

This split matches the subject: API-only backend, simple separate frontend, school-friendly.

## 2. Data model

```
users                             reviews
─────                             ───────
id         bigint PK              id         bigint PK
name       string                 user_id    FK → users.id (cascade)
email      unique                 content    text
password   hashed                 sentiment  string  (positive|neutral|negative)
role       string ('user'/admin)  score      float   (0..1 confidence)
timestamps                        topics     json    (["price","delivery"])
                                  timestamps
```

A `personal_access_tokens` table (Sanctum default) holds API tokens.

**Design choices.**
- JSON `topics` keeps topic storage simple without a many-to-many table.
- `sentiment`/`score` are nullable so a review object is always valid even before analysis.
- Cascading `user_id` foreign key cleans up reviews if a user is deleted.

## 3. AI method

Implemented in `app/Services/HuggingFaceService.php`:

1. **Topic extraction (local)** — a small keyword lexicon maps words to topics (`price`, `quality`, `delivery`, `service`, `product`, `website`, `refund`). Fast, deterministic, explainable.
2. **Sentiment (remote, Hugging Face)** — posts the text to `https://api-inference.huggingface.co/models/<model>`, by default `cardiffnlp/twitter-roberta-base-sentiment-latest`. Labels are normalized to `positive` / `neutral` / `negative`; `score` is the model's top-class confidence.
3. **Fallback (local)** — if the token is missing, the HTTP call times out, or the response is malformed, a simple positive/negative keyword counter produces a sentiment. This keeps the app demonstrable offline.

All HF calls happen only in the Laravel backend — the token is never exposed to the browser.

Automatic analysis is triggered in `ReviewController@store` and `@update`: the service runs before persisting, so every stored review already has `sentiment`, `score`, and `topics`.

## 4. Endpoints

| Method | Path                              | Auth | Role            | Purpose                          |
|--------|-----------------------------------|------|-----------------|----------------------------------|
| POST   | `/api/register`                   | no   | —               | Create user (role=user)          |
| POST   | `/api/login`                      | no   | —               | Get bearer token                 |
| POST   | `/api/logout`                     | yes  | any             | Revoke current token             |
| GET    | `/api/me`                         | yes  | any             | Current user                     |
| POST   | `/api/analyze`                    | yes  | any             | Analyze arbitrary text           |
| GET    | `/api/reviews`                    | yes  | user (own)/admin| List reviews                     |
| POST   | `/api/reviews`                    | yes  | any             | Create + auto-analyze            |
| GET    | `/api/reviews/{id}`               | yes  | owner or admin  | Show review                      |
| PUT    | `/api/reviews/{id}`               | yes  | owner or admin  | Update + re-analyze              |
| DELETE | `/api/reviews/{id}`               | yes  | owner or admin  | Delete review                    |
| GET    | `/api/dashboard/stats`            | yes  | any             | Percentages + average score      |
| GET    | `/api/dashboard/recent-reviews`   | yes  | any             | 5 most recent                    |
| GET    | `/api/dashboard/topics`           | yes  | any             | Top 3 topics                     |

Full request/response examples: see `API.md`.

## 5. API tests

Run after `php artisan serve`:

```bash
# 1. Login as seeded user
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password"}' | jq -r .token)

# 2. Analyze (manual)
curl -s -X POST http://localhost:8000/api/analyze \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"text":"Great product, fast delivery, good price."}'
# → { "sentiment": "positive", "score": 0.98, "topics": ["delivery","price","product"] }

# 3. Auto-analysis on create
curl -s -X POST http://localhost:8000/api/reviews \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"content":"Horrible quality, broke immediately."}'
# → sentiment: "negative", topics contain "quality"

# 4. Stats
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/dashboard/stats
# → { "total": 6, "positive_percent": 50.0, ... "average_score": 0.71 }

# 5. Authorization: user cannot delete admin's review
curl -i -X DELETE -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/reviews/3
# → 403 Forbidden
```

### Seed demo accounts

- `admin@example.com` / `password` (role: admin)
- `user@example.com`  / `password` (role: user)

## 6. Screenshots

*(Replace these placeholders with actual screenshots from your run.)*

- **Login page** — `frontend/login.html`, register + login forms side by side.
- **Reviews list** — color-coded sentiment badges (green / gray / red), topic pills, edit/delete buttons.
- **Add review** — live "Preview analysis" button hitting `POST /api/analyze`.
- **Statistics dashboard** — percentage cards, top-3 topics, recent reviews panel.

## 7. Security notes

- No secrets in frontend: only the Laravel backend talks to Hugging Face.
- Passwords hashed by Laravel's `hashed` cast.
- Policies + scoped queries enforce that non-admin users can only see/alter their own reviews.
- Validation via Form Requests at every write endpoint.
- Consistent JSON error envelope (see `bootstrap/app.php`).

## 8. How to switch models

Change `HUGGINGFACE_MODEL` in `.env` — any HF sentiment classifier that returns `[[{label, score}, ...]]` will work. The service normalizes labels containing `pos` / `neg` / `neu` (and the `LABEL_0/1/2` format used by `cardiffnlp`).

## 9. Possible extensions

- Pagination on `/api/reviews` for large datasets.
- Chart.js on the stats page.
- Queue the HF call via `dispatch()` so review creation is instant and analysis runs asynchronously.
- Bulk CSV import for companies with historical review data.
