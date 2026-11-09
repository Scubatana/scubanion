# Sécurité entre joueurs

Ce que l'app propose pour se protéger des autres joueurs (APP-07, [fonctionnalites-sociales.md](../fonctionnalites-sociales.md) § 1) : bloquer un joueur, et le signaler à l'équipe.

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

## Signaler un joueur

Un joueur signale un pseudo déplacé ou un comportement gênant. Le signalement est enregistré « en attente » avec son motif, et l'équipe l'examine : il n'y a **pas de sanction automatique**, et le joueur signalé n'est pas prévenu.

Signaler ne bloque pas le joueur et ne touche pas à l'amitié. Pour faire les deux, l'app appelle `POST /me/reports` puis `POST /me/blocks`, par exemple quand le joueur coche « Bloquer aussi ce joueur ».

Un même joueur peut être signalé plusieurs fois, par la même personne ou par d'autres. Un signalement est supprimé avec le compte de l'un ou l'autre joueur.

### `POST /me/reports`

Signale un joueur. Comme pour le blocage, l'app envoie le `player.id` d'un ami ou d'une invitation de `GET /me/friends`.

| Champ | Règle |
| ----- | ----- |
| `player_id` | obligatoire. Identifiant d'un joueur existant, autre que soi. |
| `reason` | obligatoire. `pseudo` (pseudo déplacé), `behavior` (comportement gênant) ou `other` (autre). |
| `comment` | facultatif. 500 caractères au plus. Les espaces autour sont retirés ; un commentaire vide n'est pas gardé. |

Réponse **201** :

```json
{ "message": "Merci, ton signalement a été envoyé. L'équipe va l'examiner." }
```

- **422** sur `player_id` : champ absent, joueur inconnu, ou soi-même. Sur `reason` : absent ou inconnu. Sur `comment` : trop long.
- Limite : 5 signalements par heure et par joueur, puis **429**.

### Examen par l'équipe

Il n'y a pas encore d'interface d'administration : l'équipe consulte la table `reports` (phpMyAdmin en dev), traite les signalements dont `status` vaut `pending`, puis passe `status` à `reviewed`.
