# Fonctionnalités sociales

Ce document décrit tout ce que l'équipe veut faire pour l'aspect multijoueur de Scubanion : la sécurité, ce que chacun partage, les amis, les squads qui se forment pour marcher ensemble, les raids de borne et les notifications. C'est une **proposition** : chaque fonctionnalité donne son but, ses règles, sa priorité et ce qu'il reste à trancher. Une fois validée, elle est reportée dans le [cahier des charges](cahier-des-charges.md) (APP-07, APP-08, APP-10, APP-11, APP-12).

Ce qui est déjà en place est décrit ailleurs : le contrat de l'API des amis dans [api/amis.md](api/amis.md), les tables `friendships` et `encounters` dans [modele-de-donnees.md](modele-de-donnees.md), l'envoi des positions et l'anti-triche dans [regles-du-jeu.md](regles-du-jeu.md).

## Principes

- **Le multijoueur est conseillé, jamais obligatoire.** Un joueur seul fait progresser son compagnon, réussit ses quêtes et passe aux bornes sans aucune limite. Jouer à plusieurs donne des bonus plafonnés et des skins en plus, jamais un accès au reste du jeu. Notre cible est en partie isolée : si le jeu solo était bridé, elle partirait avant d'avoir trouvé quelqu'un avec qui jouer.
- **Mais tout pousse à se voir en vrai.** Les plus belles récompenses (skins de squad, raids, moments partagés) vont à ce qui se fait ensemble, dehors. Les bornes chez les partenaires servent de point de rencontre naturel, y compris entre joueurs qui ne se connaissent pas.
- **Coopérer, jamais punir.** On gagne ensemble. Un défi raté ne fait rien perdre.
- **Chacun choisit ce qu'il partage** (§ 2), et rien de sensible n'est partagé par défaut.
- **Personne ne peut pister personne.** Aucune position, aucune zone n'est jamais montrée à un autre joueur.
- **Le serveur décide.** Squads, bonus et récompenses sont calculés par l'API (BCK-03), avec des contraintes d'unicité en base pour qu'une même récompense ne soit jamais donnée deux fois, même si deux téléphones envoient la même requête en même temps.

## Vue d'ensemble

| Fonctionnalité                                               | § | Exigence          | Priorité proposée | État                                              |
| ------------------------------------------------------------- | -- | ----------------- | ------------------- | -------------------------------------------------- |
| Bloquer, signaler, quitter, exclure, suppression complète    | 1  | APP-06, APP-07    | Must                | À faire (suppression de compte déjà en cascade) |
| Choix de ce que l'on partage                                  | 2  | APP-07            | Must                | À faire                                           |
| Liste d'amis, invitation par code                             | 3  | APP-07            | Should              | API faite, écran à faire                         |
| Ajout d'un ami par QR code, coup de patte, fiche du compagnon | 3  | APP-07            | Should              | À faire                                           |
| Squads : invitation, QR code, marche ensemble                 | 4  | APP-08, APP-10    | Should              | Table `encounters` faite, le reste à faire      |
| Moment partagé (animation des compagnons)                    | 4  | APP-10            | Should              | À faire                                           |
| Quêtes à plusieurs, défis du jour et de la semaine, collection | 5  | APP-05, APP-10    | Should              | À faire, sur le moteur de quêtes existant        |
| Échanges d'objets entre joueurs                               | 5  | nouvelle exigence | Could               | À faire                                           |
| Cadeaux entre amis                                            | 5  | nouvelle exigence | Could               | À faire                                           |
| Succès sociaux                                                | 5  | nouvelle exigence | Should              | À faire                                           |
| Série de coups de patte entre amis                            | 3  | nouvelle exigence | Should              | À faire                                           |
| Raid de borne                                                 | 6  | BOR-05            | Should              | À faire                                           |
| Notifications                                                 | 7  | APP-12            | Should              | À faire                                           |
| Fil d'activité réduit, classement facultatif                | 3  | APP-07            | Could               | À faire                                           |
| Messages prédéfinis et emotes                               | 8  | APP-11            | Could               | À faire                                           |
| Parrainage                                                    | 9  | nouvelle exigence | Could               | À faire après le reste                           |

## 1. Sécurité

Ces règles sont livrées **avant ou avec** la première fonctionnalité sociale, pas après.

- **Pseudos seulement.** Ni vrai nom, ni photo, ni contact libre entre joueurs.
- **Bloquer** un joueur : l'amitié est supprimée, les invitations, coups de patte et messages de l'un vers l'autre sont refusés par le serveur (pas seulement masqués dans l'app), et ils ne peuvent plus se retrouver dans une même squad. Le joueur bloqué n'est pas prévenu.
- **Signaler** un joueur (pseudo déplacé, comportement gênant) : le signalement est enregistré avec son motif, et l'équipe l'examine. Pas de sanction automatique.
- **Quitter une squad** à tout moment, sans confirmation des autres.
- **Exclure d'une squad** : le chef de squad (celui qui l'a créée, puis le membre arrivé le plus tôt s'il part) peut exclure un membre, qui ne peut plus la rejoindre.
- **Supprimer son compte** efface tout ce qui le concerne, données dérivées comprises : amitiés, invitations, squads, rencontres, coups de patte, messages, signalements qu'il a faits, participations aux raids. Les tables actuelles sont déjà supprimées en cascade ; chaque nouvelle table doit suivre la même règle.
- **Durée de conservation.** Les rencontres et les squads gardent leur zone approximative 30 jours au plus, puis seulement des compteurs (« 12 sorties ensemble »). Une zone gardée longtemps finirait par révéler des habitudes : mêmes personnes, même endroit, chaque semaine.
- **Limites de fréquence** sur toutes les actions sociales, comme les 20 invitations par minute déjà en place.

## 2. Ce que chacun partage

Chaque joueur règle, dans son compte, ce que ses amis voient de lui. Les pas sont des données d'activité physique, proches des données de santé : ils ne sont pas partagés tant que le joueur ne l'a pas décidé.

| Donnée                                | Visible par                 | Par défaut       |
| -------------------------------------- | --------------------------- | ----------------- |
| Pseudo et compagnon (apparence, skins) | Amis et membres de sa squad | Toujours visible  |
| Niveau du compagnon                    | Amis                        | Visible           |
| Pas du jour et objectif                | Amis                        | **Masqué** |
| Série de jours                        | Amis                        | **Masqué** |
| Apparition dans le fil d'activité     | Amis                        | Visible           |
| Succès                                 | Amis                        | Visible           |
| Apparition dans le classement          | Amis                        | **Non**     |

- Un membre de sa squad qui n'est pas son ami ne voit que son pseudo et son compagnon.
- Une invitation reçue ne montre que le pseudo.
- Changement à prévoir dans l'API : `GET /me/friends` renvoie aujourd'hui toujours les pas du jour et la série ; elle devra respecter ces réglages.

## 3. Amis

### 3.1 Ajouter un ami

Deux façons, selon qu'on se voit ou non :

- **En vrai : par QR code.** Un joueur affiche son QR d'ami, l'autre le scanne avec l'app : ils sont **amis tout de suite**, sans invitation à accepter. Afficher son QR vaut accord.
- **À distance : par code.** On saisit le code ami de 8 caractères ([api/amis.md](api/amis.md)) ; l'autre accepte ou refuse l'invitation, comme aujourd'hui.

Le QR d'ami **change toutes les minutes** tant qu'il est affiché. Comme le scan rend amis sans validation, une photo du QR postée sur un réseau (Discord, Snapchat…) permettrait sinon à n'importe qui de devenir ami avec ce joueur à son insu. Pour les joueurs, rien ne change : on affiche, l'autre scanne. Le QR ne contient qu'un jeton signé par le serveur, sans identifiant ni position.

### 3.2 Coup de patte

Un bouton « Coup de patte » sur la fiche de chaque ami : le compagnon envoie un petit signe d'encouragement.

- Un coup de patte par jour et par ami, vérifié par le serveur.
- L'ami reçoit une notification (§ 7), et son compagnon gagne un petit bonus d'humeur, plafonné.
- Il peut répondre d'un coup de patte le même jour, ce qui fait avancer leur série (§ 3.6).
- But : un lien de tous les jours, sans avoir besoin d'écrire.

### 3.3 Fiche du compagnon d'un ami

Toucher un ami ouvre sa fiche : son compagnon avec ses skins, son niveau, et selon ses réglages (§ 2) ses pas du jour et sa série. La fiche sert aussi de vitrine aux skins gagnés en borne et en squad.

### 3.4 Fil d'activité (réduit)

Trois événements seulement, sans lieu ni heure précise :

- « Lina a atteint son objectif du jour »
- « Tom a débloqué un nouveau skin »
- « Yanis a réussi son défi de la semaine »

Ils sont calculés côté serveur au fil de l'eau, sans pagination ni filtres : on affiche ceux des 7 derniers jours.

### 3.5 Classement de la semaine (facultatif)

Un classement entre amis, remis à zéro chaque lundi, au pourcentage moyen de l'objectif atteint. Il est **désactivé par défaut** : seuls les joueurs qui l'activent y apparaissent et le voient. Il passe après tout le reste, et peut être abandonné sans gêner le projet.

### 3.6 Série de coups de patte

Entre deux amis, une **série** compte les jours d'affilée où ils se sont **échangé un coup de patte** : l'un en envoie un, l'autre y répond le même jour (heure de Paris). Peu importe qui commence.

- Elle démarre toute seule au premier jour d'échange, entre n'importe quels amis : pas besoin de l'activer, elle ne révèle rien d'autre que les coups de patte.
- Le compteur s'affiche à côté de l'ami dans la liste, avec une petite flamme.
- Elle n'a rien à voir avec la série personnelle déjà en place, qui compte les jours où le joueur atteint son objectif de pas (`streak` dans l'API des pas).

**Paliers.** À 7, 30 et 100 jours, les deux compagnons gagnent un **accessoire assorti** (non échangeable) et l'app joue le moment partagé (§ 4.4).

**Ne pas en faire une corvée.** Les séries de Snapchat sont connues pour la pression qu'elles mettent sur les adolescents ; celle de Scubanion suit ces règles :

- **Un joker par semaine** : un jour sans échange par semaine ne casse pas la série. Il s'applique tout seul.
- **Aucun rappel** du type « votre série va s'arrêter » : les seules notifications sont le coup de patte reçu et les paliers atteints.
- Quand une série s'arrête, l'app ne dit pas qui l'a fait tomber, et ne montre aucun message triste. Le **record** reste affiché, et une nouvelle série repart dès le prochain échange.
- La série ne sert jamais de condition à une quête, un défi ou un succès : elle reste un bonus.

Limite à assumer : cette série récompense le fait d'ouvrir l'app chaque jour, pas de marcher. C'est pour cela que ses récompenses restent des accessoires, sans XP.

## 4. ScubaSquads : marcher ensemble

C'est le cœur du multijoueur. Comme dans un jeu en ligne, des joueurs forment une **squad** le temps d'une sortie, à deux, trois ou plus, pour marcher ensemble et réussir des quêtes à plusieurs. Une squad est **temporaire** : elle se dissout à la fin de la sortie.

### 4.1 Former une squad

Un joueur crée une squad (2 à 6 joueurs) ; il en est le chef. Les autres la rejoignent de deux façons :

- **Par invitation** : le chef invite un ou plusieurs **amis** depuis l'app. Ils reçoivent une notification (« Lina t'invite dans sa squad ») et acceptent ou refusent. Seuls les amis peuvent être invités à distance, pour qu'un inconnu ne puisse pas attirer un joueur dans sa squad.
- **En vrai, par QR code** : le chef affiche le QR **de cette squad**, que les joueurs présents scannent. Pas besoin d'être amis ; à la fin de la sortie, l'app propose aux membres qui ne le sont pas de le devenir. Comme le QR d'ami, il change toutes les minutes, pour qu'une photo ne permette pas de rejoindre la squad de loin.

Un joueur n'est que dans une squad à la fois. Il la quitte quand il veut ; le chef peut exclure un membre (§ 1).

### 4.2 Être ensemble pour de vrai

On peut rejoindre une squad à distance, mais les bonus ne comptent que pour les membres **réellement ensemble**. Le Bluetooth est écarté (§ 11) : le serveur compare les positions, que l'app envoie déjà chaque minute.

1. Le serveur découpe la sortie en **fenêtres de 10 minutes**.
2. Une fenêtre est validée pour les membres qui étaient à moins de 100 m les uns des autres sur au moins 8 des 10 positions, et qui se sont déplacés d'au moins 150 m à une vitesse de marche (2 à 7 km/h). Deux téléphones posés sur une table, ou un trajet en bus, ne valident donc rien.
3. Les positions ne sont gardées que le temps du calcul (voir § 1 pour la zone approximative).
4. La squad se dissout quand le chef la termine, quand il ne reste qu'un membre, après 30 minutes sans fenêtre validée, ou au bout de 3 heures.

Les garde-fous des quêtes s'appliquent : une position simulée ou une vitesse impossible annule les bonus du joueur concerné.

**Passage commun à une borne.** Deux membres d'une squad qui passent à la même borne à moins de 10 minutes d'écart sont aussi considérés comme ensemble : c'est la preuve la plus fiable, puisque chaque passage porte un jeton signé et à usage unique (D2).

### 4.3 Bonus de marche ensemble

Les pas sont comptés par jour : on ne sait pas lesquels ont été faits pendant la sortie. Le bonus est donc un **montant fixe d'XP par fenêtre validée**, en plus de l'XP normale des pas.

| Membres ensemble dans la fenêtre | XP par fenêtre de 10 minutes |
| --------------------------------- | ----------------------------- |
| 2                                 | 20 XP                         |
| 3 et plus                         | 30 XP                         |

Plafond : 180 XP par jour, soit environ une heure de marche ensemble. À comparer aux 550 XP d'une journée où l'objectif de 5 000 pas est tenu seul : le bonus est sensible, mais un joueur seul n'est pas distancé. Valeurs à équilibrer.

Les règles de validation des fenêtres et le calcul du bonus sont écrits comme des fonctions pures, testées unitairement.

### 4.4 Moment partagé

Quand une première fenêtre est validée, quand un défi est réussi ou quand un raid est débloqué, l'app joue une courte **animation des compagnons ensemble** : ils se retrouvent, sautent, repartent côte à côte. C'est ce moment, plus que l'XP, qui donne envie de recommencer. Il demande de nouveaux dessins à partir des sprites de `scubatana.demo/assets/img/dog/`.

## 5. Quêtes à plusieurs, défis, collection, échanges et succès

### 5.1 Quêtes à plusieurs

Le moteur de quêtes existant gagne deux paramètres par tâche : un **nombre minimum de participants** et une **fenêtre de temps**. La tâche est validée pour tous quand assez de membres d'une même squad l'ont validée dans la fenêtre.

Exemples : « passez au parc à au moins deux, à moins de 15 minutes d'écart », « passez à trois à la borne de la médiathèque ». Les check-in et les passages en borne existent déjà : seule la règle de squad est nouvelle.

### 5.2 Défis du jour

Chaque matin, chaque joueur reçoit **un petit défi**, faisable en une balade, seul ou à plusieurs :

- passer par un parc ;
- marcher 20 minutes en squad ;
- envoyer un coup de patte à un ami ;
- passer à une borne.

Pour que le multijoueur reste conseillé sans être obligatoire, le défi du jour est toujours faisable seul. Un défi social est proposé avec une alternative solo (« marcher 20 minutes en squad, ou 3 000 pas seul »), et la version à plusieurs rapporte un peu plus.

Pour qu'il ne devienne pas une corvée :

- la récompense est modeste (de l'XP, parfois un accessoire) ;
- rater un défi du jour ne fait rien perdre et ne casse aucune série ;
- aucune notification de rappel : il est affiché à l'ouverture de l'app, c'est tout.

Les défis du jour sont des **quêtes** `daily` du moteur existant, déjà redonnées chaque jour à minuit ([regles-du-jeu.md](regles-du-jeu.md)) : seule l'alternative solo ou squad est nouvelle.

### 5.3 Défi de la semaine

Les squads étant temporaires, le défi est **personnel** mais se réussit avec les autres. Chaque lundi, chaque joueur reçoit un défi choisi parmi quelques modèles :

- faire 3 sorties en squad ;
- marcher en squad avec 2 joueurs différents ;
- passer à une borne avec un membre de sa squad ;
- réussir une quête à plusieurs.

N'importe quelle squad compte, avec n'importe qui. Réussir donne de l'XP et un skin de squad ; échouer ne fait rien perdre. Les pas cumulés ne font pas partie des défis : ils ne demandent pas d'être ensemble.

### 5.4 Collection

Les skins gagnés à plusieurs (défis, quêtes à plusieurs, raids) forment une **collection** à part dans les accessoires du joueur (`user_accessories`), avec, pour chacun, le souvenir de ceux avec qui il l'a gagné (« gagné avec Lina et Tom »). Pas d'inventaire partagé à gérer.

### 5.5 Échanges d'objets

Deux joueurs qui se voient s'échangent des accessoires, à la manière des échanges de Rocket League.

1. Un joueur affiche son **QR d'échange**, l'autre le scanne. Comme les autres QR, il change toutes les minutes : on n'échange qu'en vrai.
2. Une **fenêtre d'échange** s'ouvre sur les deux téléphones, avec deux colonnes : « Je donne » et « Je reçois ». Chacun ajoute les accessoires qu'il veut donner, et voit en direct ce que l'autre propose.
3. Chacun appuie sur **« Prêt »**. Si l'un des deux modifie son offre, les deux « Prêt » sont annulés : personne ne peut changer l'offre au dernier moment.
4. Quand les deux sont prêts, chacun **confirme** après un décompte de 3 secondes.
5. Le serveur fait l'échange **d'un seul coup** (transaction) : il vérifie que chacun possède encore ce qu'il donne et ne possède pas déjà ce qu'il reçoit, puis transfère tout, ou rien. Un accessoire donné que le compagnon portait lui est retiré.

Les échanges ne concernent que les accessoires : ni XP, ni niveau, ni monnaie. Une fenêtre d'échange se ferme après 5 minutes sans action, ou dès que l'un des deux la quitte.

**Ce qui ne s'échange pas.** Chaque accessoire du catalogue est marqué échangeable ou non (`tradable` dans `accessories`). Ne s'échangent pas :

- les skins de partenaires et de collabs : un partenaire paie pour qu'on vienne chez lui, le skin doit se gagner sur place ;
- les skins de raid et de défi de la semaine : ce sont des souvenirs de ce qu'on a fait ;
- les accessoires de départ, que tout le monde a.

S'échangent donc surtout les récompenses de quêtes et de défis du jour. Un joueur ne possède jamais deux fois le même accessoire : donner un accessoire, c'est s'en séparer.

**Garde-fous.** Les échanges attirent les arnaques, la pression entre joueurs, qui peuvent être mineurs, et les comptes créés pour récupérer des objets :

- échanger demande un compte d'au moins 7 jours et un compagnon de niveau 3 ;
- 3 échanges par jour au plus ;
- l'écran récapitule clairement ce que l'on perd avant la confirmation, et propose « Signaler » ;
- l'historique des échanges est gardé, pour examiner un signalement ;
- pas d'annulation après coup : l'écran le dit avant la confirmation.

Technique : la fenêtre d'échange doit se mettre à jour en direct sur les deux téléphones. Le plus simple est que l'app interroge le serveur toutes les 1 à 2 secondes tant que la fenêtre est ouverte ; un WebSocket (Laravel Reverb) serait plus propre, mais ajoute un service au déploiement.

### 5.6 Cadeaux entre amis

Offrir un accessoire à un ami, sans rien demander en retour, même à distance.

- Depuis la fiche d'un ami, on choisit un accessoire et on peut ajouter un message prédéfini (« Joyeux anniversaire ! », « Pour ta première balade ! »).
- L'ami reçoit une notification et **accepte ou refuse** le cadeau. Tant qu'il n'a pas répondu, l'accessoire reste chez celui qui l'offre ; sans réponse au bout de 7 jours, le cadeau est annulé.
- Les règles des échanges s'appliquent : seuls les accessoires échangeables (`tradable`) s'offrent, et l'ami ne doit pas déjà l'avoir.
- Garde-fous : seulement entre amis, compte d'au moins 7 jours et compagnon de niveau 3, 3 cadeaux offerts par jour au plus. Sans ces limites, un joueur pourrait créer des comptes pour récupérer des objets et les envoyer à son compte principal.

Plus simple qu'un échange : pas de fenêtre en direct, juste une offre et une réponse.

### 5.7 Succès sociaux

Des **succès** (badges) récompensent les moments sociaux. Ils s'affichent sur la fiche du joueur, visibles par ses amis selon ses réglages (§ 2).

| Succès | Condition |
| ------ | --------- |
| Première sortie | Une fenêtre validée en squad |
| Bande de potes | 10 sorties en squad |
| Nouveaux visages | Marcher en squad avec 5 joueurs différents |
| Entouré | 5 amis |
| Patte tendue | 20 coups de patte envoyés |
| Premier raid | Participer à un premier raid de borne |
| Fidèle au poste | 4 défis de la semaine réussis |
| Généreux | Un premier cadeau offert |
| Bonne affaire | Un premier échange |

Règles :

- Les succès se cumulent dans le temps et ne se perdent jamais. Aucun ne demande une série de jours d'affilée, pour ne pas créer de pression.
- Certains paliers donnent un accessoire (non échangeable), la plupart rien d'autre que le badge.
- Les succès sont des **données** : un catalogue en base (code, nom, icône, type de condition, seuil), pour en ajouter sans livrer l'app, comme les quêtes.
- Ils sont calculés par le serveur à chaque événement concerné (fenêtre validée, ami ajouté, raid…), et un succès débloqué apparaît dans le fil d'activité.

## 6. Raid de borne 

Un **raid** est un événement sur une borne : elle doit être scannée par un nombre de joueurs différents dans la journée (par exemple 15) pour débloquer un skin exclusif, donné à **tous ceux qui y sont passés** ce jour-là, même avant que le palier soit atteint.

- L'écran de la borne affiche la jauge (« 11 / 15 »), mise à jour par MQTT après chaque passage.
- L'app montre les raids en cours sur la carte, et envoie une notification aux joueurs qui y sont déjà passés quand il ne manque plus que quelques personnes, puis quand le skin est débloqué.
- Pas besoin d'être amis ni en squad : le raid fait coopérer des inconnus, sans aucun contact entre eux. C'est la porte d'entrée du multijoueur pour un joueur seul.
- Chaque passage reste un passage normal, vérifié par jeton signé (D2) : un joueur ne compte qu'une fois par raid.

C'est aussi un argument pour les partenaires : un raid amène du monde au même endroit le même jour (BOR-05, BOR-07).

## 7. Notifications

Les notifications doivent donner envie, pas culpabiliser. C'est le compagnon qui parle.

| Événement                              | Exemple                                                            |
| ---------------------------------------- | ------------------------------------------------------------------ |
| Coup de patte reçu                      | « Bulle, le compagnon de Hamza, t'envoie un coup de patte ! »    |
| Invitation d'ami reçue ou acceptée     | « Thomas a accepté ton invitation. »                           |
| Invitation dans une squad                | « Yanis t'invite dans sa squad pour une balade. »                |
| Cadeau reçu                              | « Lina t'offre la casquette rayée ! »                            |
| Succès débloqué                          | « Nouveau succès : Bande de potes ! »                            |
| Palier de série                          | « 30 jours d'affilée avec Lina ! Vos compagnons ont un nouveau foulard. » |
| Défi presque réussi, puis réussi      | « Plus qu'une sortie pour le skin de la semaine ! »              |
| Raid presque débloqué, puis débloqué | « Plus que 3 joueurs à la borne du parc pour le skin pirate ! » |
| Compagnon (solo)                         | « Il fait beau, Bulle a envie d'aller au parc. »                 |

Règles :

- **3 notifications par jour au plus**, aucune entre 21 h et 8 h. Les invitations de squad ne comptent pas dans la limite : elles sont attendues.
- Chaque type se désactive séparément dans le compte.
- Jamais de reproche (« ton ami t'attend », « ton compagnon est triste à cause de toi »).

Technique : les rappels du compagnon peuvent être des notifications locales, programmées par l'app sans serveur. Les notifications sociales demandent des notifications push (Firebase Cloud Messaging), donc un projet Firebase et un envoi depuis l'API : compter 1 à 2 jours.

## 8. Messages prédéfinis et emotes

Pas de chat libre : seulement des **messages prédéfinis** et des **emotes**, entre amis et dans une squad.

- Messages : « On marche ce soir ? », « Je suis en route », « Rendez-vous à la borne », « Bravo ! ».
- Emotes : 6 à 10 animations du compagnon (saute de joie, applaudit, dort…).
- Les textes sont des données en base, ajoutés sans livrer l'app.

Un chat libre demanderait une modération qu'une équipe de quatre ne peut pas assurer, avec des joueurs qui peuvent être mineurs. Priorité Could : le coup de patte et les invitations de squad couvrent déjà une partie du besoin.

## 9. Parrainage

Un joueur invite quelqu'un qui n'a pas encore l'app. Le parrain et le filleul gagnent chacun un accessoire quand le filleul est **actif** (objectif atteint 3 jours), et ils deviennent amis. 5 parrainages récompensés par mois au plus.

Priorité Could, à faire après tout le reste : c'est surtout un outil d'acquisition (indicateur K6), et il attire la triche par comptes multiples. Il reste utile au modèle économique (R5.01, R5.13).

## 10. Données à ajouter

Proposition, à affiner au moment du développement. Chaque table est supprimée en cascade avec le compte (§ 1).

| Table                               | Contenu                                                                       |
| ----------------------------------- | ----------------------------------------------------------------------------- |
| `privacy_settings`                | Les réglages de partage du § 2                                              |
| `blocks`, `reports`             | Blocages ; signalements avec motif et état d'examen                          |
| `cheers`                          | Coups de patte : qui, à qui, quel jour (unique par paire et par jour)        |
| `squads`, `squad_members`       | Chef, début, fin ; membres, invitation ou scan, arrivée, départ, exclusion |
| `squad_windows`                   | Fenêtres de 10 minutes validées et membres ensemble                         |
| `weekly_challenges`               | Défi de la semaine d'un joueur, avancement, date de réussite                |
| `trades`, `trade_items`           | Échange : les deux joueurs, état, dates ; accessoires donnés par chacun    |
| `friend_streaks`                  | Série de coups de patte entre deux amis : début, valeur, record, joker utilisé cette semaine |
| `gifts`                           | Cadeau : qui, à qui, accessoire, message, état (en attente, accepté, refusé, annulé) |
| `achievements`, `user_achievements` | Catalogue des succès (condition, seuil, accessoire éventuel) ; succès débloqués et date |
| `raids`, `raid_participants`    | Borne, jour, palier, skin ; joueurs passés                                   |
| `notifications`                   | Ce qui a été envoyé, pour respecter les plafonds                           |
| `message_templates`, `messages` | Textes et emotes ; messages envoyés                                          |
| `referrals`                       | Parrain, filleul, date de validation                                          |

Les jetons des QR codes n'ont pas besoin de table : ils sont signés par le serveur et portent leur date d'expiration. Les défis du jour sont des quêtes `daily` : ils utilisent les tables des quêtes. La table `accessories` gagne une colonne `tradable`.

Un échange déjà fait n'est pas annulé quand un compte est supprimé : les accessoires reçus appartiennent à l'autre joueur. Seul le nom du joueur supprimé disparaît de l'historique.

## 11. Écarté ou reporté, et pourquoi

| Idée                                             | Décision                                    | Raison                                                                                                                                                                                                                               |
| ------------------------------------------------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Détection de la marche ensemble par Bluetooth    | Reportée                                    | Le plugin actuel ne sait pas émettre : il faudrait du code Android natif, fragile en arrière-plan selon les marques de téléphones. Estimé à 2 ou 3 semaines pour un résultat incertain. Les positions et les bornes suffisent |
| Bonus en pourcentage sur les pas de la sortie     | Remplacé par un bonus fixe                  | Les pas sont comptés par jour : on ne sait pas lesquels ont été faits ensemble                                                                                                                                                    |
| QR code fixe                                      | Remplacé par un QR qui change chaque minute | Le scan rend amis ou fait entrer dans une squad sans validation : une photo du QR partagée en ligne le permettrait à n'importe qui                                                                                                 |
| Groupes permanents                                | Remplacés par des squads temporaires        | Plus simple, comme dans un jeu en ligne ; le défi de la semaine devient personnel                                                                                                                                                   |
| Défi sur les pas cumulés, défi qui fait perdre | Écartés                                    | Pas besoin d'être ensemble ; pression et culpabilité sur le moins actif                                                                                                                                                            |
| Chat libre                                        | Écarté                                     | Modération impossible à assurer                                                                                                                                                                                                    |
| Album des compagnons croisés                     | Reporté                                     | Demande de détecter les rencontres entre inconnus, donc le Bluetooth                                                                                                                                                                |

## Ordre de réalisation proposé

1. Sécurité et réglages de partage (§ 1 et § 2), écran Amis sur l'API existante, ajout par QR code, fiche du compagnon.
2. Coups de patte, série de coups de patte et notifications (§ 7).
3. Squads : invitation, QR, fenêtres de marche ensemble, bonus et moment partagé.
4. Défis du jour, quêtes à plusieurs, défi de la semaine, collection et succès.
5. Raid de borne, une fois le passage en borne en place.
6. Si le temps le permet : cadeaux, échanges d'objets, fil d'activité, messages prédéfinis et emotes, classement, parrainage.
