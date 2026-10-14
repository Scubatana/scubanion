# Position, carte et catalogue

L'app ne récupère que ce qui la concerne : ses quêtes (`GET /me/quests`, voir [quetes.md](quetes.md)), les lieux de la zone affichée sur la carte, et le catalogue des types, qu'elle garde en cache.

## `POST /me/position`

La dernière position connue du joueur. L'app l'envoie chaque minute quand elle a l'accès à la localisation. Elle sert à donner les quêtes ciblées sur une zone, et à l'anti-triche.

| Champ | Règle |
| ----- | ----- |
| `latitude`, `longitude` | obligatoires |
| `accuracy_m` | précision en mètres, facultative |
| `mocked` | booléen : vrai si Android signale une position simulée |
| `recorded_at` | facultatif (ISO 8601), l'heure de la mesure ; par défaut, l'heure de réception |

Réponse **204**, toujours, quoi que le serveur en conclue. Une seule position est gardée par joueur : chaque envoi remplace le précédent. Une position plus ancienne que celle gardée ne la remplace pas, mais passe quand même le contrôle anti téléportation. Limite : 10 envois par minute.

**Anti-téléportation.** Chaque nouvelle position est comparée à la précédente. Plus de 1 000 km/h sur plus de 5 km, ou une position simulée, et le joueur est bloqué 24 h sur les tâches de localisation uniquement. Un nouveau signalement pendant le blocage le prolonge au lieu d'en créer un autre. Le blocage est silencieux : rien n'est affiché, ses validations de lieu échouent simplement. Les preuves (positions, distance, vitesse) sont gardées dans `quest_bans.evidence`.

## `GET /map?bbox=ouest,sud,est,nord`

Les lieux de la zone affichée : ceux dont le type est affiché sur la carte (`on_map`), actifs, dont le point est dans le rectangle ou dont le contour le recoupe.

```json
{
  "truncated": false,
  "places": [
    { "id": 1, "name": "Colline du Château", "type": "point_of_interest", "latitude": 43.6953, "longitude": 7.2806, "radius_m": 150, "outline": null },
    { "id": 7, "name": "Forêt", "type": "forest", "latitude": 43.80, "longitude": 7.25, "radius_m": null, "outline": [[43.76, 7.24], [43.74, 7.26], [43.84, 7.26]] }
  ]
}
```

- `bbox` : longitude ouest, latitude sud, longitude est, latitude nord, en degrés décimaux. **422** si elle est mal formée ou plus large qu'un demi-degré : l'app doit zoomer.
- 500 lieux au plus par réponse, les plus proches du centre de la zone en premier. `truncated` vaut `true` quand d'autres lieux existent dans la zone : l'app peut inviter à zoomer.
- `type` renvoie au `code` du catalogue des types de lieux, pour l'icône et la couleur.

## `GET /catalog/types`

Les types de lieux et de tâches actifs. Ce sont des données : on en ajoute sans mettre l'app à jour.

```json
{
  "place_types": [ { "code": "park", "label": "Parc", "icon": "tree", "color": "#4FA871", "on_map": true } ],
  "task_types": [ { "code": "steps", "label": "Marcher", "app_action": "auto" } ]
}
```

La réponse porte un en-tête `ETag`. L'app le garde et le renvoie dans `If-None-Match` : tant que rien n'a changé, le serveur répond **304** sans corps.
