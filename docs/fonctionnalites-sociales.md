# Fonctionnalités sociales

Ce document décrit tout ce que l'équipe veut faire pour l'aspect multijoueur de Scubanion : les amis, les groupes qui se retrouvent pour marcher ensemble, les messages et le parrainage. C'est une **proposition** : chaque fonctionnalité donne son but, ses règles, sa priorité et ce qu'il reste à trancher. Une fois validée, elle est reportée dans le [cahier des charges](cahier-des-charges.md) (APP-07, APP-08, APP-10, APP-11).

Ce qui est déjà en place est décrit ailleurs : le contrat de l'API des amis dans [api/amis.md](api/amis.md), les tables `friendships` et `encounters` dans [modele-de-donnees.md](modele-de-donnees.md).

## Principes

- **Se voir en vrai.** Le social répond à l'isolement, la moitié du problème ciblé. Les plus grosses récompenses vont à ce qui se fait ensemble, dehors, pas à ce qui se fait seul sur le téléphone.
- **Coopérer plutôt que s'affronter.** On gagne en groupe. Quand il y a une comparaison, elle se fait au pourcentage de l'objectif atteint, pour qu'un joueur qui reprend le sport ne soit pas écrasé par un marathonien.
- **Ne pas pouvoir pister quelqu'un.** Aucune position n'est jamais montrée à un autre joueur.
- **Règles calculées côté serveur**, comme le reste du jeu (BCK-03) : un téléphone modifié ne doit pas pouvoir inventer un groupe ou une rencontre.

## Vue d'ensemble

| Fonctionnalité | Exigence | Priorité proposée | État |
| -------------- | -------- | ----------------- | ---- |
| Liste d'amis, invitation par code | APP-07 | Should | API faite, écran à faire |
| Ajout d'un ami par QR code | APP-07 | Should | À faire |
| Encouragement | APP-07 | Should | À faire |
| Fiche du compagnon d'un ami | APP-07 | Should | Données déjà dans l'API, écran à faire |
| Fil d'activité des amis | APP-07 | Could | À faire |
| Classement de la semaine entre amis | APP-07 | Could | À faire |
| Groupes : se retrouver, marcher ensemble, tâches et défis | APP-08, APP-10 | Should | Table `encounters` faite, le reste à faire |
| Messages prédéfinis et emotes | APP-11 | Could | À faire |
| Parrainage | nouvelle exigence | Could | À faire |

## 1. Amis

### 1.1 Ajouter un ami par code ou par QR code

Chaque joueur a déjà un code ami de 8 caractères ([api/amis.md](api/amis.md)). L'écran Amis l'affiche aussi sous forme de **QR code**.

- Pour ajouter quelqu'un qu'on a en face de soi, on scanne son QR code avec l'appareil photo de l'app. L'invitation part et l'autre l'accepte, comme avec le code tapé à la main.
- Le QR code ne contient que le code ami : aucune donnée personnelle.
- La saisie à la main reste possible, pour inviter quelqu'un à distance.

Côté serveur, rien ne change : l'app envoie le code lu à `POST /me/friends`. Il faut seulement un plugin Capacitor de lecture de QR code.

### 1.2 Encourager un ami

Un bouton « Encourager » (un coup de patte du compagnon) sur la fiche de chaque ami.

- Un encouragement par jour et par ami.
- L'ami le voit à sa prochaine ouverture de l'app, et son compagnon gagne un petit bonus d'humeur.
- But : créer un lien de tous les jours, sans avoir besoin d'écrire.

### 1.3 Voir le compagnon d'un ami

Toucher un ami ouvre sa fiche : son compagnon avec ses accessoires, son niveau, ses pas du jour par rapport à son objectif et sa série de jours. Ces données sont déjà renvoyées par `GET /me/friends`. La fiche sert aussi de vitrine pour les skins gagnés en borne, et donne envie d'aller les chercher.

### 1.4 Fil d'activité

Une liste des moments forts des amis, sans détail sur leurs déplacements :

- « Lina a atteint son objectif du jour »
- « Yanis est passé au niveau 4 »
- « Tom a débloqué la casquette Decathlon »
- « Lina, Yanis et Tom ont réussi leur défi de groupe »

Le lieu n'est jamais affiché, même pour un accessoire gagné en borne.

### 1.5 Classement de la semaine

Un classement entre amis, remis à zéro chaque lundi, au **pourcentage moyen de l'objectif atteint** dans la semaine et pas au nombre de pas. Un joueur peut choisir de ne pas y apparaître.

## 2. Groupes : se retrouver et marcher ensemble

C'est le cœur du multijoueur. Deux joueurs qui se croisent ne gagnent pas seulement un bonus : ils peuvent **former un groupe**, à deux, à trois ou plus, pour marcher ensemble et réaliser des tâches communes. Les rencontres (APP-08) et les équipes (APP-10) deviennent une seule fonctionnalité.

### 2.1 Former un groupe

- Un groupe compte de **2 à 6 joueurs** (taille maximale à confirmer).
- Il se forme **sur place** : le créateur affiche le QR code du groupe, les autres le scannent. Il faut être ensemble physiquement pour rejoindre un groupe.
- Le groupe a un nom et dure dans le temps : on peut se retrouver la semaine suivante avec le même groupe.
- Un joueur peut appartenir à plusieurs groupes (nombre maximum à fixer), et quitter un groupe à tout moment.
- Les membres d'un groupe qui ne sont pas encore amis se voient proposer de le devenir.

### 2.2 Prouver qu'on est ensemble

Les bonus de groupe ne comptent que quand les membres sont **réellement au même endroit au même moment**. On appelle ce moment une **sortie**.

- Une sortie commence quand au moins deux membres d'un groupe sont détectés ensemble, et s'arrête quand ils se séparent.
- La détection est la même que pour les rencontres : Bluetooth entre les téléphones, recoupé par la position côté serveur. Le plugin Bluetooth actuel ne sait pas émettre, d'où la décision **D11** à prendre (voir § 7).
- Comme pour les rencontres, aucune coordonnée brute n'est gardée : seulement une zone approximative, et jamais montrée aux autres joueurs.
- Les garde-fous anti-triche des quêtes s'appliquent : position simulée ou vitesse impossible annulent la sortie.

### 2.3 Marcher ensemble

Pendant une sortie, chaque membre présent gagne plus d'XP pour ses pas. Proposition de bonus, à équilibrer :

| Membres présents | Bonus sur l'XP des pas |
| ---------------- | ---------------------- |
| 2 | +20 % |
| 3 | +30 % |
| 4 et plus | +50 % |

Limite à lever : aujourd'hui les pas sont comptés par jour ([regles-du-jeu.md](regles-du-jeu.md)), pas par moment. Pour savoir quels pas ont été faits pendant la sortie, il faut dater les pas plus finement. Repli possible : un bonus fixe d'XP par sortie d'au moins 20 minutes et 1 000 pas.

### 2.4 Tâches de groupe

Des quêtes qui ne se réussissent qu'à plusieurs. Elles réutilisent le moteur de quêtes existant, avec des tâches validées pour tout le groupe :

- **Pas cumulés** : « faites 20 000 pas à vous trois cette semaine ».
- **Lieu visité ensemble** : « passez au parc ensemble », validée quand au moins deux membres font leur check-in au même lieu à moins de 10 minutes d'écart.
- **Sorties** : « faites 3 sorties ensemble cette semaine ».
- **Borne** : « passez tous à la même borne le même jour ».

### 2.5 Défi de la semaine

Chaque lundi, chaque groupe reçoit un défi commun dont l'objectif dépend du nombre de membres (par exemple 25 000 pas par membre). Tout le groupe gagne ou perd ensemble : le défi pousse ceux qui marchent le plus à entraîner les autres.

### 2.6 Récompenses

- XP pour chaque membre.
- **Accessoires de groupe** : un skin que seuls les membres d'un groupe qui a réussi un défi peuvent porter, pour se reconnaître entre eux.
- Une série de semaines réussies, affichée sur la fiche du groupe.

## 3. Messages prédéfinis et emotes

Pas de chat libre : seulement des **messages prédéfinis** et des **emotes**, entre amis et dans un groupe.

- Messages : « On marche ce soir ? », « Je suis en route », « Bravo ! », « Plus que 1 000 pas ! », « Rendez-vous au parc ».
- Emotes : le compagnon qui saute de joie, qui applaudit, qui dort, qui a faim, etc., dessinés à partir des sprites de `scubatana.demo/assets/img/dog/`.
- Les textes sont des données, ajoutées sans livrer l'app, comme les accessoires.

Pourquoi : l'app accepte des joueurs dès 15 ans. Un chat libre demanderait de la modération (insultes, harcèlement, partage de données personnelles) qu'une équipe de quatre ne peut pas assurer. Des messages prédéfinis suppriment ce risque tout en permettant de s'organiser.

## 4. Parrainage

Un joueur invite quelqu'un qui n'a pas encore l'app.

- Le parrain partage un lien ou son code. Le filleul le saisit à l'inscription.
- La récompense n'est donnée qu'une fois le filleul **actif**, par exemple quand il a atteint son objectif 3 jours, pour éviter les faux comptes.
- Les deux gagnent : un accessoire exclusif « parrain » et un accessoire de bienvenue pour le filleul.
- Le parrain et le filleul deviennent amis automatiquement.
- Limite : 5 parrainages récompensés par mois.

Le parrainage soutient l'indicateur de rétention K6 et le coût d'acquisition des joueurs (R5.01, R5.13).

## 5. Confidentialité et sécurité

- Seuls les amis acceptés voient l'activité d'un joueur ; une invitation ne montre que le pseudo.
- Aucune position, aucune zone de rencontre ni de sortie n'est montrée à un autre joueur.
- **Bloquer** un joueur supprime l'amitié, le retire des groupes communs et l'empêche de renvoyer une invitation.
- **Signaler** un joueur (pseudo ou nom de groupe déplacé).
- **Profil privé** : le joueur n'apparaît ni dans le classement ni dans le fil d'activité.
- Supprimer un compte supprime ses amitiés, groupes, sorties, rencontres, encouragements et messages (règles RGPD du cahier des charges, § 6.5).

## 6. Données à ajouter

Proposition, à affiner au moment du développement :

| Table | Contenu |
| ----- | ------- |
| `groups` | Nom, créateur, date de création |
| `group_members` | Groupe, joueur, date d'arrivée |
| `outings` | Une sortie : groupe, début, fin, zone approximative |
| `outing_members` | Les membres présents pendant une sortie |
| `cheers` | Encouragements : qui, à qui, quel jour |
| `message_templates` | Textes des messages prédéfinis et des emotes |
| `messages` | Message envoyé : expéditeur, ami ou groupe destinataire, modèle utilisé |
| `referrals` | Parrain, filleul, date de validation |
| `blocks`, `reports` | Blocages et signalements |

Les tables existantes `friendships` et `encounters` sont conservées.

## 7. Questions à trancher

| Question | Options | Remarque |
| -------- | ------- | -------- |
| Détection des rencontres et des sorties (D11) | Bluetooth recoupé par la position, position seule, scan du QR code de l'autre | Le plugin Bluetooth actuel ne sait pas émettre d'annonces : autre plugin ou code Android natif. À trancher avant le 07/11 |
| Taille d'un groupe | 4, 6, 8 | Plus le groupe est grand, plus la détection est difficile |
| Faut-il être amis pour former un groupe ? | Oui, ou non avec proposition d'amitié | La proposition est « non » : le groupe se forme sur place |
| Bonus de la marche ensemble | Pourcentage sur les pas de la sortie, ou bonus fixe | Le pourcentage demande de dater les pas plus finement |
| Récompense du parrainage | Accessoire, XP, les deux | |

## 8. Pistes gardées pour plus tard

Proposées mais pas retenues pour le moment :

- Chat libre (modération impossible à assurer).
- Album des compagnons croisés en rencontre.
- Passage en duo à une borne, mini-jeu à deux sur l'écran de la borne.
- Objectif collectif de toute la ville pendant un événement.

## Ordre de réalisation proposé

1. Écran Amis sur l'API existante, ajout par QR code, fiche du compagnon d'un ami.
2. Encouragements.
3. Décision D11, puis groupes et sorties, avec le bonus de marche ensemble.
4. Tâches de groupe et défi de la semaine.
5. Messages prédéfinis et emotes.
6. Parrainage, fil d'activité, classement.
