# Compagnon

## Objet compagnon

Renvoyé par `GET /me`, `PUT /me/profile` et `PUT /me/companion`.

```json
{
  "name": "Scubanion",
  "species": "scuba",
  "color": "ocean",
  "accessories": ["tophat", "bowtie"],
  "level": 1,
  "xp": 0,
  "xp_for_next_level": 1000,
  "mood": 100,
  "health": 100
}
```

- `species`, `color` et `accessories` sont des identifiants du catalogue.
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
    { "id": "tophat", "label": "Haut-de-forme", "category": "hat", "image": "data:image/svg+xml;base64,..." }
  ]
}
```

- `svg` est le dessin de la base. Il utilise les variables CSS `--companion-main`, `--companion-shade` et `--companion-light`, à remplir avec la couleur choisie.
- Chaque accessoire se place sur l'ancre de sa catégorie (`anchors`, en pourcentage du dessin), recentré selon l'`origin` de la catégorie.
- La norme des dessins est décrite dans [skins-compagnon.md](../skins-compagnon.md).
