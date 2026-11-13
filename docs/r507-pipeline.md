# Architecture CI/CD, déploiement et supervision (R5.07)

Ce document décrit la chaîne d'intégration et de déploiement continu du projet Scubanion ainsi que les choix techniques retenus pour la ressource **R5.07**.

## 1. Dépôts et stratégie Git

- **Dépôt de travail** : GitHub (`Scubatana/scubanion`), sur le modèle **GitHub Flow** :
  - `master` protégée (aucun push direct, merge commit obligatoire, 1 approbation externe, checks CI requis).
  - Branches courtes par fonctionnalité ou correction (`feature/...`, `fix/...`, `docs/...`, `ci/...`).
  - Tags `vX.Y.Z` réservés aux versions livrables.
- **Miroir évalué** : GitLab IUT, synchronisé automatiquement depuis GitHub (`.github/workflows/miroir-gitlab.yml`), sur lequel s'exécute le pipeline complet (`picsou06`).
- **Commits** : format Conventional Commits en français (`feat:`, `fix:`, `docs:`, `ci:`, `test:`).

## 2. Chaîne CI/CD de bout en bout

```mermaid
flowchart LR
  DEV["Poste membre"] -->|"Push / PR"| GH["GitHub Actions<br/>Tests API + MariaDB + Borne + App"]
  GH -->|"Miroir auto"| GL["GitLab IUT (.gitlab-ci.yml)"]
  GL --> T["1. Test<br/>PHPUnit (SQLite & MariaDB), Vitest + LCOV"]
  T --> Q["2. Quality<br/>SonarQube (sonar-project.properties)"]
  Q --> B["3. Build<br/>Images Docker Alpine + APK Android signé"]
  B --> V["4. Verify<br/>Tests smoke Docker (HTTP, Santé, MQTT)"]
  V --> R["5. Release<br/>Publication DockerHub + tag APK"]
  R --> D["6. Deploy<br/>SSH + Ansible (deploy/deploy.yml)"]
  D -.-> M["7. Monitoring<br/>Prometheus -> Grafana"]
```

## 3. Qualité logicielle et conteneurisation

- **Images multi-stage et légères** :
  - `scubatana.backend/Dockerfile` : stage builder Composer sans dépendances de dev (`--no-dev`), runtime `php:8.4-fpm-alpine` avec `pdo_mysql`, `opcache`, `pcntl` (arrêt propre des workers MQTT sur `SIGTERM`/`SIGINT`), `fcgi` pour le healthcheck PHP-FPM, et `.dockerignore`.
  - `scubatana.nginx/Dockerfile` : `nginx:1.27-alpine` servant les fichiers statiques depuis le contexte backend et exposant les métriques uniquement sur le port interne `9091`.
  - Seule `mariadb:11.4` conserve l'image officielle (absence de variante Alpine officielle).
- **Aucun secret ni IP en dur** : communications entre services par noms DNS Docker (`mariadb`, `backend`, `mosquitto`, `nginx`, `prometheus`, `grafana`) ; séparation des réseaux (`donnees` en `internal: true`, `iot`, `web`).
- **Analyse statique et couverture** : `sonar-project.properties` agrège les sources PHP, TypeScript/Vue et C++ avec le rapport `scubatana.frontend/coverage/lcov.info` (et `clover.xml` lorsque PCOV/Xdebug est disponible).

## 4. Déploiement, supervision et passage à l'échelle

- **Déploiement automatisé** : décrit dans [deploy/deploy.yml](../deploy/deploy.yml) et déclenché en production par le stage `deploy` de `.gitlab-ci.yml`.
- **Supervision (`Prometheus` + `Grafana`)** :
  - Laravel expose `/metrics` (`MetricsController`) au format Prometheus (`scubanion_users_total`, `scubanion_steps_today_total`, `scubanion_passages_total`, `scubanion_terminals_online`, `scubanion_active_quests_total`, `scubanion_reports_pending`).
  - L'endpoint est protégé en production par `METRICS_TOKEN` ou restreint au listener Nginx interne sur le port `9091` (non publié hors du réseau Docker `web`).
  - `scubatana.prometheus/` et `scubatana.grafana/` provisionnent automatiquement la source de données et le tableau de bord *Scubanion — Supervision*.
- **Passage à l'échelle (pistes prévues)** :
  - Le backend API (`php-fpm`), le planificateur (`scheduler`) et le consommateur MQTT (`bornes:ecouter`) sont déjà séparés en conteneurs distincts.
  - Les sessions/tokens Sanctum et la validation de jetons NFC sont en base MariaDB : l'API HTTP est stateless et peut être répliquée derrière un répartiteur de charge en amont de Nginx/PHP-FPM.
  - Les sessions d'écoute MQTT peuvent utiliser des abonnements partagés MQTT v5 (`$share/workers/scubanion/bornes/#`) en cas de montée en charge du parc de bornes.
