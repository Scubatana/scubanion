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
| XP | 1 XP pour 10 pas, plus 50 XP de bonus chaque jour où l'objectif est atteint, plus l'XP des récompenses de quête déjà récupérées (`user_rewards`). |
| Niveau | Passer du niveau N au niveau N + 1 demande N × 1 000 XP : 1 000 XP pour le niveau 2, 2 000 de plus pour le niveau 3, etc. L'API renvoie l'XP gagnée dans le niveau en cours (`xp`) et celle qu'il faut pour passer au suivant (`xp_for_next_level`). |
| Humeur (0 à 100) | La part de l'objectif atteinte aujourd'hui ou hier, la meilleure des deux. Elle tombe à 0 après deux jours sans marcher, et remonte dès la première balade. |
| Santé (0 à 100) | La part moyenne de l'objectif atteinte sur les 7 jours précédant aujourd'hui. Elle reflète la régularité et bouge lentement. |

Les jours antérieurs à la création du compte comptent comme des jours où l'objectif est atteint : un nouveau compagnon commence au niveau 1, avec 0 XP, une humeur et une santé à 100.

Pour un objectif de 5 000 pas tenu chaque jour, le joueur gagne 550 XP par jour : niveau 2 au bout de 2 jours, niveau 3 au bout de 6, niveau 4 au bout de 11, niveau 5 au bout de 19.

Dans l'app, la bulle du compagnon change selon son état : une santé sous 40 passe en premier, puis l'humeur (70 et plus, de 30 à 69, moins de 30).

Le code est dans `scubatana.backend/app/Services/CompanionProgressService.php`, les tests dans `tests/Feature/Me/CompanionProgressTest.php` et `tests/Unit/Services/CompanionProgressServiceTest.php`.

## Quêtes

La conception suit la proposition d'Evan sur la PR #10.

**Attribution.** Au lancement de l'app (`GET /api/me/quests`), le serveur donne au joueur chaque quête publiée et en cours, attribuée automatiquement, dont il remplit les conditions : l'ambition visée (ou aucune), et la zone visée (ou aucune) qui contient sa dernière position connue. Un admin peut aussi donner une quête à un joueur précis (`php artisan quetes:donner {quête} {joueur}`). Il n'y a pas de prérequis : un joueur qui n'a pas droit à une quête ne la reçoit pas.

**Périodes.** Une quête `once` est donnée une fois. Une quête `daily` est redonnée chaque jour à minuit, une quête `weekly` chaque lundi (heure de Paris). Chaque période a sa propre ligne `user_quests`, qui expire à la fin de la période ou de la quête, la plus proche des deux.

**Étapes.** Une quête reçue est active tout de suite, et un joueur peut en avoir plusieurs à la fois. Il faut finir toutes les tâches de l'étape en cours pour débloquer la suivante ; le joueur ne voit jamais les étapes suivantes. Une quête expirée ou ratée disparaît de l'app, sauf s'il lui reste des récompenses à récupérer.

| Action | Comment la tâche avance |
| ------ | ----------------------- |
| `auto` (pas) | les pas comptent seulement depuis le déblocage de l'étape : le total du joueur à ce moment est gardé dans `baseline`. La tâche avance à chaque envoi de pas, sans attendre que le joueur ouvre ses quêtes |
| `checkin` | le joueur est dans le rayon du lieu (100 m par défaut) ou à l'intérieur de son contour, avec une marge égale à la précision du GPS, plafonnée à 50 m. Un lieu précis se valide en une visite. Pour un type de lieu, n'importe quel lieu actif de ce type convient, mais chaque lieu ne compte qu'une fois (« passe par 3 arrêts de bus » demande 3 arrêts différents). La tâche se valide toute seule dès qu'une position envoyée par l'app (chaque minute, app ouverte) tombe dans le lieu ; le bouton « Je suis sur place » reste là pour valider tout de suite |
| `scan` | le code envoyé correspond, sans tenir compte de la casse ni des espaces ; il n'est jamais envoyé à l'app |
| `answer` | quiz : une question à la fois, dans l'ordre, un seul essai. Une erreur fait rater la quête. Les réponses ne sortent jamais du serveur |
| `meet` | chaque rencontre validée depuis le déblocage de l'étape compte |

**Hors ligne.** Une validation faite hors ligne est acceptée jusqu'à 24 h après, si la quête était encore en cours à ce moment-là. Une validation de lieu plus ancienne que la dernière position connue passe quand même le contrôle de vitesse, comparée à cette position.

**Récompenses.** Une récompense (`quest_rewards`) donne de l'XP, un accessoire, ou les deux. Elle se gagne à l'un de ces trois moments :

- en finissant une tâche (récompense rattachée à la tâche) ;
- en finissant toute une étape (récompense rattachée au numéro d'étape) ;
- en finissant la quête (récompense sans tâche ni étape).

Une récompense gagnée attend dans `user_rewards` jusqu'à ce que le joueur la récupère avec le bouton « Récupérer » : l'XP s'ajoute alors au compagnon et l'accessoire est débloqué. Une récompense déjà gagnée reste récupérable même si la quête est ensuite ratée ou expire. Les accessoires marqués `earnable` dans la table `accessories` sont verrouillés tant que le joueur ne les a pas débloqués.

**Anti-triche.** La position envoyée pour valider un lieu passe par le même contrôle que la position envoyée chaque minute : une vitesse impossible ou une position simulée bloque le joueur 24 h. Un joueur bloqué (`quest_bans`) échoue silencieusement aux tâches de localisation : rien ne lui est affiché, ses validations de lieu échouent simplement.

**Limite connue (négligeable).** Une tâche de pas compte les pas reçus par le serveur après le déblocage de son étape. Des pas faits juste avant le déblocage, mais envoyés après (téléphone resté sans réseau), sont donc comptés. Avec une synchro toutes les 20 secondes, l'écart est minime ; le corriger demanderait de dater les pas plus finement qu'au jour.

Le code est dans `app/Services/QuestEngine.php` (avancement), `QuestAssigner.php` (attribution) et `QuestPresenter.php` (ce qui est envoyé à l'app), les tests dans `tests/Feature/Quests/QuestEngineTest.php`.
