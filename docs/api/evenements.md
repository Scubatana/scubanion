# Événements

Un événement est limité dans le temps (BOR-05) : un bonus d'XP pour un week-end, un accessoire de saison, ou les deux, sur certaines bornes ou sur toutes. Il s'applique au premier passage récompensé du jour à une borne concernée ([bornes.md](bornes.md)). Les règles complètes sont dans [../regles-du-jeu.md](../regles-du-jeu.md#événements).

## `GET /events`

Les événements en cours et à venir, pour les annoncer dans l'app. Un événement fini ou désactivé n'apparaît plus.

```json
{
  "events": [
    {
      "id": 4,
      "name": "Week-end au parc",
      "description": null,
      "starts_at": "2026-10-17T00:00:00+02:00",
      "ends_at": "2026-10-19T00:00:00+02:00",
      "in_progress": true,
      "bonus_xp": 50,
      "accessory": null,
      "all_terminals": false,
      "terminals": [
        { "code": "chateau-01", "name": "Borne du Château" },
        { "code": "parc-01", "name": "Borne du parc" }
      ]
    },
    {
      "id": 5,
      "name": "Halloween",
      "description": "Un chapeau à gagner à chaque borne",
      "starts_at": "2026-10-31T00:00:00+01:00",
      "ends_at": "2026-11-02T00:00:00+01:00",
      "in_progress": false,
      "bonus_xp": 0,
      "accessory": { "id": "crown", "label": "Couronne" },
      "all_terminals": true,
      "terminals": []
    }
  ]
}
```

- Triés par date de début.
- `starts_at` est inclus, `ends_at` est exclu : un événement du samedi 00:00 au lundi 00:00 couvre tout le week-end, et s'arrête à minuit pile le lundi. Les heures sont celles de Paris, avec leur décalage (`+02:00` en été, `+01:00` en hiver).
- `in_progress` : l'événement a commencé.
- `bonus_xp` : l'XP ajoutée au premier passage récompensé du jour à une borne concernée, en plus des 100 XP habituels. 0 si l'événement ne donne qu'un accessoire.
- `accessory` : l'accessoire à gagner (`id` du catalogue, voir [compagnon.md](compagnon.md)), ou `null`. Il est déjà gagné si son `id` est dans `companion.unlocked_accessories` : la liste n'est pas propre au joueur.
- `all_terminals` : vrai si l'événement vaut pour toutes les bornes, y compris celles installées plus tard. `terminals` est alors vide ; sinon il liste les bornes concernées (leur `code` est celui de `GET /bornes`), triées par nom.

## Créer un événement

Il n'y a pas encore d'interface d'administration (BCK-08) : un événement se crée en ligne de commande, sur le serveur.

```bash
php artisan evenements:creer "Week-end au parc" "2026-10-17 00:00" "2026-10-19 00:00" --bonus=50 --borne=parc-01 --borne=chateau-01
php artisan evenements:creer "Halloween" "2026-10-31" "2026-11-02" --accessoire=crown --toutes-les-bornes --description="Un chapeau à gagner à chaque borne"
```

| Argument ou option | Règle |
| ------------------ | ----- |
| `nom` | obligatoire, 40 caractères au plus : il s'affiche sur l'écran de la borne |
| `debut`, `fin` | obligatoires, heure de Paris ; la fin est exclue et doit être après le début |
| `--bonus` | de 1 à 10 000 XP |
| `--accessoire` | le code d'un accessoire actif et `earnable` |
| `--borne` | le code d'une borne concernée, à répéter pour plusieurs bornes |
| `--toutes-les-bornes` | l'événement vaut pour toutes les bornes |
| `--description` | facultatif, 500 caractères au plus |

Il faut `--bonus`, `--accessoire` ou les deux, et `--borne` ou `--toutes-les-bornes`, pas les deux. Un événement déjà utilisé par un passage ne peut plus être supprimé : pour l'arrêter, on passe `active` à faux (phpMyAdmin en dev).
