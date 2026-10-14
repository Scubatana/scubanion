# Scubanion

Projet scolaire (équipe de 4, IUT). Un animal virtuel connecté (type Tamagotchi) dont la santé et la progression dépendent de l'activité physique réelle de son propriétaire (marche, pas comptés par le podomètre du téléphone) et de ses interactions sociales réelles (rencontres physiques avec d'autres utilisateurs).

## Comment ça marche

- Les pas sont lus par le podomètre du téléphone (capteur de pas Android), sans matériel supplémentaire. Le seul objet connecté est la borne NFC.
- Le nombre de pas fait gagner de l'XP à l'animal.
- Des quêtes géolocalisées invitent à se rendre à des points d'intérêt réels.
- Quand deux utilisateurs se croisent physiquement (détection Bluetooth entre téléphones, recoupée par géolocalisation côté serveur), un bonus est déclenché.

## Architecture

| Dossier | Rôle | Stack |
|---|---|---|
| `scubatana.frontend` | App mobile Android (podomètre du téléphone, détection de proximité BLE) | Nuxt 4 / Vue 3 / TypeScript, empaqueté avec Capacitor |
| `scubatana.backend` | API | PHP 8.4 / Laravel |
| `scubatana.mariadb` | Base de données | MariaDB |
| `scubatana.nginx` | Reverse proxy (prod uniquement) | nginx |
| `scubatana.phpmyadmin` | Admin base de données | phpMyAdmin |
| `scubatana.demo` | Maquette visuelle statique des écrans (HTML/CSS, sans logique), référence de design pour la future app web | - |

L'application web de consultation (animal, historique, quêtes) n'est pas encore développée.

## Lancer le projet en développement

```bash
cp .env.exemple .env
cp scubatana.frontend/.env.example scubatana.frontend/.env   # si absent, puis renseigner les mots de passe du keystore
cp scubatana.backend/.env.example scubatana.backend/.env     # si absent

docker compose -f dev-docker-compose.yml up -d mariadb phpmyadmin
docker compose -f dev-docker-compose.yml up frontend
```

Le backend n'est pas lancé par docker compose en dev (PHP-FPM derrière nginx n'apporte rien en local, le hot reload PHP marche déjà sans conteneur) :

```bash
cd scubatana.backend
composer install
php artisan migrate
php artisan serve
```

L'API répond alors sur `http://localhost:8000/api`. Ses routes : `POST /auth/code`, `POST /auth/verify`, `POST /auth/logout`, `GET /me`, `DELETE /me?confirm=1` (suppression du compte), `PUT /me/profile`, `PUT /me/companion`, `GET /companion/catalog`, `GET /me/steps`, `POST /me/steps`, `PUT /me/goal`.

Les pas sont lus sur le téléphone par un module Android natif (`StepCounterPlugin`, capteur `STEP_COUNTER`) et envoyés au serveur, qui les plafonne (`STEPS_MAX_PER_HOUR`, `STEPS_MAX_PER_DAY`). L'apparence du compagnon suit la norme décrite dans `docs/skins-compagnon.md`.

### Envoi des mails (code de connexion)

La connexion se fait par email et code à 6 chiffres. L'envoi passe par l'API de Brevo. Dans `scubatana.backend/.env` :

```bash
MAIL_MAILER=brevo
BREVO_API_KEY=            # clé d'API Brevo (xkeysib-...), jamais commitée
MAIL_FROM_ADDRESS=        # adresse d'expéditeur validée dans Brevo
MAIL_FROM_NAME="Scubanion"
```

Sans clé, laisser `MAIL_MAILER=log` : le code est alors écrit dans `storage/logs/laravel.log`. Si Brevo répond que l'adresse IP n'est pas reconnue, l'autoriser dans les réglages de sécurité du compte Brevo.

### Tests

```bash
cd scubatana.backend && php artisan test     # API, sur SQLite en mémoire, mails simulés
cd scubatana.frontend && npm test            # app, avec Vitest
```

### CI/CD (GitLab)

Le pipeline est décrit dans `.gitlab-ci.yml` et ne tourne que sur la branche par défaut (`master`) du dépôt GitLab `de402549/scubanion`. Les jobs portent l'étiquette `picsou06` : ils s'exécutent sur le runner Docker du VPS, pas sur celui de l'IUT.

| Étape | Jobs | Rôle |
|---|---|---|
| `test` | `test:backend`, `test:frontend` | `php artisan test` et `npm test` |
| `build` | `build:backend`, `build:frontend` | dépendances de production de l'API, `nuxt generate` avec l'adresse de l'API en ligne |
| `verify` | `verify:backend`, `verify:frontend` | migrations sur une vraie MariaDB puis appel de `/up` et de `/api/me`, service du site généré |
| `release` | `release` | étiquette `v0.<numéro du pipeline>` et release GitLab avec l'archive du site généré |
| `release` | `release:images` | construit les images de prod et les publie sur DockerHub (`scripts/publish-images.sh`), sous l'étiquette de la release et sous `latest` |
| `deploy` | `deploy` | lance `deploy.sh` du projet `scubatana` sur le VPS |

Le job `deploy` n'existe que si la variable `DEPLOY_SSH_KEY` est définie. Il attend trois variables CI/CD protégées, de type « Variable » : `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` et `DEPLOY_HOST`.

Le job `release:images` n'existe que si la variable `DOCKERHUB_TOKEN` est définie. Il attend `DOCKERHUB_USERNAME` et `DOCKERHUB_TOKEN` (jeton d'accès DockerHub, masqué), et `DOCKERHUB_NAMESPACE` si les images vont dans une organisation plutôt que sur le compte. Il construit les images avec Docker-in-Docker : le runner doit l'autoriser (`privileged = true`).

### Production (docker-compose)

`docker-compose.yml` (racine) lance l'application complète à partir de trois images :

| Service | Image | Rôle |
| ------- | ----- | ---- |
| `mariadb` | `scubanion-mariadb` | base de données, données dans le volume `mariadb_data` |
| `backend` | `scubanion-backend` | API Laravel sous php-fpm. Image multi-stage : les dépendances sont installées au build, sans celles de dev ; ni git, ni composer, ni les tests dans l'image finale |
| `nginx` | `scubanion-nginx` | point d'entrée HTTP, exposé sur `${NGINX_PORT}`. Il embarque `public/` et transmet le PHP à `backend:9000` |

```bash
cp .env.exemple .env      # puis renseigner les mots de passe et APP_KEY
docker compose up -d --build
```

- `APP_KEY` se génère avec `php artisan key:generate --show` (depuis `scubatana.backend`). Sans elle, le conteneur `backend` refuse de démarrer.
- Au démarrage, `backend` met en cache la configuration, les routes et les vues, puis lance les migrations.
- Chaque image a un `HEALTHCHECK` : MariaDB répond au ping, php-fpm répond sur `/ping`, nginx répond sur `/healthz`. Le compose attend qu'un service soit en bonne santé avant de lancer celui qui en dépend (`mariadb`, puis `backend`, puis `nginx`).
- Deux réseaux : `donnees` (interne, sans accès à Internet) relie `mariadb` et `backend` ; `web` relie `backend` et `nginx`. nginx ne voit pas la base.
- Le code est dans les images, pas dans un volume : un redéploiement sert toujours la nouvelle version.
- L'image nginx se construit par `docker compose build` : elle récupère `public/` dans `scubatana.backend` grâce au contexte de build supplémentaire `backend` (`additional_contexts`). Un `docker build` seul demande `--build-context backend=scubatana.backend`.
- Les images portent le nom `${DOCKERHUB_NAMESPACE}/scubanion-<service>:${IMAGE_TAG}`. Pour déployer une version publiée sans rebuild : `docker compose pull && docker compose up -d`.

### App mobile Android (build + installation)

Depuis `scubatana.frontend` (Node >= 22 requis pour Capacitor) :

```bash
npm install
npx cap add android   # une seule fois, génère le dossier android/
```

Puis, téléphone Android branché en USB avec le débogage activé.

Pour développer, avec l'API lancée sur ce poste (`php artisan serve`) :

```bash
npm run adb:install:debug
```

Cette commande active le pont USB (`adb reverse`, le `localhost:8000` du téléphone pointe vers ce poste), build une version debug et l'installe. L'app ne joint l'API que téléphone branché. Le pont saute dès que le câble est débranché ou qu'adb redémarre : l'app affiche alors « Impossible de joindre le serveur ». Il se rétablit sans rebuild avec `adb reverse tcp:8000 tcp:8000`. Le trafic HTTP non chiffré n'est autorisé que vers `localhost` et que dans ce build debug.

Pour une version release signée, qui appelle le serveur en ligne :

```bash
npm run adb:install
```

Cette commande build une version release signée et l'installe sur l'unique téléphone connecté, en mise à jour (`adb install -r`, sans perte des données locales de l'app). La clé de signature dev (`android-keystore/scubanion-dev.keystore`) est versionnée dans le dépôt pour simplifier le partage entre membres de l'équipe. L'adresse de l'API est choisie au build par le script : `NUXT_PUBLIC_API_URL_DEV` pour `adb:install:debug` (par défaut `http://localhost:8000`) et `NUXT_PUBLIC_API_URL_PROD` pour `adb:install` (par défaut `https://scubatana.picsou06.fr`). Les deux se règlent dans `scubatana.frontend/.env`.

Une version debug et une version release ne peuvent pas se remplacer l'une l'autre : désinstaller l'app avant de changer de type de build.