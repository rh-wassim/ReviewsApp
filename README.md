# ReviewsApp — AI Customer Review Analysis

Laravel 12 REST API + vanilla HTML/CSS/JS frontend. Reviews are automatically analyzed with a Hugging Face sentiment model; a rule-based fallback guarantees the app works offline.

## Stack

- Backend: Laravel 12, Sanctum (token auth), SQLite
- AI: Hugging Face Inference API (`cardiffnlp/twitter-roberta-base-sentiment-latest`) + rule-based fallback
- Frontend: static HTML/CSS/JS with `fetch`

## Project structure

```
ReviewsApp/
├── backend/                 # Laravel 12 API
│   ├── app/
│   │   ├── Http/Controllers/Api/   # Auth, Review, Analyze, Dashboard
│   │   ├── Http/Requests/          # Form Requests (validation)
│   │   ├── Http/Middleware/        # EnsureUserRole
│   │   ├── Models/                 # User, Review
│   │   ├── Policies/               # ReviewPolicy
│   │   └── Services/               # HuggingFaceService
│   ├── config/                     # services.php (HF), sanctum, cors
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/DatabaseSeeder.php
│   │   └── factories/
│   ├── routes/api.php
│   ├── bootstrap/app.php
│   ├── .env.example
│   └── composer.json
├── frontend/
│   ├── login.html
│   ├── reviews.html
│   ├── add-review.html
│   ├── stats.html
│   ├── js/api.js
│   └── css/styles.css
├── API.md
├── REPORT.md
└── README.md
```

## Quick start (after cloning)

Requirements: PHP 8.2+, Composer, Python 3 (for the static frontend server).

```bash
./setup.sh    # installs deps, creates .env + SQLite DB, runs migrations + seeders
./start.sh    # runs the API (:8000) and the frontend (:5500) together
```

Then open http://localhost:5500/login.html and log in with `admin@example.com` / `password`.

To use a Hugging Face model instead of the rule-based fallback, add `HUGGINGFACE_API_TOKEN=hf_xxx` to `backend/.env`.

## Manual setup (backend)

If you prefer to run the steps yourself:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve    # http://localhost:8000
```

### Using MySQL instead of SQLite

In `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reviewsapp
DB_USERNAME=root
DB_PASSWORD=
```

Then: `php artisan migrate:fresh --seed`.

## Setup (frontend)

Just open `frontend/login.html` in the browser, or serve it:

```bash
cd frontend
python3 -m http.server 5500
# open http://localhost:5500/login.html
```

Edit `frontend/js/api.js` if your Laravel server is not on `http://localhost:8000`.

## Demo accounts (from seeder)

| Email               | Password | Role  |
|---------------------|----------|-------|
| admin@example.com   | password | admin |
| user@example.com    | password | user  |

## Hugging Face configuration

- `HUGGINGFACE_API_TOKEN` — personal access token from https://huggingface.co/settings/tokens
- `HUGGINGFACE_MODEL` — defaults to `cardiffnlp/twitter-roberta-base-sentiment-latest`

If the token is missing or the API times out / errors, the app logs a warning and uses rule-based fallback. The user-facing API always returns a valid sentiment.

## Verify it works

```bash
# Register
curl -X POST http://localhost:8000/api/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Test","email":"t@t.com","password":"secret123","password_confirmation":"secret123"}'

# Login (save token from response)
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password"}' | jq -r .token)

# Manual analyze endpoint
curl -X POST http://localhost:8000/api/analyze \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"text":"Great product, fast delivery!"}'

# Create review (auto-analyzed)
curl -X POST http://localhost:8000/api/reviews \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"content":"Terrible quality, broke immediately."}'

# List reviews
curl http://localhost:8000/api/reviews -H "Authorization: Bearer $TOKEN"

# Dashboard stats
curl http://localhost:8000/api/dashboard/stats -H "Authorization: Bearer $TOKEN"
```

See [API.md](API.md) for the full endpoint reference and [REPORT.md](REPORT.md) for the project report.
