# Bornes

Le fonctionnement complet des bornes (jeton, messages MQTT) est décrit dans [../mqtt.md](../mqtt.md). Côté app, deux routes.

## `POST /me/passages`

Enregistre un passage à une borne. L'app envoie le jeton lu sur la borne par NFC, tel quel.

| Champ | Règle |
| ----- | ----- |
| `token` | obligatoire, le jeton donné par la borne (`{code}.{heure}.{signature}`, 40 caractères au plus) |
| `latitude`, `longitude` | facultatifs, mais l'un ne va pas sans l'autre : la position du téléphone au moment du scan |
| `accuracy_m` | facultatif, la précision de cette position en mètres |
| `mocked` | facultatif, vrai si Android signale une position simulée |
| `recorded_at` | facultatif, l'heure de la position (maintenant par défaut). Hors ligne, l'app garde la position du scan avec le jeton et l'envoie avec lui. Ni plus de 24 h dans le passé, ni plus de 5 minutes dans le futur |

L'app envoie la position dès qu'elle en a une : c'est ce qui prouve que le joueur était bien à la borne (voir [les règles du jeu](../regles-du-jeu.md#bornes)). La position passe par le même contrôle que `POST /me/position` : une position simulée ou une vitesse impossible bloque le joueur 24 h.

Réponse **200** :

```json
{
  "scanned_at": "2026-10-13T15:00:00+02:00",
  "status": "rewarded",
  "message": "Bravo Lea ! +100 XP",
  "reward_xp": 100,
  "terminal": { "code": "chateau-01", "name": "Borne du Château" },
  "companion": { "...": "voir compagnon.md" }
}
```

- `status` : `rewarded` au premier passage du jour à cette borne (100 XP, ajoutés tout de suite au compagnon), `already_rewarded` ensuite (passage enregistré, sans récompense).
- Le même message est publié sur l'écran de la borne.
- Sans réseau, l'app garde le jeton et l'envoie plus tard : il reste valable 24 h après l'heure du scan.

Erreurs :
- **422** sur `token` : jeton illisible, signature fausse, borne inconnue, scan de plus de 24 h ou dans le futur ;
- **422** sur `position` : le joueur n'était pas à la borne (position envoyée hors du lieu, position simulée, ou dernière position connue trop loin). Le jeton n'est pas consommé : l'app peut le renvoyer avec une meilleure position ;
- **422** sur `latitude`, `longitude` ou `recorded_at` : position incomplète, ou trop ancienne ;
- **409** : jeton déjà utilisé, par ce joueur ou un autre ;
- **429** : plus de 30 envois par minute.

## `GET /bornes`

Les bornes déclarées, pour la carte (APP-09) et le suivi.

```json
{
  "terminals": [
    {
      "code": "chateau-01",
      "name": "Borne du Château",
      "online": true,
      "last_seen_at": "2026-10-13T14:59:30+02:00",
      "place": { "id": 3, "name": "Colline du Château", "latitude": 43.6953, "longitude": 7.2817 }
    }
  ]
}
```

- `online` est vrai si la borne a donné signe de vie depuis moins de 2 minutes.
- `place` vaut `null` pour une borne qui n'est rattachée à aucun lieu.
