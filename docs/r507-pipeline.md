# Architecture CI/CD, déploiement et supervision (R5.07)

Ce document décrit la chaîne d'intégration et de déploiement continu du projet Scubanion ainsi que les choix techniques retenus pour la ressource **R5.07**.

## 1. Dépôts et stratégie Git

- **Dépôt de travail** : GitHub (`Scubatana/scubanion`). Les règles complètes sont dans [CONTRIBUTING.md](../CONTRIBUTING.md).
  - `dev` : branche d'intégration et branche par défaut. Chaque tâche part de `dev` sur une branche courte (`feature/...`, `fix/...`, `docs/...`, `ci/...`, `build/...`) et y revient par PR : relecture d'un autre membre, CI verte, commit de merge (pas de squash ni de rebase).
  - `master` : la version livrée. Elle ne reçoit que des PR `dev` → `master`, une par version. La fusion crée le tag `vX.Y.Z` et la release GitHub (`.github/workflows/release.yml`), avec un numéro calculé depuis les Conventional Commits.
  - `garde-des-pull-requests.yml` redirige vers `dev` toute PR qui vise `master` depuis une autre branche que `dev`, et signale en commentaire une PR en retard sur sa branche cible : l'auteur fusionne lui-même, pour que le commit de merge lui soit attribué (Git est noté sur GitLab IUT).
- **Miroir évalué** : GitLab IUT, synchronisé automatiquement depuis GitHub (`.github/workflows/miroir-gitlab.yml`) : branches copiées à l'identique, tags ajoutés sans jamais être supprimés. Son runner (`picsou06`) exécute le pipeline officiel.
- **Commits** : format Conventional Commits en français (`feat:`, `fix:`, `docs:`, `ci:`, `test:`, `build:`).

## 2. Chaîne CI/CD de bout en bout

```mermaid
flowchart LR
  DEV["Poste d'un membre"] -->|"PR vers dev"| GHA["GitHub Actions<br/>Tests API (SQLite, MariaDB), borne, app"]
  GHA -->|"PR dev → master"| REL["Release GitHub<br/>tag vX.Y.Z"]
  REL -->|"miroir"| GL["GitLab IUT<br/>.gitlab-ci.yml"]
  GL --> T["1. test<br/>PHPUnit + pcov, Vitest + LCOV"]
  T --> Q["2. quality<br/>SonarQube"]
  Q --> B["3. build<br/>dépendances, site Nuxt, APK signé"]
  B --> V["4. verify<br/>API démarrée : /up et 401 sur /api/me<br/>site statique servi"]
  V --> R["5. release<br/>release GitLab (site + APK)<br/>images DockerHub vX.Y.Z"]
  R --> D["6. deploy<br/>SSH vers le VPS"]
  D -.-> M["Supervision<br/>Prometheus → Grafana"]
```

| Événement | GitHub Actions | GitLab CI (runner IUT) |
| --------- | -------------- | ---------------------- |
| PR vers `dev` ou `master` | Tests API, Tests API MariaDB, Tests borne, Tests app ; garde des PR | — |
| Fusion dans `master` | Tag `vX.Y.Z` et release GitHub ; miroir | test → quality → build → verify |
| Tag `vX.Y.Z` (reçu par le miroir) | — | test → quality → build → verify → release → deploy |

Le stage `quality` ne tourne que si `SONAR_TOKEN` et `SONAR_HOST_URL` sont définis dans les variables masquées du projet GitLab ; `release:images` demande `DOCKERHUB_TOKEN` et `deploy` demande `DEPLOY_SSH_KEY`.

## 3. Qualité logicielle et conteneurisation

- **Images multi-stage et légères** :
  - `scubatana.backend/Dockerfile` : stage builder Composer sans dépendances de dev (`--no-dev`), runtime `php:8.4-fpm-alpine` avec `pdo_mysql`, `opcache`, `pcntl` (arrêt propre de `bornes:ecouter` sur `SIGTERM`/`SIGINT`), `fcgi` pour le healthcheck PHP-FPM, et `.dockerignore`.
  - `scubatana.nginx/Dockerfile` : `nginx:1.27-alpine` servant les fichiers statiques depuis le contexte backend, avec un second vhost sur le port interne `9091`, non publié, réservé au scrape de `/metrics`.
  - Seule `mariadb:11.4` conserve l'image officielle (absence de variante Alpine officielle).
- **Aucun secret ni IP en dur** : communications entre services par noms DNS Docker (`mariadb`, `backend`, `mosquitto`, `nginx`, `prometheus`, `grafana`) ; séparation des réseaux (`donnees` en `internal: true`, `iot`, `web`) ; mot de passe Grafana obligatoire dans `.env`, sans valeur par défaut.
- **Analyse statique et couverture** : `sonar-project.properties` analyse le PHP de l'API et le TypeScript/Vue de l'app, avec `clover.xml` (PHPUnit, pilote pcov installé dans le job) et `lcov.info` (Vitest, chemins écrits depuis la racine du dépôt). Le firmware C++ n'est pas analysé par SonarQube, qui exigerait une base de compilation et le compilateur de l'ESP32 dans le job : sa logique est testée par `pio test -e native` dans GitHub Actions.

## 4. Déploiement, supervision et passage à l'échelle

- **Déploiement automatisé** : le job `deploy` de `.gitlab-ci.yml` se connecte en SSH au VPS (`deploy@$DEPLOY_HOST`, clé et empreinte du serveur en variables masquées) et y lance le script de déploiement (commande `scubatana`).
- **Playbook Ansible** : [deploy/deploy.yml](../deploy/deploy.yml) décrit les mêmes étapes de façon reproductible (copie de la configuration, `docker compose pull` des images taguées, `up -d`, contrôle de `/up`). Il n'est pas lancé par la CI : c'est une alternative manuelle pour réinstaller le VPS ou revenir à une version précédente (`IMAGE_TAG=vX.Y.Z`). Mode d'emploi dans [deploy/README.md](../deploy/README.md).
- **Supervision (`Prometheus` + `Grafana`)** :
  - Laravel expose `/metrics` (`MetricsController`) au format Prometheus : `scubanion_users_total`, `scubanion_steps_today_total`, `scubanion_passages_total`, `scubanion_terminals_total`, `scubanion_terminals_online`, `scubanion_terminal_online{code}` (signal de vie de chaque borne, BOR-08), `scubanion_active_quests_total`, `scubanion_reports_pending`.
  - Prometheus lit `/metrics` par le vhost Nginx interne (port `9091`). Depuis l'extérieur, l'endpoint exige `METRICS_TOKEN` (Bearer) ; sans jeton, il est refusé en production. La route ne crée ni session ni cookie.
  - Prometheus et Grafana ne sont publiés que sur `127.0.0.1` de l'hôte (`MONITORING_BIND`) : on les consulte par un tunnel SSH.
  - `scubatana.prometheus/` et `scubatana.grafana/` provisionnent automatiquement la source de données et le tableau de bord *Scubanion — Supervision*.
- **Passage à l'échelle (pistes prévues)** :
  - Le backend API (`php-fpm`), le planificateur (`scheduler`) et le consommateur MQTT (`bornes:ecouter`) sont déjà séparés en conteneurs distincts.
  - Les sessions/tokens Sanctum et la validation de jetons NFC sont en base MariaDB : l'API HTTP est stateless et peut être répliquée derrière un répartiteur de charge en amont de Nginx/PHP-FPM.
  - Les sessions d'écoute MQTT peuvent utiliser des abonnements partagés (`$share/workers/scubanion/bornes/#`) en cas de montée en charge du parc de bornes.
