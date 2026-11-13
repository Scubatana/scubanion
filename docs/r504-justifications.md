# Justifications des protocoles et standards IoT (R5.04 — Décision D5)

Ce document justifie les choix de protocoles, supports de communication et standards IoT retenus dans Scubanion, ainsi que les raisons pour lesquelles certains outils vus en cours ne sont pas utilisés dans cette version.

## 1. Synthèse de la pile retenue

| Couche | Choix retenu | Rôle dans Scubanion | Justification technique |
| ------ | ------------ | ------------------- | ----------------------- |
| **Capteurs smartphone** | Podomètre matériel Android (`TYPE_STEP_COUNTER`) + Google Play Local Recording | Comptage des pas quotidiens et rattrapage hors ligne | Consommation énergétique minimale (coprocesseur capteur), pas de service permanent en arrière-plan |
| **Interaction physique borne** | **NFC NDEF** (ST25DV16K côté borne, tag URI HTTPS) | Déclenchement d'un passage à la borne par contact (< 4 cm) | Prouve la proximité physique instantanée sans appairage préalable ni application tierce requise au moment du tap (l'OS ouvre directement `https://scuba.picsou06.fr/b/{jeton}`) |
| **Proximité sociale** | **BLE** *(prévu)* | Détection de proximité entre joueurs sur le terrain | Standard bas niveau d'énergie supporté nativement sur Android ; consentement explicite requis |
| **Téléphone ↔ Serveur** | **HTTPS / REST JSON** + jetons Bearer Laravel Sanctum | Synchronisation des pas, quêtes, amis, passages en borne | Chiffrement TLS natif, compatibilité avec les captures hors ligne rejouées sous 24 h, débogage simple et cache HTTP sur les catalogues |
| **Borne ↔ Serveur** | **MQTT** (Mosquitto, port 1883, pas encore chiffré : limite connue, passage en TLS sur le port 8883 prévu, voir [mqtt.md](mqtt.md)) | Signal de vie (`etat`) des bornes et envoi des messages d'écran (`affichage`) | Protocole léger adapté aux microcontrôleurs ESP32 sous Wi-Fi, support natif du **LWT** (Last Will and Testament) pour détecter les pannes réseau et des messages **retained** |
| **Authenticité des passages** | **HMAC-SHA256** sur `{code}.{heure_base36}` | Validation hors ligne et en ligne des jetons de passage | Chaque borne ne stocke que son secret ; aucune connectivité synchrone critique n'est requise côté téléphone au moment du scan (tolérance hors ligne 24 h) |
| **Horloge borne** | **NTP**, puis **RTC DS3231** une fois montée | Horodatage des jetons pour l'anti-rejeu | Aujourd'hui, la borne prend l'heure par NTP au démarrage. La DS3231, pas encore montée, gardera une heure fiable même après une coupure Wi-Fi grâce à sa pile de sauvegarde |

## 2. Décision D5 : pourquoi Apache Kafka et oneM2M ne sont pas retenus en v1

Dans le cadre du cours R5.04, **MQTT**, **Apache Kafka** et **oneM2M** ont été étudiés. Après évaluation par rapport aux contraintes du projet (monolithique modulaire déployé sur un seul VPS, quelques dizaines de bornes au lancement, sobriété numérique), seuls **MQTT + REST JSON** sont retenus pour la version 1 :

### Pourquoi pas Apache Kafka ?
1. **Empreinte mémoire et exploitation** : un cluster Kafka (JVM, stockage de segments, coordination KRaft) consomme à lui seul plusieurs centaines de Mo à plusieurs Go de RAM, ce qui est disproportionné face aux autres services du VPS (MariaDB, PHP-FPM, Mosquitto, Nginx, Prometheus/Grafana).
2. **Adéquation au besoin** : les messages IoT de Scubanion sont à faible débit (un signal de vie toutes les 30 secondes par borne sur `etat`, et un court message sur `affichage` lors d'un passage) et orientés temps réel léger vers un microcontrôleur ESP32. Mosquitto répond directement au besoin en quelques Mo de RAM.
3. **Seuil de bascule identifié** : si le parc dépassait plusieurs milliers de bornes avec analytique temps réel multi-consommateurs, un pont MQTT → Kafka (Kafka Connect / EMQX) pourrait être inséré sans modifier le firmware des bornes.

### Pourquoi pas oneM2M ?
1. **Complexité d'interopérabilité inutile en v1** : oneM2M (CSE, AE, arborescence de ressources `<container>` / `<contentInstance>`, bindings HTTP/MQTT/CoAP dédiés) est conçu pour fédérer des écosystèmes IoT hétérogènes multi-opérateurs à grande échelle. Dans Scubanion, la borne, l'application mobile et l'API appartiennent au même domaine fonctionnel maîtrisé par l'équipe.
2. **Surcharge de développement et de bande passante** : maintenir une couche oneM2M alourdirait la charge utile sur l'ESP32 et dupliquerait le modèle métier déjà géré en base relationnelle (bornes, passages, quêtes) sans apporter de service supplémentaire au joueur ni au partenaire B2B.
3. **Évolutivité** : les topics MQTT sont déjà normalisés (`scubanion/bornes/{code}/etat`, `affichage`), ce qui permettrait d'exposer ultérieurement une passerelle IPE (Interworking Proxy Entity) vers un CSE oneM2M si une collectivité l'exigeait.
