# Compagnon

## Objet compagnon

Renvoyé par `GET /me`, `PUT /me/profile`, `PUT /me/companion`, `POST /me/steps`, `PUT /me/goal` et `POST /me/quests/{id}/claim`.

```json
{
  "name": "Scubanion",
  "species": "scuba",
  "color": "ocean",
  "accessories": ["tophat", "bowtie"],
  "unlocked_accessories": ["crown"],
  "level": 1,
  "xp": 0,
  "xp_for_next_level": 1000,
  "mood": 100,
  "health": 100
}
```

- `species`, `color` et `accessories` sont des identifiants du catalogue.
- `unlocked_accessories` : les accessoires « à gagner » que le joueur a débloqués avec une récompense de quête.
- `level`, `xp`, `mood` et `health` sont calculés par le serveur à partir des pas, des récompenses de quête récupérées et des passages en borne ([../regles-du-jeu.md](../regles-du-jeu.md)). L'app ne fait que les afficher.
- `xp` est l'XP gagnée dans le niveau en cours, `xp_for_next_level` celle qu'il faut pour passer au suivant.
- `mood` et `health` vont de 0 à 100.

## `PUT /me/companion`

Change le nom et l'apparence.

| Champ | Règle |
| ----- | ----- |
| `name` | obligatoire, 2 à 20 caractères |
| `species` | identifiant d'une base du catalogue |
| `color` | identifiant d'une couleur du catalogue |
| `accessories` | liste (éventuellement vide) d'identifiants d'accessoires, un seul par catégorie |

Réponse **200** : `{ "companion": {...} }`. **409** si le joueur n'a pas encore de compagnon (profil non rempli).

**422** sur `accessories` pour un accessoire « à gagner » (`earnable`) que le joueur n'a pas débloqué. Un accessoire que le compagnon porte déjà reste toujours accepté, même s'il est devenu à gagner ou a été retiré du catalogue.

## `GET /companion/catalog`

Tout ce qu'il faut pour dessiner un compagnon. L'app le charge une fois et le garde en mémoire.

```json
{
  "categories": [
    { "id": "hat", "label": "Chapeau", "origin": { "x": 50, "y": 75 } }
  ],
  "species": [
    {
      "id": "scuba",
      "label": "Scuba",
      "anchors": { "hat": { "x": 50, "y": 18, "width": 56 } },
      "svg": "<svg ...>...</svg>"
    }
  ],
  "colors": [
    { "id": "ocean", "label": "Océan", "main": "#7FB7C4", "shade": "#4E8A99", "light": "#EAF6F8" }
  ],
  "accessories": [
    { "id": "tophat", "label": "Haut-de-forme", "category": "hat", "image": "data:image/svg+xml;base64,...", "earnable": false }
  ]
}
```

- Les accessoires viennent de la table `accessories` : un accessoire ajouté par un admin apparaît dans l'app sans nouvelle version. Un accessoire désactivé n'est plus envoyé.
- `earnable` : l'accessoire se gagne par une récompense de quête. L'app l'affiche grisé tant qu'il n'est pas dans `unlocked_accessories`.

- `svg` est le dessin de la base. Il utilise les variables CSS `--companion-main`, `--companion-shade` et `--companion-light`, à remplir avec la couleur choisie.
- Chaque accessoire se place sur l'ancre de sa catégorie (`anchors`, en pourcentage du dessin), recentré selon l'`origin` de la catégorie.
- La norme des dessins est décrite dans [skins-compagnon.md](../skins-compagnon.md).
