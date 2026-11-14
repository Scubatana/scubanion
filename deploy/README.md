# Déploiement Ansible — Scubanion

Ce dossier formalise le déploiement de la pile Docker Compose en production (exigence R5.07). Le pipeline GitLab déploie, lui, par SSH avec le script du VPS : ce playbook n'est pas lancé par la CI, il sert à réinstaller le serveur ou à revenir à une version précédente.

## Prérequis sur la machine cible

- Docker Engine + plugin `docker compose` (v2)
- Répertoire applicatif (par défaut `/opt/scubatana/scubanion`) contenant le `.env` de production généré hors dépôt Git (`APP_KEY`, `DB_*`, `MQTT_*`, `BREVO_API_KEY`, `GRAFANA_ADMIN_PASSWORD`, `METRICS_TOKEN`).

## Exécution

Aucune adresse IP ni aucun secret n'est stocké dans ce dépôt : les paramètres passent par variables d'environnement ou fichier d'inventaire local non versionné.

```bash
cp deploy/inventory.example.yml deploy/inventory.yml
# Renseigner DEPLOY_HOST, IMAGE_TAG et DOCKERHUB_NAMESPACE dans l'environnement
# (DEPLOY_USER et DEPLOY_SSH_KEY_PATH sont facultatifs : deploy et ~/.ssh/deploy_key par défaut)
ansible-playbook -i deploy/inventory.yml deploy/deploy.yml
```

## Retour arrière (rollback)

```bash
IMAGE_TAG=v0.x.y ansible-playbook -i deploy/inventory.yml deploy/deploy.yml
```
