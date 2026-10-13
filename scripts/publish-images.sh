#!/bin/sh
# Construit les images de prod décrites dans docker-compose.yml et les publie sur DockerHub,
# sous l'étiquette donnée et sous « latest ». Appelé par le job release:images de la CI.
# Usage : DOCKERHUB_NAMESPACE=<compte> scripts/publish-images.sh <étiquette>
set -eu

tag="${1:?Usage : scripts/publish-images.sh <étiquette>}"
: "${DOCKERHUB_NAMESPACE:?DOCKERHUB_NAMESPACE manquant : le compte ou l'organisation DockerHub}"

export IMAGE_TAG="$tag"

docker compose -f docker-compose.yml build
docker compose -f docker-compose.yml push

for image in $(docker compose -f docker-compose.yml config --images); do
	latest="${image%:*}:latest"
	docker tag "$image" "$latest"
	docker push "$latest"
done
