# Bornes

Le protocole entre les bornes et le serveur est décrit dans [../mqtt.md](../mqtt.md). Côté app, deux routes.

## `GET /me/badge`

Un badge de courte durée qui identifie le joueur auprès d'une borne. L'app le transmet à la borne (par NFC, selon la décision D2).

```json
{ "token": "7.mhq4xk.Qm9uam91ciBMw6lh", "expires_at": "2026-10-13T15:05:00+02:00" }
```

- Valable 5 minutes : l'app en redemande un quand il expire, ou juste avant de badger.
- Le jeton ne contient ni l'email ni le pseudo du joueur, et ne peut pas être fabriqué sans la clé du serveur.

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
