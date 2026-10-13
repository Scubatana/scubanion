# Règles du jeu

Les règles sont calculées côté serveur (BCK-03), pour qu'un téléphone modifié ne puisse pas les fausser. Ce document décrit celles qui sont en place ; il s'enrichira avec les quêtes, les rencontres et les bornes.

## Pas

- Chaque téléphone envoie le total de pas de chaque jour. Le total d'un jour est la somme des téléphones du joueur.
- Garde-fous contre la triche : au plus 12 000 pas par heure écoulée depuis le dernier envoi du joueur (tous téléphones confondus) et au plus 100 000 pas par jour (`config/steps.php`).
- L'objectif du jour vaut 5 000 pas par défaut ; le joueur le règle entre 1 000 et 50 000.

## Progression du compagnon

Le compagnon est recalculé à chaque ouverture de l'accueil (`GET /api/me`), à chaque envoi de pas et à chaque changement d'objectif. Le calcul repart toujours de l'historique complet des pas : renvoyer deux fois les mêmes pas ne donne jamais deux fois l'XP, et changer l'objectif du jour corrige aussitôt le bonus.

| Valeur | Règle |
| ------ | ----- |
| XP | 1 XP pour 10 pas, plus 50 XP de bonus chaque jour où l'objectif est atteint. |
| Niveau | Passer du niveau N au niveau N + 1 demande N × 1 000 XP : 1 000 XP pour le niveau 2, 2 000 de plus pour le niveau 3, etc. L'API renvoie l'XP gagnée dans le niveau en cours (`xp`) et celle qu'il faut pour passer au suivant (`xp_for_next_level`). |
| Humeur (0 à 100) | La part de l'objectif atteinte aujourd'hui ou hier, la meilleure des deux. Elle tombe à 0 après deux jours sans marcher, et remonte dès la première balade. |
| Santé (0 à 100) | La part moyenne de l'objectif atteinte sur les 7 jours précédant aujourd'hui. Elle reflète la régularité et bouge lentement. |

Les jours antérieurs à la création du compte comptent comme des jours où l'objectif est atteint : un nouveau compagnon commence au niveau 1, avec 0 XP, une humeur et une santé à 100.

Pour un objectif de 5 000 pas tenu chaque jour, le joueur gagne 550 XP par jour : niveau 2 au bout de 2 jours, niveau 3 au bout de 6, niveau 4 au bout de 11, niveau 5 au bout de 19.

Dans l'app, la bulle du compagnon change selon son état : une santé sous 40 passe en premier, puis l'humeur (70 et plus, de 30 à 69, moins de 30).

Le code est dans `scubatana.backend/app/Services/CompanionProgressService.php`, les tests dans `tests/Feature/Me/CompanionProgressTest.php` et `tests/Unit/Services/CompanionProgressServiceTest.php`.
