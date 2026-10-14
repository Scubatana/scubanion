#!/bin/bash
# Regenere les PDF des livrables R5.01 a partir de leurs sources HTML.
#
# Usage, depuis n'importe ou :
#     bash docs/r501/outils/rendre-pdf.sh                  # les trois
#     bash docs/r501/outils/rendre-pdf.sh marketing-mix    # un seul
#
# Edge en mode headless applique la feuille de style d'impression et embarque
# les polices. Chrome fonctionne aussi : changer NAVIGATEUR ci-dessous.

set -u

R501="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

NAVIGATEUR="/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe"
[ -x "$NAVIGATEUR" ] || NAVIGATEUR="/c/Program Files/Google/Chrome/Application/chrome.exe"
[ -x "$NAVIGATEUR" ] || { echo "Ni Edge ni Chrome trouve. Corrige NAVIGATEUR."; exit 1; }

# Edge veut un chemin Windows (C:/...), pas un chemin Git Bash (/c/...)
win_path() { echo "$1" | sed 's|^/\([a-zA-Z]\)/|\1:/|'; }
R501_WIN="$(win_path "$R501")"

docs=${*:-"charte-de-projet marketing-mix experience-utilisateur"}

for d in $docs; do
  if [ ! -f "$R501/$d.html" ]; then
    echo "  absent : $d.html"
    continue
  fi

  "$NAVIGATEUR" --headless --disable-gpu --no-sandbox --no-pdf-header-footer \
    --print-to-pdf="$R501_WIN/$d.pdf" --virtual-time-budget=14000 \
    "file:///$R501_WIN/$d.html" >/dev/null 2>&1

  if [ -f "$R501/$d.pdf" ]; then
    taille=$(du -h "$R501/$d.pdf" | cut -f1)
    echo "  $d.pdf  ($taille)"
  else
    echo "  $d : echec du rendu"
  fi
done
