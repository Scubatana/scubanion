# Amis

Chaque joueur a un **code ami** de 8 caractères (lettres et chiffres, sans `0`, `O`, `1` ni `I` pour éviter les confusions), donné à la création du compte. Pour devenir amis, un joueur saisit le code de l'autre, qui accepte l'invitation (APP-07).

Les quatre routes renvoient le même objet, la vue complète des amis du joueur : l'app n'a qu'à réafficher la réponse après chaque action.

```json
{
  "code": "K7Q2MZ4P",
  "friends": [
    {
      "id": 12,
      "since": "2026-10-13",
      "player": {
        "id": 3,
        "pseudo": "Yanis",
        "companion": { "name": "Bulle", "species": "dog", "color": "sand", "accessories": ["cap"], "level": 3 },
        "today": { "date": "2026-10-13", "steps": 4200, "goal": 5000 },
        "streak": { "days": 2, "active": false }
      }
    }
  ],
  "received": [
    { "id": 13, "sent_at": "2026-10-13T15:00:00+02:00", "player": { "id": 9, "pseudo": "Lina" } }
  ],
  "sent": [
    { "id": 14, "sent_at": "2026-10-13T14:30:00+02:00", "player": { "id": 5, "pseudo": "Tom" } }
  ]
}
```

- `code` : le code ami du joueur, à partager.
- `friends` : les amitiés acceptées, avec le compagnon et les pas du jour de chaque ami. `companion` vaut `null` si l'ami n'en a pas encore.
- `received` : les invitations reçues, à accepter ou refuser. `sent` : les invitations envoyées, en attente.
- `id` est l'identifiant de l'amitié ou de l'invitation, à utiliser dans les routes ci-dessous.

## `GET /me/friends`

La vue des amis du joueur.

## `POST /me/friends`

Invite un joueur par son code.

| Champ | Règle |
| ----- | ----- |
| `code` | obligatoire. Majuscules, minuscules, espaces et tirets sont acceptés. |

- Si l'autre joueur avait déjà invité ce joueur, l'invitation est acceptée directement : ils deviennent amis.
- **422** sur `code` : code inconnu (ou joueur sans profil), son propre code, invitation déjà envoyée, ou déjà amis.
- **409** si le joueur n'a pas encore rempli son profil.
- Limite : 20 invitations par minute et par joueur, puis **429**.

## `POST /me/friends/{id}/accept`

Accepte une invitation reçue. **409** si le joueur n'est pas celui qui a été invité, ou si l'invitation est déjà acceptée. **404** si le joueur n'est pas concerné.

## `DELETE /me/friends/{id}`

Supprime le lien, quel que soit son état : refuser une invitation reçue, annuler une invitation envoyée, ou retirer un ami. **404** si le joueur n'est pas concerné.

## Ailleurs dans l'API

`GET /me` renvoie le nombre d'amis dans `player.stats.friends_count`.
