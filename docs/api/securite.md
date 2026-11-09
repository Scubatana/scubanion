# Sécurité entre joueurs

Ce que l'app propose pour se protéger des autres joueurs (APP-07, [fonctionnalites-sociales.md](../fonctionnalites-sociales.md) § 1). Pour l'instant : bloquer un joueur.

## Bloquer un joueur

Bloquer un joueur coupe tout lien avec lui :

- l'amitié ou les invitations en attente entre les deux joueurs sont supprimées, dans les deux sens ;
- tant que le blocage existe, aucun des deux ne peut inviter l'autre. Le serveur refuse l'invitation avec le message d'un code inconnu (« Aucun joueur n'a ce code. »), pour que le joueur bloqué ne sache pas qu'il l'est ([amis.md](amis.md)) ;
- le joueur bloqué n'est pas prévenu, et ne voit le blocage nulle part.

Seul celui qui a bloqué voit le blocage et peut le lever. Un blocage est supprimé avec le compte de l'un ou l'autre joueur.

Les trois routes renvoient le même objet, la liste des joueurs que le joueur a bloqués : l'app n'a qu'à réafficher la réponse après chaque action.

```json
{
  "blocked": [
    { "id": 7, "since": "2026-11-09", "player": { "id": 3, "pseudo": "Yanis" } }
  ]
}
```

- `id` : l'identifiant du blocage, à utiliser pour débloquer.
- `since` : le jour du blocage.
- `player` : le joueur bloqué. Son `id` est le même que dans `GET /me/friends`.

### `GET /me/blocks`

La liste des joueurs bloqués.

### `POST /me/blocks`

Bloque un joueur. L'app envoie l'`id` du joueur, pris dans `player.id` d'un ami ou d'une invitation de `GET /me/friends`.

| Champ | Règle |
| ----- | ----- |
| `player_id` | obligatoire. Identifiant d'un joueur existant, autre que soi. |

- Bloquer un joueur déjà bloqué ne change rien et renvoie la liste.
- **422** sur `player_id` : champ absent, joueur inconnu, ou soi-même.
- Limite : 30 blocages par minute et par joueur, puis **429**.

### `DELETE /me/blocks/{id}`

Débloque le joueur. L'amitié supprimée n'est pas rétablie : il faut une nouvelle invitation. **404** si le blocage n'existe pas ou n'a pas été fait par ce joueur.
