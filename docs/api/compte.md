# Connexion et compte

## Connexion par email et code

La connexion se fait en deux temps (décision D1) : le joueur demande un code à 6 chiffres par email, puis le renvoie. Il n'y a pas de mot de passe. Un compte est créé à la première vérification réussie, à condition que les conditions d'utilisation soient acceptées.

### `POST /auth/code`

Envoie un code par email. Sans authentification.

| Champ | Règle |
| ----- | ----- |
| `email` | obligatoire, email valide, 255 caractères au plus. Espaces et majuscules sont ignorés. |

Réponse **204**, que l'adresse ait un compte ou non. Le code est valable 10 minutes ; en demander un nouveau annule le précédent.

Limites : 5 demandes par heure et par adresse, 10 par heure et par adresse IP, puis **429**.

### `POST /auth/verify`

Vérifie le code et ouvre une session. Sans authentification.

| Champ | Règle |
| ----- | ----- |
| `email` | l'adresse utilisée pour demander le code |
| `code` | les 6 chiffres. Les espaces et tirets sont ignorés. |
| `terms_accepted` | booléen, obligatoire à `true` pour créer un compte, ignoré pour un compte existant |

Réponse **200** :

```json
{
  "token": "1|Xq3...",
  "player": { "...": "voir Joueur ci-dessous" }
}
```

Erreurs **422** :
- `code` : code faux, expiré ou déjà utilisé. La réponse est la même que le compte existe ou non. Après 5 essais faux, le code est grillé.
- `terms_accepted` : nouveau compte sans acceptation des conditions. Le code n'est pas consommé : le joueur peut cocher la case et renvoyer le même code.

Se connecter révoque les jetons des autres téléphones du joueur : une seule session active par joueur.

Limite : 20 vérifications par minute et par adresse IP, puis **429**.

### `POST /auth/logout`

Révoque le jeton utilisé. Réponse **204**.

## Joueur

### `GET /me`

Le joueur, son compagnon et ses pas du jour : c'est l'appel de l'écran d'accueil. Le serveur recalcule au passage le niveau, l'XP, l'humeur et la santé du compagnon.

```json
{
  "player": {
    "id": 7,
    "email": "lea@example.com",
    "pseudo": "Lea",
    "birth_date": "2004-05-12",
    "gender": "female",
    "ambition": "fitness",
    "profile_completed": true,
    "stats": { "total_steps": 48210, "quests_done": 0, "friends_count": 0 }
  },
  "companion": { "...": "voir compagnon.md" },
  "today": { "date": "2026-10-13", "steps": 3200, "goal": 5000 },
  "streak": { "days": 4, "active": false }
}
```

- `gender` vaut `female`, `male`, `other` ou `null` ; `ambition` vaut `fitness`, `go_out`, `meet_people` ou `explore` (ou `null` avant le profil).
- `profile_completed` est faux tant que le joueur n'a pas de pseudo : l'app l'envoie alors sur l'écran de profil. `companion` vaut `null` jusque-là.
- `streak.days` compte les jours consécutifs où l'objectif est atteint ; `streak.active` dit si aujourd'hui en fait déjà partie.
- `stats.friends_count` compte les amitiés acceptées ([amis.md](amis.md)). `stats.quests_done` compte les quêtes terminées par le joueur (`user_quests.completed_at` renseigné).

### `PUT /me/profile`

Enregistre le profil et crée le compagnon de départ s'il n'existe pas.

| Champ | Règle |
| ----- | ----- |
| `pseudo` | obligatoire, 3 à 20 caractères, unique |
| `birth_date` | obligatoire, `AAAA-MM-JJ`, au moins 15 ans |
| `gender` | `female`, `male`, `other` ou `null` |
| `ambition` | obligatoire : `fitness`, `go_out`, `meet_people` ou `explore` |

Réponse **200** : `{ "player": {...}, "companion": {...} }`.

### `DELETE /me?confirm=1`

Supprime le compte et toutes ses données : compagnon, pas, quêtes et leur avancement, récompenses, accessoires débloqués, amitiés, passages en borne, position, codes de connexion et jetons (tous les téléphones sont déconnectés).

- La confirmation passe dans l'adresse (`confirm=1`), car certains clients n'envoient pas de corps avec un `DELETE`.
- Réponse **204**. **422** sur `confirm` sans confirmation explicite : rien n'est supprimé.
- Après la suppression, l'app oublie la session, l'email en attente et les pas gardés sur le téléphone, puis revient à l'écran de connexion.
