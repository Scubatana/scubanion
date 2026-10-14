#!/usr/bin/env bash
set -uo pipefail

MARKER="<!-- garde-des-pull-requests -->"

open_pull_requests() {
	gh pr list --state open --limit 100 --json number,baseRefName --jq '.[] | select(.baseRefName == "master" or .baseRefName == "main") | .number'
}

bot_comment_id() {
	gh api "repos/$GH_REPO/issues/$1/comments" --paginate --jq ".[] | select(.user.login == \"github-actions[bot]\" and (.body | contains(\"$MARKER\"))) | .id" | tail -n 1
}

say() {
	local number=$1 message=$2 id
	id=$(bot_comment_id "$number")

	if [ -n "$id" ]; then
		gh api -X PATCH "repos/$GH_REPO/issues/comments/$id" -f body="$MARKER
$message" > /dev/null
	else
		gh api -X POST "repos/$GH_REPO/issues/$number/comments" -f body="$MARKER
$message" > /dev/null
	fi
}

forget() {
	local id
	id=$(bot_comment_id "$1")

	if [ -n "$id" ]; then
		gh api -X DELETE "repos/$GH_REPO/issues/comments/$id" > /dev/null
	fi
}

wait_for_new_head() {
	local number=$1 previous=$2 current

	for _ in $(seq 1 30); do
		current=$(gh pr view "$number" --json headRefOid --jq .headRefOid)

		if [ "$current" != "$previous" ]; then
			return 0
		fi

		sleep 2
	done

	return 1
}

update() {
	local number=$1 state head sha base cross base_sha behind answer

	IFS=$'\t' read -r state head sha base cross < <(gh pr view "$number" --json state,headRefName,headRefOid,baseRefName,isCrossRepository --jq '[.state, .headRefName, .headRefOid, .baseRefName, .isCrossRepository] | @tsv')

	if [ "$state" != "OPEN" ]; then
		return 0
	fi

	if [ "$cross" = "true" ]; then
		say "$number" "Cette PR vient d'un fork : sa branche ne peut pas être mise à jour automatiquement. Fusionne \`$base\` dans ta branche avant la relecture."
		return 0
	fi

	base_sha=$(gh api "repos/$GH_REPO/git/ref/heads/$base" --jq .object.sha)
	behind=$(gh api "repos/$GH_REPO/compare/$base_sha...$sha" --jq .behind_by)

	if [ "$behind" = "0" ]; then
		forget "$number"
		return 0
	fi

	if ! answer=$(gh api -X PUT "repos/$GH_REPO/pulls/$number/update-branch" -f expected_head_sha="$sha" 2>&1); then
		if grep -qi "workflow" <<< "$answer"; then
			say "$number" "La branche n'a pas pu être mise à jour automatiquement : \`$base\` modifie des fichiers de \`.github/workflows\`, ce que l'automatisation n'a pas le droit de faire. Fusionne \`$base\` dans ta branche (\`git fetch origin\` puis \`git merge origin/$base\` et \`git push\`). La fusion reste bloquée tant que la branche n'est pas à jour."
		elif grep -qi "conflict" <<< "$answer"; then
			say "$number" "PR refusée : sa branche est en conflit avec \`$base\`. Fusionne \`$base\` dans ta branche, résous les conflits puis pousse. La fusion reste bloquée tant que la branche n'est pas à jour."
		else
			say "$number" "La branche n'a pas pu être mise à jour automatiquement avec \`$base\`. Fusionne \`$base\` dans ta branche puis pousse. La fusion reste bloquée tant que la branche n'est pas à jour."
		fi

		echo "PR #$number non mise à jour : $answer"
		return 1
	fi

	if ! wait_for_new_head "$number" "$sha"; then
		echo "PR #$number : la mise à jour n'est pas encore visible."
		return 1
	fi

	gh workflow run tests.yml --ref "$head"
	forget "$number"
	echo "PR #$number mise à jour avec $base, tests relancés."
}

if [ -n "${1:-}" ]; then
	update "$1"
	exit $?
fi

for number in $(open_pull_requests); do
	update "$number" || true
done
