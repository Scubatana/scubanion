#!/usr/bin/env bash
# Calcule la prochaine version vX.Y.Z depuis les Conventional Commits fusionnés depuis le dernier tag,
# et écrit les notes de version dans notes.md. Sortie : tag=vX.Y.Z (vide s'il n'y a rien à publier).
#
#   feat → version mineure ; fix, docs, ci... → correctif ; « type!: » ou « BREAKING CHANGE: » → majeure
#   (en 0.x, un changement cassant ne passe qu'à la mineure suivante : la 1.0.0 se publie à la main).
#
# Variables : EVENT (push ou workflow_dispatch), VERSION (forcée, facultative), GITHUB_OUTPUT, GITHUB_REPOSITORY.
set -euo pipefail

output() {
	echo "tag=$1" >> "${GITHUB_OUTPUT:-/dev/stdout}"
}

subject=$(git log -1 --format=%s)

if [ "${EVENT:-push}" = "push" ] && ! [[ "$subject" =~ ^Merge\ pull\ request\ \#[0-9]+\ from\ [^/]+/dev$ ]]; then
	echo "Ce commit ne vient pas d'une PR dev → master : pas de release."
	output ""
	exit 0
fi

last=$(git describe --tags --abbrev=0 --match 'v[0-9]*.[0-9]*.[0-9]*' 2>/dev/null || true)
range="${last:+$last..}HEAD"

if [ -n "$last" ] && [ "$(git rev-list --count "$range")" = "0" ]; then
	echo "Rien de nouveau depuis $last."
	output ""
	exit 0
fi

breaking='^[a-z]+(\([^)]*\))?!:|^BREAKING[ -]CHANGE:'

if [ -n "${VERSION:-}" ]; then
	version="${VERSION#v}"

	if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
		echo "Version invalide : $VERSION (attendu : X.Y.Z)" >&2
		exit 1
	fi
else
	IFS=. read -r major minor patch <<< "${last#v}"
	major=${major:-0}
	minor=${minor:-0}
	patch=${patch:-0}
	log=$(git log --no-merges --format='%s%n%b' "$range")

	if [ "$major" != "0" ] && grep -qE "$breaking" <<< "$log"; then
		version="$((major + 1)).0.0"
	elif grep -qE "$breaking|^feat(\([^)]*\))?:" <<< "$log"; then
		version="$major.$((minor + 1)).0"
	else
		version="$major.$minor.$((patch + 1))"
	fi
fi

tag="v$version"

if git rev-parse -q --verify "refs/tags/$tag" > /dev/null; then
	echo "Le tag $tag existe déjà." >&2
	exit 1
fi

separator=$(printf '\037')

# Une ligne par commit : « sujet (hash court, auteur) », groupées par type. Le mode « autres » prend
# les commits qui ne correspondent pas au motif, et garde leur sujet entier.
section() {
	local title=$1 pattern=$2 mode=${3:-type} lines text hash author

	if [ "$mode" = "autres" ]; then
		lines=$(git log --no-merges --format="%s${separator}%h${separator}%an" "$range" | grep -vE "$pattern" || true)
	else
		lines=$(git log --no-merges --format="%s${separator}%h${separator}%an" "$range" | grep -E "$pattern" || true)
	fi

	if [ -z "$lines" ]; then
		return 0
	fi

	printf '### %s\n\n' "$title"

	while IFS="$separator" read -r text hash author; do
		if [ "$mode" != "autres" ]; then
			text="${text#*: }"
		fi

		printf -- '- %s (%s, %s)\n' "$text" "$hash" "$author"
	done <<< "$lines"

	printf '\n'
}

{
	printf '## Scubanion %s\n\n' "$tag"
	section "Nouveautés" '^feat(\([^)]*\))?!?:'
	section "Corrections" '^fix(\([^)]*\))?!?:'
	section "Documentation" '^docs(\([^)]*\))?!?:'
	section "Tests" '^test(\([^)]*\))?!?:'
	section "CI et build" '^(ci|build)(\([^)]*\))?!?:'
	section "Autres changements" '^(feat|fix|docs|test|ci|build)(\([^)]*\))?!?:' autres

	merged=$(git log --merges --format=%s "$range" | sed -nE 's/^Merge pull request (#[0-9]+) from [^/]+\/(.+)$/\1 \2/p' | grep -v ' dev$' || true)

	if [ -n "$merged" ]; then
		printf '### Pull requests\n\n'

		while read -r number branch; do
			printf -- '- %s (%s)\n' "$number" "$branch"
		done <<< "$merged"

		printf '\n'
	fi

	if [ -n "$last" ]; then
		printf '**Tous les changements** : https://github.com/%s/compare/%s...%s\n' "${GITHUB_REPOSITORY:-Scubatana/scubanion}" "$last" "$tag"
	fi
} > notes.md

echo "Prochaine version : $tag (dernier tag : ${last:-aucun})"
output "$tag"
