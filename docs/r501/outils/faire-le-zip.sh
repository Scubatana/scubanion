#!/bin/bash
# Fabrique le ZIP a envoyer a l'enseignante, a partir des PDF du depot.
#
# Usage, depuis n'importe ou :
#     bash docs/r501/outils/faire-le-zip.sh            # -> ~/Downloads
#     bash docs/r501/outils/faire-le-zip.sh /un/dossier
#
# Il ne regenere RIEN : il assemble ce qui existe. Pensez a lancer
# rendre-pdf.sh avant, si vous avez modifie un document.

set -u

R501="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${1:-$HOME/Downloads}"
NOM="Scubanion-R501-Scubatana"

# Les PDF du depot, et le nom qu'ils prennent dans le dossier envoye.
# L'ordre des numeros est celui dans lequel on veut qu'elle les ouvre.
FICHIERS=(
  "soutenance.pdf|1-Presentation-du-projet.pdf"
  "charte-de-projet.pdf|2-Charte-de-projet.pdf"
  "marketing-mix.pdf|3-Marketing-mix.pdf"
  "experience-utilisateur.pdf|4-Experience-utilisateur.pdf"
  "cahier-des-charges.pdf|5-Cahier-des-charges.pdf"
)

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/$NOM/videos"

manque=0
for paire in "${FICHIERS[@]}"; do
  src="${paire%%|*}"
  dst="${paire##*|}"
  if [ -f "$R501/$src" ]; then
    cp "$R501/$src" "$TMP/$NOM/$dst"
    printf "  %-34s %s\n" "$dst" "$(du -h "$R501/$src" | cut -f1)"
  else
    echo "  MANQUE : $src  (lancez rendre-pdf.sh)"
    manque=1
  fi
done

cp "$R501/LISEZ-MOI.txt" "$TMP/$NOM/" 2>/dev/null || { echo "  MANQUE : LISEZ-MOI.txt"; manque=1; }

if ls "$R501/videos/"*.mp4 >/dev/null 2>&1; then
  cp "$R501/videos/"*.mp4 "$TMP/$NOM/videos/"
  echo "  videos/                            $(ls "$R501/videos/"*.mp4 | wc -l) fichier(s)"
else
  echo "  MANQUE : les videos"
  manque=1
fi

[ "$manque" -eq 1 ] && echo "" && echo "  /!\\ Des fichiers manquent : le ZIP sera incomplet."

mkdir -p "$DEST"
ZIP="$DEST/$NOM-$(date +%Y%m%d).zip"
rm -f "$ZIP"

( cd "$TMP" && python -c "
import shutil, sys
shutil.make_archive(sys.argv[1][:-4], 'zip', '.', sys.argv[2])
" "$ZIP" "$NOM" )

if [ -f "$ZIP" ]; then
  echo ""
  echo "  -> $ZIP  ($(du -h "$ZIP" | cut -f1))"
else
  echo "  Echec de la creation du ZIP."
  exit 1
fi
