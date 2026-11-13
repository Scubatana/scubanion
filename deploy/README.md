# Déploiement Ansible — Scubanion

Ce dossier formalise le déploiement automatisé de la pile Docker Compose en production (exigence R5.07).

## Prérequis sur la machine cible

- Docker Engine + plugin `docker compose` (v2)
- Répertoire applicatif (par défaut `/opt/scubatana/scubanion`) contenant le `.env` de production généré hors dépôt Git (`APP_KEY`, `DB_*`, `MQTT_*`, `BREVO_API_KEY`, `GRAFANA_ADMIN_PASSWORD`, `METRICS_TOKEN`).

## Exécution

Aucune adresse IP ni aucun secret n'est stocké dans ce dépôt : les paramètres passent par variables d'environnement ou fichier d'inventaire local non versionné.

```bash
cp deploy/inventory.example deploy/inventory.ini
# Renseigner DEPLOY_HOST, IMAGE_TAG et DOCKERHUB_NAMESPACE dans l'environnement
ansible-playbook -i deploy/inventory.ini deploy/deploy.yml
```

## Retour arrière (rollback)

```bash
IMAGE_TAG=v0.x.y ansible-playbook -i deploy/inventory.ini deploy/deploy.yml
```
