# Quêtes

Une quête est donnée à un joueur (automatiquement selon son ambition ou sa zone, ou à la main par un admin). Elle est découpée en **étapes**, chacune faite de plusieurs **tâches** : il faut finir toutes les tâches d'une étape pour débloquer la suivante. Le joueur ne voit que l'étape en cours (« étape 2 sur 3 ») : le serveur n'envoie jamais la suite. Les règles complètes sont dans [../regles-du-jeu.md](../regles-du-jeu.md).

## `GET /me/quests`

À appeler au lancement de l'app : le serveur donne d'abord au joueur les quêtes auxquelles il a droit (pour la période en cours), fait avancer les tâches automatiques, puis renvoie les quêtes en cours et celles qui ont encore des récompenses à récupérer :

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
      "status": "active",
      "step": 1,
      "steps": 2,
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
          "questions": null,
          "rewards": [{ "xp": 0, "accessory": "leaf_hat" }]
        }
      ],
      "step_rewards": [],
      "quest_rewards": [{ "xp": 200, "accessory": "crown" }],
      "rewards_to_claim": [{ "id": 31, "xp": 50, "accessory": null }]
    }
  ]
}
```

- `quests` : les quêtes en cours, plus les quêtes finies, ratées ou expirées qui ont encore des récompenses à récupérer. Une fois ces récompenses récupérées, la quête n'apparaît plus. `id` est l'identifiant de la quête du joueur, à utiliser dans les routes d'action et de récupération.
- `status` : `active`, `completed`, `failed` (quiz raté) ou `expired`. Seule une quête `active` a des `tasks` et des `step_rewards`.
- `period` : `once`, le jour (`2026-10-14`) ou la semaine ISO (`2026-W42`) d'une quête répétée. `expires_at` : la fin de la période ou de la quête, la plus proche.
- `tasks` : seulement les tâches de l'étape en cours. `action` dit à l'app quoi faire :

| `action` | Ce que fait l'app |
| -------- | ----------------- |
| `auto` | rien : le serveur fait avancer la tâche seul (les pas) |
| `checkin` | rien d'obligatoire : la tâche se valide toute seule avec la position envoyée chaque minute. Un bouton « Je suis sur place » envoie la position tout de suite |
| `scan` | envoyer le code scanné |
| `answer` | afficher les questions et envoyer les réponses une par une |
| `meet` | rien : la tâche avance quand une rencontre avec un autre joueur est validée |

- `place` : le lieu à rejoindre, avec son rayon (`radius_m`) ou son contour (`outline`, liste de `[latitude, longitude]`). `place_type` : le type de lieu à rejoindre, quand n'importe quel lieu de ce type convient (« passe par un parc »).
- `questions` (pour `answer`) : les questions et leurs choix, **sans les réponses**. `value` est le nombre de bonnes réponses déjà données, donc l'indice de la prochaine question.
- Récompenses à venir : `tasks[].rewards` (gagnées en finissant la tâche), `step_rewards` (en finissant toute l'étape en cours) et `quest_rewards` (en finissant la quête). Les récompenses des étapes suivantes ne sont jamais envoyées. Une récompense donne de l'XP, un accessoire (son `id` du catalogue), ou les deux.
- `rewards_to_claim` : les récompenses gagnées mais pas encore récupérées. L'app affiche alors un bouton « Récupérer ».

## `POST /me/quests/{id}/tasks/{taskId}`

Fait avancer une tâche de l'étape en cours. Le corps dépend de l'action :

| `action` | Corps |
| -------- | ----- |
| `checkin` | `latitude`, `longitude`, et si possible `accuracy_m` (précision en mètres) et `mocked` (position simulée signalée par Android) |
| `scan` | `code` |
| `answer` | `question` (indice, dans l'ordre), `choice` (indice du choix) |

Champ commun facultatif : `recorded_at` (ISO 8601), l'heure à laquelle l'action a eu lieu, pour une validation faite hors ligne. Elle est acceptée jusqu'à 24 h après.

Réponse **200** :

```json
{ "status": "active", "quest": { "...": "la quête à jour, comme dans GET" } }
```

- `status` : `active`, `completed` (la quête est finie) ou `failed` (mauvaise réponse à un quiz : la quête est ratée).
- `quest` vaut `null` quand la quête n'a plus rien à montrer : finie ou ratée, sans récompense à récupérer.
- Un `checkin` passe par le même contrôle anti téléportation que `POST /me/position`. La précision envoyée élargit la zone du lieu, au plus de 50 m. Pour un type de lieu avec une cible supérieure à 1, chaque lieu ne compte qu'une fois.

Erreurs :
- **422** `position` : « Tu n'es pas encore sur place. », ou « Tu as déjà validé ce lieu » pour un type de lieu ;
- **422** `code` : le code ne correspond pas ;
- **422** `question` : la question n'est pas la suivante ;
- **422** `recorded_at` : validation trop ancienne ;
- **409** : quête finie, ratée ou expirée, tâche déjà faite, ou tâche qui avance toute seule (`auto`, `meet`) ;
- **404** : quête d'un autre joueur, ou tâche qui n'est pas dans l'étape en cours ;
- **429** : plus de 30 actions par minute.

## `POST /me/quests/{id}/claim`

Récupère toutes les récompenses gagnées et pas encore récupérées de cette quête : l'XP s'ajoute au compagnon, les accessoires sont débloqués.

```json
{
  "claimed": [{ "id": 31, "xp": 50, "accessory": null }, { "id": 32, "xp": 0, "accessory": "crown" }],
  "quest": null,
  "companion": { "...": "le compagnon à jour, avec unlocked_accessories" }
}
```

`quest` vaut `null` si la quête n'a plus rien à montrer, sinon c'est la quête à jour. **409** s'il n'y a rien à récupérer, **404** pour la quête d'un autre joueur.

## Ailleurs dans l'API

- `GET /companion/catalog` : chaque accessoire a un champ `earnable`. Un accessoire `earnable` se gagne par une récompense de quête.
- L'objet compagnon (`GET /me`…) donne `unlocked_accessories`, les accessoires débloqués par le joueur.
- `PUT /me/companion` refuse (422 sur `accessories`) un accessoire `earnable` que le joueur n'a pas encore débloqué.
