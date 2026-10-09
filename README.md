# Scoubanion

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
| `scubatana.backend` | API | PHP 8.3 / Laravel |
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
php artisan migrate
php artisan serve
```

En production, `docker-compose.yml` (racine) conteneurise aussi `backend` et `nginx`.

### App mobile Android (build + installation)

Depuis `scubatana.frontend` (Node >= 22 requis pour Capacitor) :

```bash
npm install
npx cap add android   # une seule fois, génère le dossier android/
```

Puis, téléphone Android branché en USB avec le débogage activé :

```bash
npm run adb:install
```

Cette commande build une version release signée et l'installe sur l'unique téléphone connecté, en mise à jour (`adb install -r`, sans perte des données locales de l'app). La clé de signature dev (`android-keystore/scoubanion-dev.keystore`) est versionnée dans le dépôt pour simplifier le partage entre membres de l'équipe.