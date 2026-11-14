# Amis

Deux façons de devenir amis (APP-07, [fonctionnalites-sociales.md](../fonctionnalites-sociales.md) § 3.1) :

- **À distance, par code.** Chaque joueur a un **code ami** de 8 caractères (lettres et chiffres, sans `0`, `O`, `1` ni `I` pour éviter les confusions), donné à la création du compte. Un joueur saisit le code de l'autre, qui accepte l'invitation.
- **En vrai, par QR code.** Un joueur affiche son QR, l'autre le scanne : ils sont amis tout de suite, sans invitation à accepter. Afficher son QR vaut accord (voir [Ajout par QR code](#ajout-par-qr-code)).

Les routes des amis, sauf `GET /me/friends/qr`, renvoient le même objet, la vue complète des amis du joueur : l'app n'a qu'à réafficher la réponse après chaque action.

```json
{
  "code": "K7Q2MZ4P",
  "friends": [
    {
      "id": 12,
      "since": "2026-10-13",
      "cheer": { "sent": false, "received": true },
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
- `friends` : les amitiés acceptées, avec le compagnon, les pas du jour et la série de chaque ami. `companion` vaut `null` si l'ami n'en a pas encore ; `today` et `streak` valent `null` tant que l'ami n'active pas leur partage (`PUT /me/privacy`).
- `cheer` : les coups de patte du jour avec cet ami. `sent` : le joueur lui en a envoyé un ; `received` : l'ami en a envoyé un au joueur. Les deux repassent à `false` à minuit, heure de Paris.
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
- **422** sur `code` : code inconnu (ou joueur sans profil), son propre code, invitation déjà envoyée, ou déjà amis. Un blocage entre les deux joueurs, dans un sens ou dans l'autre, donne la même erreur qu'un code inconnu ([securite.md](securite.md)).
- **409** si le joueur n'a pas encore rempli son profil.
- Limite : 20 invitations par minute et par joueur, puis **429**.

## `POST /me/friends/{id}/accept`

Accepte une invitation reçue. **409** si le joueur n'est pas celui qui a été invité, ou si l'invitation est déjà acceptée. **404** si le joueur n'est pas concerné.

## `POST /me/friends/{id}/cheer`

Envoie un coup de patte à un ami ([fonctionnalites-sociales.md](../fonctionnalites-sociales.md) § 3.2). Pas de corps. Renvoie la vue des amis, où `cheer.sent` passe à `true`.

- Un coup de patte par jour et par ami, heure de Paris : la contrainte unique de la table `cheers` le garantit, même si deux requêtes arrivent en même temps. Les deux amis peuvent s'en envoyer un chacun le même jour.
- Chaque coup de patte reçu dans la journée ajoute 5 points à l'humeur du compagnon de l'ami, 15 au plus par jour, sans dépasser 100 ([regles-du-jeu.md](../regles-du-jeu.md)).
- **409** si un coup de patte a déjà été envoyé à cet ami aujourd'hui, ou si le lien n'est qu'une invitation. **404** si le joueur n'est pas concerné.
- Limite : 30 par minute et par joueur, puis **429**.
- Pas encore de notification à l'ami : elle viendra avec les notifications (§ 7). L'ami le voit dans l'app, sur sa liste et sur la fiche.

## `DELETE /me/friends/{id}`

Supprime le lien, quel que soit son état : refuser une invitation reçue, annuler une invitation envoyée, ou retirer un ami. **404** si le joueur n'est pas concerné.

## Ajout par QR code

Le QR d'ami contient un **jeton** : l'identifiant du joueur et une heure d'expiration, chiffrés par le serveur avec la clé de l'application (`APP_KEY`). Il est illisible (on n'y trouve ni identifiant, ni pseudo, ni position) et infalsifiable sans cette clé. Il n'est pas stocké : le serveur n'a qu'à le déchiffrer.

Le jeton est **valable 60 secondes**. Comme scanner rend amis sans validation, une photo du QR partagée en ligne permettrait sinon à n'importe qui de devenir ami avec ce joueur. Pendant sa minute de validité, le même QR peut être scanné par plusieurs joueurs.

Côté app :

1. le joueur ouvre « Mon QR » : l'app appelle `GET /me/friends/qr` et dessine le QR à partir de `token` ;
2. tant que le QR est affiché, l'app redemande un jeton avant `expires_at`, par exemple toutes les 50 secondes ;
3. l'autre joueur scanne le QR : son app envoie le texte lu à `POST /me/friends/qr`.

### `GET /me/friends/qr`

Un nouveau jeton à afficher en QR.

```json
{ "token": "eyJpdiI6IkZ3...", "expires_at": "2026-11-09T16:01:00+01:00" }
```

- **409** si le joueur n'a pas encore rempli son profil.
- Limite : 30 par minute et par joueur, puis **429**.

### `POST /me/friends/qr`

Ajoute en ami le joueur dont on a scanné le QR, et renvoie la vue des amis.

| Champ | Règle |
| ----- | ----- |
| `token` | obligatoire. Le texte lu dans le QR, tel quel. |

- Les deux joueurs deviennent amis tout de suite. Si une invitation était en attente entre eux, dans un sens ou dans l'autre, elle est acceptée.
- **422** sur `token` :
  - « Ce QR code n'est plus valable. Demande à ton ami de l'afficher à nouveau. » : jeton expiré, illisible ou modifié, ou joueur supprimé. Un blocage entre les deux joueurs, dans un sens ou dans l'autre, donne la même erreur ([securite.md](securite.md)) ;
  - son propre QR ;
  - déjà amis.
- **409** si le joueur n'a pas encore rempli son profil.
- Limite : 20 scans par minute et par joueur, puis **429**.

Si les deux joueurs se scannent au même moment, une seule amitié est créée : le serveur verrouille les deux joueurs le temps de la vérification.

## Ailleurs dans l'API

`GET /me` renvoie le nombre d'amis dans `player.stats.friends_count`.

Bloquer un joueur supprime l'amitié ou les invitations avec lui : voir [securite.md](securite.md).
