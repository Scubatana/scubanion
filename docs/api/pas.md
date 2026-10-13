# Pas

## `POST /me/steps`

Envoie les totaux de pas comptés par le téléphone. Chaque envoi donne le **total** du jour vu par ce téléphone, pas un ajout : renvoyer deux fois le même total ne compte qu'une fois.

```json
{
  "device_id": "3f2b9c4e-8d1a-4c55-9e7b-2a6f0d1c3b4e",
  "days": [
    { "day": "2026-10-12", "steps": 8420 },
    { "day": "2026-10-13", "steps": 3200 }
  ]
}
```

| Champ | Règle |
| ----- | ----- |
| `device_id` | identifiant du téléphone, tiré au hasard à la première ouverture : 8 à 64 caractères parmi lettres, chiffres et `-` |
| `days` | 1 à 32 jours, chacun au plus une fois |
| `days.*.day` | `AAAA-MM-JJ`, entre il y a 30 jours et demain |
| `days.*.steps` | entier positif |

Réponse **200** :

```json
{
  "today": { "date": "2026-10-13", "steps": 3200, "goal": 5000 },
  "streak": { "days": 4, "active": false },
  "total_steps": 48210
}
```

Garde-fous côté serveur : un total plus bas que celui déjà reçu ne retire rien ; le gain est plafonné à 12 000 pas par heure écoulée depuis le dernier envoi du joueur, tous téléphones confondus ; un jour ne dépasse jamais 100 000 pas. Les pas refusés par le plafond horaire sont récupérés aux envois suivants, à mesure que le temps passe.

## `GET /me/steps`

L'historique d'un mois, pour l'écran des pas.

Paramètre facultatif `month` (`AAAA-MM`), entre le mois de création du compte et le mois en cours. Sans lui, le mois en cours.

```json
{
  "month": "2026-10",
  "goal": 5000,
  "has_previous": true,
  "has_next": false,
  "streak": { "days": 4, "active": false },
  "days": [
    { "day": "2026-10-01", "steps": 6120, "goal": 5000, "goal_reached": true }
  ],
  "stats": {
    "total": 48210,
    "average": 3709,
    "active_days": 11,
    "goal_days": 6,
    "best": { "day": "2026-10-04", "steps": 11240 },
    "longest_streak": 3
  }
}
```

- `days` va du premier jour du mois jusqu'à aujourd'hui (ou la fin du mois pour un mois passé).
- Chaque jour garde l'objectif qui était en vigueur ce jour-là ; aujourd'hui utilise l'objectif actuel.
- `best` vaut `null` si aucun pas n'a été compté dans le mois.

## `PUT /me/goal`

Change l'objectif quotidien. Il s'applique à aujourd'hui et aux jours suivants ; les jours passés gardent leur objectif.

| Champ | Règle |
| ----- | ----- |
| `daily_step_goal` | entier entre 1 000 et 50 000 |

Réponse **200** : `{ "today": {...}, "streak": {...} }`.
