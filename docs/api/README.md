# API de Scubanion

Ce dossier est le contrat entre l'API (`scubatana.backend`) et l'app (`scubatana.frontend`) : chaque route, ce qu'elle attend et ce qu'elle renvoie. Une PR qui ajoute ou change une route met à jour le fichier de son domaine dans le même dossier, un fichier par domaine.

## Conventions

- **Adresse** : `{API}/api/...`. En dev, `{API}` vaut `http://localhost:8000` ; en prod, l'adresse du serveur. Dans l'app, elle vient de `NUXT_PUBLIC_API_URL`.
- **Format** : JSON dans les deux sens. Envoyer `Accept: application/json` (et `Content-Type: application/json` avec un corps).
- **Authentification** : toutes les routes sauf `POST /auth/code` et `POST /auth/verify` demandent le jeton obtenu à la connexion, dans l'en-tête `Authorization: Bearer <jeton>`. Un jeton n'expire pas, mais il est révoqué à la déconnexion et quand le joueur se connecte sur un autre téléphone.
- **Dates** : jour au format `AAAA-MM-JJ`, mois au format `AAAA-MM`, à l'heure de Paris.

## Erreurs

| Code | Quand | Corps |
| ---- | ----- | ----- |
| 401 | Jeton absent, invalide ou révoqué | `{"message": "Unauthenticated."}` |
| 409 | Action impossible dans l'état actuel (ex. personnaliser un compagnon avant d'avoir un profil) | `{"message": "<texte affichable>"}` |
| 422 | Données refusées | `{"message": "...", "errors": {"<champ>": ["<texte affichable>"]}}` |
| 429 | Trop de requêtes (limites de la connexion) | `{"message": "Too Many Attempts."}` |

Les messages de `errors` sont en français et peuvent être affichés tels quels sous le champ concerné.

## Domaines

Un fichier par domaine dans ce dossier. Les premiers :

- [Connexion et compte](compte.md) : code par email, profil, joueur.
- [Compagnon](compagnon.md) : apparence et catalogue.
- [Pas](pas.md) : envoi des pas, historique, objectif.

Chaque nouvelle fonctionnalité (amis, quêtes, bornes…) ajoute son propre fichier, sans toucher à cette liste, pour que les PR en parallèle ne se gênent pas.
