# Skins du compagnon : la norme

Ce document explique comment ajouter une base, une couleur ou un accessoire au compagnon. Rien de tout cela ne demande de modifier le code ni de recompiler l'app : le catalogue et les images sont envoyés par le serveur.

Les bases, les couleurs et les catégories se décrivent dans `scubatana.backend/config/companion.php`, avec leurs images dans `scubatana.backend/resources/companion/`. Les accessoires, eux, sont des lignes de la table `accessories` : en ajouter un ne demande aucune modification du code.

## Le principe

- Un accessoire appartient à une **catégorie** (chapeau, yeux, moustache, bouche, cou).
- Chaque **base** (Scuba, Chien, Chat, Axolotl) dit une fois pour toutes où se place chaque catégorie sur son image.
- Un accessoire ne contient donc aucune position : il suffit de dire « c'est un chapeau, voici son image », et il se pose au bon endroit sur toutes les bases.
- On porte au plus un accessoire par catégorie.

## Le gabarit de dessin

Tout accessoire est un fichier SVG dessiné dans un carré de 100 × 100 (`viewBox="0 0 100 100"`). Chaque catégorie a un **point de repère** dans ce carré. C'est ce point que l'app accroche à l'emplacement prévu sur la base.

| Catégorie | Identifiant | Point de repère | Ce que représente ce point |
| --- | --- | --- | --- |
| Chapeau | `hat` | x 50, y 75 | Le bas du chapeau, là où il touche la tête |
| Yeux | `glasses` | x 50, y 50 | Le pont entre les deux verres, entre les yeux |
| Moustache | `moustache` | x 50, y 50 | Le milieu, juste sous le nez |
| Bouche | `mouth` | x 30, y 80 | L'endroit tenu dans la bouche ; l'objet part vers la droite et vers le haut |
| Cou | `neck` | x 50, y 50 | Le nœud, au milieu du cou |

Un gabarit vide avec le repère tracé est fourni pour chaque catégorie dans `resources/companion/templates/`. Partir de ce fichier, dessiner par-dessus, puis retirer les traits de construction.

Conseils pour qu'un dessin tombe juste :

- Occuper toute la largeur du carré : la largeur du carré correspond à la largeur de l'emplacement sur la base.
- Pour un chapeau, tout dessiner au-dessus de la ligne y 75.
- Rester sur la palette du projet (`#1E3A42`, `#2F6B7A`, `#F0855A`, `#F2C14E`) pour garder une cohérence visuelle.

## Ajouter un accessoire

1. Dessiner le SVG à partir du gabarit de sa catégorie (`resources/companion/templates/`).
2. Ajouter une ligne dans la table `accessories` :

| Colonne | Exemple |
| ------- | ------- |
| `code` | `beret` |
| `label` | `Béret` |
| `category` | `hat` (une des catégories de `config/companion.php`) |
| `image` | le contenu du fichier SVG, qui commence par `<svg` et garde `viewBox="0 0 100 100"` |
| `earnable` | `true` s'il se gagne par une récompense de quête, `false` s'il est libre |
| `active` | `true` ; `false` le retire du catalogue sans le supprimer |

L'accessoire apparaît dans l'app au prochain lancement. Un accessoire `earnable` que le joueur n'a pas encore débloqué y est grisé, avec la mention « À gagner ». Un accessoire que le compagnon porte déjà reste toujours portable, même s'il devient `earnable` ou inactif. Pour le faire gagner, il suffit de l'indiquer dans une récompense de quête (`quest_rewards.accessory_id`).

Les 16 accessoires d'origine sont recopiés dans la table par la migration `create_accessories_table`, à partir des fichiers de `resources/companion/accessories/`. Le test du catalogue vérifie que chaque accessoire a une catégorie connue et respecte le carré de 100 × 100.

## Ajouter une catégorie

1. Ajouter une entrée dans `categories` avec son libellé et son point de repère :

```php
'back' => ['label' => 'Dos', 'origin' => ['x' => 50, 'y' => 20]],
```

2. Créer son gabarit dans `resources/companion/templates/back.svg`.
3. Ajouter son emplacement dans les `anchors` de **chaque** base.
4. Fournir au moins deux accessoires de cette catégorie.

Les tests refusent une catégorie sans gabarit, sans emplacement sur une base, ou avec moins de deux accessoires.

## Ajouter une base

Toutes les bases sont dessinées sur le même gabarit, `resources/companion/templates/base.svg` : un carré de 200 × 200 (`viewBox="0 0 200 200"`), une tête ronde centrée en haut, un petit corps en dessous, les yeux toujours au même endroit. Les repères des catégories y sont tracés. C'est ce gabarit commun qui garantit qu'un accessoire va à tous les animaux.

1. Dessiner l'animal en SVG par-dessus le gabarit et le déposer dans `resources/companion/species/`. Seul le SVG est accepté pour une base.
2. Utiliser les trois variables de couleur à la place de couleurs fixes, pour que l'animal prenne la couleur choisie par le joueur :

| Variable | Rôle |
| --- | --- |
| `var(--companion-main, #7FB7C4)` | Couleur principale : tête, corps |
| `var(--companion-shade, #4E8A99)` | Ton foncé : oreilles, queue, pattes |
| `var(--companion-light, #EAF6F8)` | Ton clair : ventre, museau |

La valeur après la virgule sert quand aucune couleur n'est choisie. Ne pas dessiner de masque, de tuba ni de chapeau sur la base : ce sont des accessoires.

3. Ajouter une entrée dans `species`, avec un emplacement par catégorie :

```php
'penguin' => [
	'label' => 'Pingouin',
	'image' => 'species/penguin.svg',
	'anchors' => [
		'hat' => ['x' => 50, 'y' => 18, 'width' => 56],
		'glasses' => ['x' => 50, 'y' => 49.5, 'width' => 44],
		'moustache' => ['x' => 50, 'y' => 57, 'width' => 32],
		'mouth' => ['x' => 50, 'y' => 59.5, 'width' => 64],
		'neck' => ['x' => 50, 'y' => 75, 'width' => 40],
	],
],
```

Un emplacement se lit ainsi : `x` et `y` sont la position du point de repère, en pourcentage de la largeur et de la hauteur de la base ; `width` est la largeur donnée au carré de l'accessoire, en pourcentage de la largeur de la base. Les valeurs ci-dessus sont celles du gabarit commun : une base qui le respecte peut les reprendre telles quelles, et ne les ajuste que si son museau ou sa tête diffèrent.

Tous les accessoires existants vont immédiatement à la nouvelle base.

## Ajouter une couleur

Une couleur est une palette de trois tons, appliquée aux trois variables de la base :

```php
'night' => ['label' => 'Nuit', 'main' => '#5B6BA8', 'shade' => '#2B3A67', 'light' => '#E6E9F5'],
```

Les accessoires ne changent jamais de couleur. La couleur `ocean` doit toujours exister : c'est celle d'un compagnon tout neuf.

## Ce qui n'existe pas encore

Les accessoires se débloquent pour l'instant seulement par les récompenses de quête. Le déblocage par les bornes ou l'ambition (cahier des charges, BOR-04 et BOR-07) reste à faire : il passera par la même table `user_accessories`, sans changer la norme de dessin.
