# Sécurité

Scubanion traite des données personnelles (position, pas, date de naissance, rencontres entre joueurs) et des secrets de bornes. Une faille ne se signale **pas** dans une issue publique.

## Signaler une faille

- Dans l'onglet **Security** du dépôt GitHub, bouton **Report a vulnerability** (signalement privé) ;
- ou directement à un membre de l'équipe, par message privé.

Indiquer la brique concernée (app, API, borne, infrastructure), comment reproduire et l'impact estimé. L'équipe répond sous une semaine ; le correctif passe par une PR vers `develop` et sort à la release suivante.

## Périmètre

- L'API (`scubatana.backend`) et son déploiement sur `scuba.picsou06.fr` ;
- l'app Android (`scubatana.frontend`) ;
- le firmware des bornes (`scubatana.borne`), le broker MQTT et le jeton NFC signé ;
- la chaîne CI/CD (GitHub Actions, GitLab CI) et les images DockerHub.

Limite déjà connue et documentée : MQTT circule en clair sur le port 1883 en attendant le passage en TLS ([docs/mqtt.md](docs/mqtt.md)).
