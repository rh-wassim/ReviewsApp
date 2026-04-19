# ReviewsApp — Rapport de projet

**Objectif.** Concevoir une plateforme permettant à des entreprises d'analyser automatiquement les avis de leurs clients grâce à l'IA.

## 1. Architecture

Deux couches découplées communiquant en JSON :

```
┌────────────────────┐   fetch + token Bearer   ┌──────────────────────────┐
│  Frontend statique │ ───────────────────────► │  API REST Laravel 12     │
│  HTML / CSS / JS   │ ◄─────────────────────── │  Sanctum · SQLite        │
└────────────────────┘       réponses JSON      │                          │
                                                │  HuggingFaceService ─────┼─► API Hugging Face
                                                │  (fallback : règles)     │
                                                └──────────────────────────┘
```

- **Backend (Laravel 12, 100 % API, sans Blade)**
  - Tokens d'accès personnels Sanctum pour l'authentification
  - Form Requests pour la validation, Policy pour les droits par avis
  - Un unique `HuggingFaceService` encapsule toute la logique IA
  - Colonne `role` sur `users` (`admin` / `user`) — l'admin voit tous les avis
- **Frontend**
  - HTML + CSS + JavaScript vanilla, `fetch` pour tous les appels API
  - Token stocké dans `localStorage`, envoyé en `Authorization: Bearer …`
  - 4 pages : connexion, liste des avis, ajout d'un avis, tableau de bord statistiques

Cette séparation respecte le sujet : backend 100 % API, frontend simple découplé, facile à évaluer.

## 2. Modèle de données

```
users                             reviews
─────                             ───────
id         bigint PK              id         bigint PK
name       string                 user_id    FK → users.id (cascade)
email      unique                 content    text
password   hashé                  sentiment  string  (positive|neutral|negative)
role       string ('user'/admin)  score      float   (0..1 confiance)
timestamps                        topics     json    (["price","delivery"])
                                  timestamps
```

Une table `personal_access_tokens` (fournie par Sanctum) stocke les tokens API.

**Choix de conception.**
- Le champ JSON `topics` évite une table many-to-many tout en restant simple.
- `sentiment`/`score` sont nullables : un avis reste valide même avant analyse.
- La clé étrangère `user_id` en cascade supprime les avis d'un utilisateur supprimé.

## 3. Méthode IA

Implémentée dans `app/Services/HuggingFaceService.php` :

1. **Extraction des thèmes (locale)** — un petit lexique de mots-clés associe les mots à des thèmes (`price`, `quality`, `delivery`, `service`, `product`, `website`, `refund`). Rapide, déterministe, explicable.
2. **Sentiment (distant, Hugging Face)** — requête POST vers `https://api-inference.huggingface.co/models/<modèle>`, par défaut `cardiffnlp/twitter-roberta-base-sentiment-latest`. Les labels sont normalisés en `positive` / `neutral` / `negative` ; `score` correspond à la confiance de la classe dominante.
3. **Fallback (local)** — si le token est absent, si la requête HTTP timeout ou si la réponse est malformée, un simple compteur de mots positifs/négatifs produit un sentiment. L'application reste donc démontrable hors ligne.

Tous les appels Hugging Face se font uniquement depuis le backend Laravel — le token n'est jamais exposé au navigateur.

L'analyse automatique est déclenchée dans `ReviewController@store` et `@update` : le service s'exécute avant la persistance, donc tout avis stocké possède déjà son `sentiment`, son `score` et ses `topics`.

## 4. Endpoints

| Méthode | Chemin                              | Auth | Rôle            | Objet                            |
|---------|-------------------------------------|------|-----------------|----------------------------------|
| POST    | `/api/register`                     | non  | —               | Créer un utilisateur (role=user) |
| POST    | `/api/login`                        | non  | —               | Récupérer un token Bearer        |
| POST    | `/api/logout`                       | oui  | tout            | Révoquer le token courant        |
| GET     | `/api/me`                           | oui  | tout            | Utilisateur courant              |
| POST    | `/api/analyze`                      | oui  | tout            | Analyser un texte arbitraire     |
| GET     | `/api/reviews`                      | oui  | user (siens)/admin | Lister les avis               |
| POST    | `/api/reviews`                      | oui  | tout            | Créer + analyser automatiquement |
| GET     | `/api/reviews/{id}`                 | oui  | propriétaire ou admin | Afficher un avis           |
| PUT     | `/api/reviews/{id}`                 | oui  | propriétaire ou admin | Modifier + ré-analyser     |
| DELETE  | `/api/reviews/{id}`                 | oui  | propriétaire ou admin | Supprimer un avis          |
| GET     | `/api/dashboard/stats`              | oui  | tout            | Pourcentages + note moyenne      |
| GET     | `/api/dashboard/recent-reviews`     | oui  | tout            | 5 avis les plus récents          |
| GET     | `/api/dashboard/topics`             | oui  | tout            | Top 3 des thèmes                 |

Exemples complets de requêtes/réponses : voir `API.md`.

## 5. Tests de l'API

À exécuter après `php artisan serve` :

```bash
# 1. Connexion avec un utilisateur du seeder
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password"}' | jq -r .token)

# 2. Analyse manuelle
curl -s -X POST http://localhost:8000/api/analyze \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"text":"Super produit, livraison rapide, bon prix."}'
# → { "sentiment": "positive", "score": 0.98, "topics": ["delivery","price","product"] }

# 3. Analyse automatique à la création
curl -s -X POST http://localhost:8000/api/reviews \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"content":"Qualité catastrophique, cassé dès la première utilisation."}'
# → sentiment: "negative", topics contient "quality"

# 4. Statistiques
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/dashboard/stats
# → { "total": 6, "positive_percent": 50.0, ... "average_score": 0.71 }

# 5. Droits d'accès : un user ne peut pas supprimer l'avis d'un admin
curl -i -X DELETE -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/reviews/3
# → 403 Forbidden
```

### Comptes de démonstration

- `admin@example.com` / `password` (rôle : admin)
- `user@example.com`  / `password` (rôle : user)

## 6. Captures d'écran

*(À remplacer par de vraies captures prises lors de l'exécution.)*

- **Page de connexion** — `frontend/login.html`, formulaires inscription et connexion côte à côte.
- **Liste des avis** — badges de sentiment colorés (vert / gris / rouge), pastilles de thèmes, boutons modifier/supprimer.
- **Ajout d'un avis** — bouton « Aperçu de l'analyse » qui appelle `POST /api/analyze` en direct.
- **Tableau de bord** — cartes de pourcentages, top 3 des thèmes, panneau des avis récents.

## 7. Sécurité

- Aucun secret côté frontend : seul le backend Laravel communique avec Hugging Face.
- Mots de passe hashés via le cast `hashed` de Laravel.
- Policies + scopes de requête garantissent qu'un utilisateur non-admin ne voit/modifie que ses propres avis.
- Validation via Form Requests à chaque endpoint d'écriture.
- Enveloppe d'erreur JSON cohérente (voir `bootstrap/app.php`).

## 8. Changer de modèle IA

Modifie `HUGGINGFACE_MODEL` dans le `.env` — n'importe quel classifieur de sentiment Hugging Face renvoyant `[[{label, score}, ...]]` fonctionne. Le service normalise les labels contenant `pos` / `neg` / `neu` (ainsi que le format `LABEL_0/1/2` utilisé par `cardiffnlp`).

## 9. Pistes d'amélioration

- Pagination sur `/api/reviews` pour les gros volumes.
- Chart.js sur la page statistiques.
- Dispatcher l'appel HF via une file `dispatch()` pour une création d'avis instantanée, l'analyse se faisant en tâche de fond.
- Import CSV en masse pour les entreprises ayant un historique d'avis.

## 10. Répartition du travail

- **Wassim RHILANE** — Squelette Laravel, module reviews (CRUD, policy), service IA HuggingFace (endpoint `/analyze`, analyse automatique), base de données (migrations, seeders, factories), tests, scripts de lancement.
- **Ilyasse DBIZA** — Authentification (Sanctum, rôles, middleware), tableau de bord statistiques, frontend complet (pages HTML, styles, wrapper fetch), documentation (API.md, ce rapport).
