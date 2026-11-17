# Rencontres

Une rencontre, c'est deux joueurs qui se sont vus en vrai. Elle fait avancer les tâches `meet` des quêtes (« Croise un autre joueur ») chez les deux joueurs. Les règles complètes, dont l'anti-triche, sont dans [regles-du-jeu.md § Rencontres](../regles-du-jeu.md#rencontres).

Le Bluetooth entre téléphones est reporté ([fonctionnalites-sociales.md § 11](../fonctionnalites-sociales.md#11-écarté-ou-reporté-et-pourquoi)) : une rencontre se prouve de deux façons, sans lui.

- **En scannant le QR de rencontre de l'autre joueur**, avec les routes ci-dessous. Il est fait comme le QR d'ami ([amis.md](amis.md#ajout-par-qr-code)) : valable 60 secondes, illisible et infalsifiable. Mais son usage est écrit dans le jeton, et chaque route vérifie le sien : montrer son QR de rencontre à un inconnu ne lui permet pas de devenir ami, et un QR d'ami ne valide pas de rencontre. Afficher son QR de rencontre vaut accord pour la rencontre seulement. Les deux joueurs n'ont pas besoin d'être amis.
- **En passant à la même borne** à moins de 10 minutes d'écart : rien à faire côté app, le serveur la crée à la réception du second passage (`POST /me/passages`, voir [bornes.md](bornes.md)). Si la création de la rencontre échoue, l'erreur est journalisée et le passage réussit quand même.

Côté app, la tâche `meet` affiche un bouton « Valider une rencontre ». Il ouvre une feuille qui montre le QR de rencontre du joueur (renouvelé avant `expires_at`) et un bouton pour scanner celui de l'autre. À l'ouverture, l'app envoie tout de suite sa position (`POST /me/position`), pour que l'autre puisse la recouper. La position n'est lue qu'avec le consentement « position géographique » des Paramètres : sans lui, l'app refuse de scanner et explique pourquoi.

## `GET /me/encounters/qr`

Un nouveau jeton à afficher en QR de rencontre.

```json
{ "token": "eyJpdiI6IkZ3...", "expires_at": "2026-11-17T16:01:00+01:00" }
```

- **409** si le joueur n'a pas encore rempli son profil.
- Limite : 30 par minute et par joueur, puis **429**.

## `POST /me/encounters`

Valide une rencontre avec le joueur dont on a scanné le QR.

| Champ | Règle |
| ----- | ----- |
| `token` | obligatoire. Le texte lu dans le QR, tel quel. |
| `latitude`, `longitude` | facultatifs, ensemble. La position du joueur qui scanne, au moment du scan. Elle est enregistrée comme celle de `POST /me/position`, avec les mêmes contrôles. |
| `accuracy_m` | facultatif. Précision du GPS, en mètres. |
| `mocked` | facultatif. `true` si Android signale une position simulée. |
| `recorded_at` | facultatif. Heure de la position (au plus 5 minutes dans le futur). Par défaut, l'heure de réception. |

Sans position dans la requête (localisation refusée ou introuvable), le serveur reprend la dernière position reçue du joueur.

**201** pour une nouvelle rencontre, **200** si les deux joueurs se sont déjà rencontrés ce jour-là (rien n'est compté en plus) :

```json
{ "player": { "pseudo": "Lea" }, "met_at": "2026-11-17T16:00:00+01:00", "new": true }
```

Erreurs :

- **409** si le joueur qui scanne n'a pas encore rempli son profil.
- **422** sur `token` : QR expiré, illisible, QR d'ami au lieu d'un QR de rencontre, ou blocage entre les deux joueurs (« Ce QR code n'est plus valable. Demande à l'autre joueur de l'afficher à nouveau. »), ou son propre QR.
- **422** sur `position` :
  - le joueur qui scanne n'a pas de position de moins de 10 minutes : « Active ta localisation pour valider la rencontre, puis scanne à nouveau. » ;
  - l'autre joueur non plus : « Demande à Lea d'ouvrir Scubanion avec sa localisation activée, puis scanne à nouveau. » ;
  - les positions sont trop éloignées, ou l'un des deux triche (position simulée, blocage anti-triche en cours) : « Vous semblez trop loin l'un de l'autre : rapprochez-vous, puis scanne à nouveau. ». Comme pour les quêtes, la triche n'est jamais annoncée.
- Limite : 20 par minute et par joueur, puis **429**.
