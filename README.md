# ReviewsApp — Analyse IA des avis clients

API REST Laravel 12 + frontend HTML/CSS/JS. Les avis sont analysés automatiquement via un modèle de sentiment Hugging Face ; un fallback à base de règles garantit que l'application fonctionne même hors ligne.

## Stack technique

- Backend : Laravel 12, Sanctum (authentification par token), SQLite
- IA : API d'inférence Hugging Face (`cardiffnlp/twitter-roberta-base-sentiment-latest`) + fallback basé sur des règles
- Frontend : HTML/CSS/JS statique avec `fetch`

## Structure du projet

```
ReviewsApp/
├── backend/                 # API Laravel 12
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

## Démarrage rapide (après clonage)

Prérequis : PHP 8.2+, Composer, Python 3 (pour servir le frontend statique).

```bash
./setup.sh    # installe les dépendances, crée le .env + la base SQLite, lance migrations + seeders
./start.sh    # lance l'API (:8000) et le frontend (:5500) en même temps
```

Ouvre ensuite http://localhost:5500/login.html et connecte-toi avec `admin@example.com` / `password`.

Pour utiliser un vrai modèle Hugging Face plutôt que le fallback, ajoute `HUGGINGFACE_API_TOKEN=hf_xxx` dans `backend/.env`.

## Installation manuelle (backend)

Si tu préfères exécuter les étapes une par une :

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve    # http://localhost:8000
```

### Utiliser MySQL au lieu de SQLite

Dans `.env` :

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reviewsapp
DB_USERNAME=root
DB_PASSWORD=
```

Puis : `php artisan migrate:fresh --seed`.

## Installation (frontend)

Ouvre simplement `frontend/login.html` dans le navigateur, ou sers les fichiers :

```bash
cd frontend
python3 -m http.server 5500
# ouvrir http://localhost:5500/login.html
```

Modifie `frontend/js/api.js` si ton serveur Laravel n'est pas sur `http://localhost:8000`.

## Comptes de démonstration (créés par le seeder)

| Email               | Mot de passe | Rôle  |
|---------------------|--------------|-------|
| admin@example.com   | password     | admin |
| user@example.com    | password     | user  |

## Configuration Hugging Face

- `HUGGINGFACE_API_TOKEN` — token personnel depuis https://huggingface.co/settings/tokens
- `HUGGINGFACE_MODEL` — valeur par défaut : `cardiffnlp/twitter-roberta-base-sentiment-latest`

Si le token est absent, si l'API dépasse le timeout ou renvoie une erreur, l'application logge un avertissement et bascule sur le fallback local. L'API publique retourne toujours un sentiment valide.

## Vérifier que tout fonctionne

```bash
# Inscription
curl -X POST http://localhost:8000/api/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Test","email":"t@t.com","password":"secret123","password_confirmation":"secret123"}'

# Connexion (récupérer le token depuis la réponse)
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password"}' | jq -r .token)

# Endpoint d'analyse manuelle
curl -X POST http://localhost:8000/api/analyze \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"text":"Super produit, livraison rapide !"}'

# Création d'un avis (analyse automatique)
curl -X POST http://localhost:8000/api/reviews \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"content":"Qualité catastrophique, cassé dès la première utilisation."}'

# Liste des avis
curl http://localhost:8000/api/reviews -H "Authorization: Bearer $TOKEN"

# Statistiques du tableau de bord
curl http://localhost:8000/api/dashboard/stats -H "Authorization: Bearer $TOKEN"
```

Consulte [API.md](API.md) pour la documentation complète des endpoints et [REPORT.md](REPORT.md) pour le rapport du projet.

## Auteurs

- **Wassim RHILANE** — Backend (squelette Laravel, reviews, IA HuggingFace, base de données, tests)
- **Ilyasse DBIZA** — Authentification (Sanctum, rôles), dashboard, frontend, documentation
