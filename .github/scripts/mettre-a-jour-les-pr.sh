#!/usr/bin/env bash
set -uo pipefail

MARKER="<!-- garde-des-pull-requests -->"

# PR ouvertes qui visent la branche qui vient de recevoir un push ($BRANCH).
open_pull_requests() {
	gh pr list --state open --base "${BRANCH:?BRANCH manquant}" --limit 100 --json number --jq '.[].number'
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

update() {
	local number=$1 state head sha base cross base_sha behind

	IFS=$'\t' read -r state head sha base cross < <(gh pr view "$number" --json state,headRefName,headRefOid,baseRefName,isCrossRepository --jq '[.state, .headRefName, .headRefOid, .baseRefName, .isCrossRepository] | @tsv')

	if [ "$state" != "OPEN" ]; then
		return 0
	fi

	# La PR de release dev → master n'est jamais mise à jour : dev est protégée, et master ne reçoit que dev.
	case "$head" in
	dev | master | main)
		return 0
		;;
	esac

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

	# Pas de mise à jour par le bot : le commit de merge doit être celui de l'auteur, Git est noté sur GitLab IUT.
	say "$number" "Ta branche a $behind commit(s) de retard sur \`$base\`. Mets-la à jour toi-même, pour que le commit de merge te soit attribué sur GitLab IUT (Git y est noté) :

\`\`\`bash
git fetch origin
git merge origin/$base
git push
\`\`\`

La fusion reste bloquée tant que la branche n'est pas à jour. Le bouton « Update branch » de GitHub marche aussi, mais il signe le commit avec l'email de ton compte GitHub, qui n'est pas forcément celui de GitLab IUT."
	echo "PR #$number : $behind commit(s) de retard sur $base, auteur prévenu."
}

if [ -n "${1:-}" ]; then
	update "$1"
	exit $?
fi

for number in $(open_pull_requests); do
	update "$number" || true
done
