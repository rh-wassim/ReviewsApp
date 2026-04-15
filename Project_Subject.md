Projet final de l'école, module full stack

Objectif du projet

Développer une plateforme permettant à des entreprises d’analyser automatiquement les avis de leurs clients.
Le système doit :
    • Stocker et gérer des avis,
    • Analyser chaque avis grâce à une méthode d’IA côté backend,
    • Exposer une API REST Laravel pour toutes les opérations,
    • Fournir un frontend séparé (simple) qui consomme l’API.

Architecture imposée
    • Backend
        o Framework : Laravel 12
        o Structure : 100% API REST (pas de Blade)
        o Authentification
        o IA : intégrée dans un service Laravel (pas besoin de vrai ML, modèle pré-entraîné via API externe)
    • Frontend
        o HTML/CSS/JS simple (fetch API)
        o Communication : API uniquement

Partie IA obligatoire
Le backend doit intégrer une IA (simple mais réelle).

    — Analyse de sentiment (positive / neutre / négative)
    Technique simple NLP basée sur :
        • Appel à une API externe (HuggingFace)

Endpoint IA (obligatoire)
    POST /api/analyze
    Body: { "text": "..." }
    Response:
    {
    "sentiment": "positive",
    "score": 87,
    "topics": ["delivery", "speed"]
    }

Fonctionnalités à implémenter
    1. Gestion Utilisateur
        • Inscription / login via API
        • Rôles possibles : admin, user

    2. Gestion des avis
        • CRUD complet :
            o POST /api/reviews (soumission d’un avis)
            o GET /api/reviews (liste)
            o GET /api/reviews/{id}
            o PUT /api/reviews/{id}
            o DELETE /api/reviews/{id}

    Chaque avis possède :
        • id
        • user_id
        • content (texte)
        • sentiment (issu de l’IA)
        • score (issu de l’IA)
        • topics (JSON)
        • created_at

    3. Analyse IA
        • L’analyse doit se déclencher : 
            o Automatiquement à la création d’un avis

    4. Tableau de bord
        • Statistiques globales via API :
            o % d’avis positifs / négatifs
            o Top 3 thèmes détectés
            o Note moyenne
            o Avis les plus récents

Frontend attendu
    — Simple (HTML/JS)
        • Page login
        • Page liste d’avis
        • Page ajouter un avis
        • Page statistiques (graphiques facultatifs)

Livrables attendus
    1. Code Laravel complet (API REST)
    2. Documentation API
    3. Frontend séparé consommant les endpoints
    4. Rapport reprenant :
        o Architecture
        o Modèle de données
        o Méthode IA
        o Tests API
        o Screenshots