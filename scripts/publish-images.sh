#!/bin/sh
set -eu

tag="${1:?Usage : scripts/publish-images.sh <étiquette>}"
if [ -z "${DOCKERHUB_NAMESPACE:-}" ]; then
	echo "DOCKERHUB_NAMESPACE manquant : le compte ou l'organisation DockerHub" >&2
	exit 1
fi

export IMAGE_TAG="$tag"

docker compose -f docker-compose.yml build
docker compose -f docker-compose.yml push

for image in $(docker compose -f docker-compose.yml config --images); do
	latest="${image%:*}:latest"
	docker tag "$image" "$latest"
	docker push "$latest"
done
