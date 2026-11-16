#!/usr/bin/env bash
# Met en place le flux develop → master sur GitHub. À lancer une seule fois, par un administrateur du dépôt,
# APRÈS la fusion dans master de la PR qui ajoute ce script (sinon la garde des PR fermerait les PR vers develop).
#
#   bash .github/scripts/configurer-flux-develop.sh
#
# Le script peut être relancé sans risque : chaque étape vérifie ce qui existe déjà.
set -euo pipefail

REPO="${GH_REPO:-Scubatana/scubanion}"
CHECKS_APP=15368 # GitHub Actions, qui publie les checks « Tests API » et « Tests app »

step() {
	printf '\n== %s\n' "$1"
}

step "Branche develop, créée depuis master"
if gh api "repos/$REPO/branches/develop" > /dev/null 2>&1; then
	echo "develop existe déjà."
else
	sha=$(gh api "repos/$REPO/git/ref/heads/master" --jq .object.sha)
	gh api -X POST "repos/$REPO/git/refs" -f ref=refs/heads/develop -f sha="$sha" > /dev/null
	echo "develop créée sur $sha."
fi

main_id=$(gh api "repos/$REPO/rulesets" --jq '.[] | select(.name == "Branches principales") | .id')

if [ -z "$main_id" ]; then
	echo "Ruleset « Branches principales » introuvable : rien n'est modifié." >&2
	exit 1
fi

step "Ruleset « Branche d'intégration » sur develop"
if [ -n "$(gh api "repos/$REPO/rulesets" --jq '.[] | select(.name == "Branche d'"'"'intégration") | .id')" ]; then
	echo "Il existe déjà."
else
	# Mêmes règles que master : PR obligatoire, 1 approbation d'un autre membre sur le dernier push,
	# fils de revue résolus, commit de merge seulement, Tests API et Tests app verts sur une branche à jour.
	gh api "repos/$REPO/rulesets/$main_id" --jq "{
		name: \"Branche d'intégration\",
		target: \"branch\",
		enforcement: \"active\",
		bypass_actors: .bypass_actors,
		conditions: { ref_name: { include: [\"refs/heads/develop\"], exclude: [] } },
		rules: [
			{ type: \"deletion\" },
			{ type: \"non_fast_forward\" },
			{ type: \"pull_request\", parameters: {
				allowed_merge_methods: [\"merge\"],
				dismiss_stale_reviews_on_push: false,
				require_code_owner_review: false,
				require_last_push_approval: true,
				required_approving_review_count: 1,
				required_review_thread_resolution: true
			} },
			{ type: \"required_status_checks\", parameters: {
				do_not_enforce_on_create: false,
				strict_required_status_checks_policy: true,
				required_status_checks: [
					{ context: \"Tests API\", integration_id: $CHECKS_APP },
					{ context: \"Tests app\", integration_id: $CHECKS_APP }
				]
			} }
		]
	}" | gh api -X POST "repos/$REPO/rulesets" --input - > /dev/null
	echo "Créé."
fi

step "master : plus d'obligation d'être à jour avant la fusion"
# master ne reçoit que develop : après une release, master a un commit de merge que develop n'a pas.
# Exiger une branche à jour forcerait à pousser sur develop, qui est protégée. Les tests tournent sur develop,
# dont l'arbre est exactement celui que la fusion produit.
gh api "repos/$REPO/rulesets/$main_id" --jq '{
	name, target, enforcement, bypass_actors, conditions,
	rules: [.rules[] | if .type == "required_status_checks" then .parameters.strict_required_status_checks_policy = false else . end]
}' | gh api -X PUT "repos/$REPO/rulesets/$main_id" --input - > /dev/null
echo "Fait."

step "develop par défaut, commits de merge seulement, branches supprimées après fusion"
gh api -X PATCH "repos/$REPO" \
	-f default_branch=develop \
	-F allow_merge_commit=true \
	-F allow_squash_merge=false \
	-F allow_rebase_merge=false \
	-F delete_branch_on_merge=true > /dev/null
echo "Fait."

step "Signalement privé des failles (bouton « Report a vulnerability » de SECURITY.md)"
gh api -X PUT "repos/$REPO/private-vulnerability-reporting" > /dev/null
echo "Fait."

step "PR ouvertes vers master redirigées vers develop"
for number in $(gh pr list --repo "$REPO" --state open --base master --limit 100 --json number,headRefName --jq '.[] | select(.headRefName != "develop") | .number'); do
	gh pr edit "$number" --repo "$REPO" --base develop > /dev/null
	echo "#$number → develop"
done

printf '\nFlux develop → master en place. Chacun : git fetch origin && git switch develop\n'
