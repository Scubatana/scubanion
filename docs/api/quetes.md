# Quêtes

Une quête est donnée à un joueur (automatiquement selon son ambition ou sa zone, ou à la main par un admin). Elle est découpée en **étapes**, chacune faite de plusieurs **tâches** : il faut finir toutes les tâches d'une étape pour débloquer la suivante. Le joueur ne voit que l'étape en cours (« étape 2 sur 3 ») : le serveur n'envoie jamais la suite. Les règles complètes sont dans [../regles-du-jeu.md](../regles-du-jeu.md).

## `GET /me/quests`

À appeler au lancement de l'app : le serveur donne d'abord au joueur les quêtes auxquelles il a droit (pour la période en cours), fait avancer les tâches automatiques, puis renvoie :

```json
{
  "quests": [
    {
      "id": 12,
      "quest_id": 3,
      "name": "Le belvédère",
      "description": "Monte jusqu'à la Colline du Château.",
      "icon": "landmark",
      "period": "once",
      "expires_at": null,
      "step": 1,
      "steps": 2,
      "reward": { "xp": 200, "accessory": "crown" },
      "tasks": [
        {
          "id": 7,
          "type": "visit_place",
          "action": "checkin",
          "description": "Rejoins la Colline du Château",
          "target": 1,
          "value": 0,
          "completed": false,
          "place": { "id": 1, "name": "Colline du Château", "latitude": 43.6953, "longitude": 7.2806, "radius_m": 150, "outline": null },
          "place_type": null,
          "questions": null
        }
      ]
    }
  ],
  "completed": [
    { "id": 9, "quest_id": 4, "name": "Balade du jour", "icon": "sun", "completed_at": "2026-10-13T18:20:00+02:00", "reward": { "xp": 50, "accessory": null } }
  ]
}
```

- `quests` : les quêtes en cours. Une quête expirée ou ratée n'y apparaît plus. `id` est l'identifiant de la quête du joueur, à utiliser dans la route d'action.
- `period` : `once`, le jour (`2026-10-14`) ou la semaine ISO (`2026-W42`) d'une quête répétée. `expires_at` : la fin de la période ou de la quête, la plus proche.
- `tasks` : seulement les tâches de l'étape en cours. `action` dit à l'app quoi faire :

| `action` | Ce que fait l'app |
| -------- | ----------------- |
| `auto` | rien : le serveur fait avancer la tâche seul (les pas) |
| `checkin` | envoyer la position du joueur quand il est sur place |
| `scan` | envoyer le code scanné |
| `answer` | afficher les questions et envoyer les réponses une par une |
| `meet` | rien : la tâche avance quand une rencontre avec un autre joueur est validée |

- `place` : le lieu à rejoindre, avec son rayon (`radius_m`) ou son contour (`outline`, liste de `[latitude, longitude]`). `place_type` : le type de lieu à rejoindre, quand n'importe quel lieu de ce type convient (« passe par un parc »).
- `questions` (pour `answer`) : les questions et leurs choix, **sans les réponses**. `value` est le nombre de bonnes réponses déjà données, donc l'indice de la prochaine question.
- `completed` : les quêtes terminées depuis 7 jours, avec ce qu'elles ont rapporté.

## `POST /me/quests/{id}/tasks/{taskId}`

Fait avancer une tâche de l'étape en cours. Le corps dépend de l'action :

| `action` | Corps |
| -------- | ----- |
| `checkin` | `latitude`, `longitude` |
| `scan` | `code` |
| `answer` | `question` (indice, dans l'ordre), `choice` (indice du choix) |

Champ commun facultatif : `recorded_at` (ISO 8601), l'heure à laquelle l'action a eu lieu, pour une validation faite hors ligne. Elle est acceptée jusqu'à 24 h après.

Réponse **200** :

```json
{ "status": "in_progress", "quest": { "...": "la quête à jour, comme dans GET" }, "reward": null }
```

- `status` : `in_progress`, `completed` (la quête est finie : `quest` vaut `null` et `reward` donne l'XP et l'accessoire gagnés) ou `failed` (mauvaise réponse à un quiz : la quête est ratée et disparaît).

Erreurs :
- **422** `position` : « Tu n'es pas encore sur place. » ;
- **422** `code` : le code ne correspond pas ;
- **422** `question` : la question n'est pas la suivante ;
- **422** `recorded_at` : validation trop ancienne ;
- **409** : quête finie, ratée ou expirée, tâche déjà faite, ou tâche qui avance toute seule (`auto`, `meet`) ;
- **404** : quête d'un autre joueur, ou tâche qui n'est pas dans l'étape en cours ;
- **429** : plus de 30 actions par minute.

## Ailleurs dans l'API

- `GET /companion/catalog` : chaque accessoire a un champ `earnable`. Un accessoire `earnable` se gagne en terminant une quête.
- L'objet compagnon (`GET /me`…) donne `unlocked_accessories`, les accessoires gagnés par le joueur.
- `PUT /me/companion` refuse (422 sur `accessories`) un accessoire `earnable` que le joueur n'a pas encore gagné.
