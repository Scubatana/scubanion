# Note de reprise — livrables R5.01

> **Note de travail interne, pas un livrable.** À supprimer avant le rendu final du 12/12.
> Elle sert à reprendre le travail sur une autre machine, ou après une coupure.

- **Branche** : `feature/r501-charte-marketing-ux`
- **Pull request** : [#24](https://github.com/Picsou06/scubanion/pull/24)
- **Dernière mise à jour** : 14/10/2026

---

## 1. Où on en est

**Les trois livrables sont terminés**, entretiens compris. Le ZIP a été généré et
déposé sur le OneDrive universitaire. Il n'y a plus de tâche bloquante avant l'envoi.

| Livrable | Fichier | État |
| -------- | ------- | ---- |
| Charte de projet | `charte-de-projet.pdf` (14 p.) | Terminé |
| Marketing mix | `marketing-mix.pdf` (14 p.) | Terminé, prix validé par les entretiens |
| Expérience utilisateur | `experience-utilisateur.pdf` (7 p.) | Terminé, avec les résultats réels |
| Films de borne | `videos/*.mp4` | Terminé |

### Les entretiens Toolbox Persona sont faits

Sept personnes interrogées en message privé le 14/10/2026, cinq réponses
exploitables dans la cible. **Zone de chevauchement : 0,99 € à 2,49 €. Prix retenu :
1,99 € le skin.**

Les réponses brutes et les règles de lecture sont dans
`outils/toolbox-prix.py` — c'est ce fichier qui régénère la partie résultats du
livrable 3. Pour ajouter des entretiens plus tard, compléter la liste `ENTRETIENS`
puis relancer :

```bash
python docs/r501/outils/toolbox-prix.py
bash docs/r501/outils/rendre-pdf.sh experience-utilisateur
```

Tout se recalcule : la grille, les deux graphiques et les compteurs.

## 2. Ce qui reste, et qui n'est pas bloquant

- **Reposer les deux questions aux 20 testeurs** après une semaine d'usage
  (action **A6**, échéance 29/11). Un joueur attaché à son compagnon n'a pas la
  même disposition à payer qu'un inconnu.
- **Les personas Yanis (27 ans) et Camille (34 ans) ne sont validés par personne** :
  tous les répondants ont entre 16 et 20 ans. Seule Léa est confirmée.
- **L'échantillon n'est pas sur le marché pilote** : réponses des Alpes-Maritimes,
  du Cantal et de la Sarthe, alors que le Business Model Canvas vise Paris. À
  trancher : retester à Paris, ou rapprocher le territoire pilote du terrain réel.
- **Diego, 17 ans, est à réinterroger** si on veut récupérer sa réponse : sa
  fourchette était inversée (trop cher à 8 €, doute en dessous de 5 €).

## 3. Où sont les choses

```
docs/r501/
├── charte-de-projet.html / .pdf        livrable 1
├── marketing-mix.html / .pdf           livrable 2
├── experience-utilisateur.html / .pdf  livrable 3
├── assets/
│   ├── style.css                       charte graphique commune
│   └── images/                         maquettes (.png) et images des films (.jpg)
├── animations/                         sources HTML des deux films (GSAP)
├── videos/                             les deux MP4 rendus
├── outils/
│   ├── rendre-pdf.sh                   HTML -> PDF
│   └── capturer-videos.py              animations -> MP4
├── LISEZ-MOI.txt                       fichier joint au ZIP, pour l'enseignante
└── NOTE-DE-REPRISE.md                  ce fichier
```

Hors dépôt : le ZIP `Scubanion-R501-Scubatana.zip` et `mail-a-envoyer.txt` sont dans le
dossier parent du dépôt et dans le Drive.

---

## 4. Comment régénérer

### Les PDF

```bash
bash docs/r501/outils/rendre-pdf.sh                 # les trois
bash docs/r501/outils/rendre-pdf.sh marketing-mix   # un seul
```

Edge en mode headless applique la feuille de style d'impression. Aucune dépendance à
installer. Les polices Baloo 2 et Inter viennent de Google Fonts : **il faut une connexion
au moment du rendu**, sinon les PDF sortent avec des polices de repli.

### Les vidéos

```bash
pip install playwright
python -m playwright install chromium
python docs/r501/outils/capturer-videos.py
```

ffmpeg doit être dans le `PATH`. Compter deux à trois minutes par film : 457 images
capturées une par une, puis encodage.

### Le ZIP à envoyer

Rassembler dans un dossier `Scubanion-R501-Scubatana/` :
les trois PDF renommés `1-Charte-de-projet.pdf`, `2-Marketing-mix.pdf`,
`3-Experience-utilisateur.pdf`, le dossier `videos/` avec les deux MP4, et
`LISEZ-MOI.txt`. Puis compresser. Environ 9,7 Mo, ça passe en pièce jointe.

---

## 5. Pièges déjà rencontrés, à ne pas redécouvrir

- **Couverture pleine page** : les marges négatives ne débordent pas la boîte de page en
  impression. Il faut une page nommée — `@page cover{margin:0}` et `.page.cover{page:cover}`.
- **Colonnes CSS** : `column-count` avec une hauteur contrainte envoie le contenu
  excédentaire dans une colonne hors cadre, **sans aucun avertissement**. Les blocs de
  bas de canvas utilisent une grille `2 × 2`, pas du multi-colonnes.
- **Images** : les illustrations tirées des films sont en `.jpg` (JPEG 86 %), les captures
  d'interface en `.png`. Converti depuis des PNG de 7,7 Mo à 3,1 Mo, ce qui a divisé par
  deux le poids des PDF. Si des images sont régénérées, **penser à mettre à jour les
  extensions dans le HTML**.
- **Accents dans les noms de fichiers** : rechercher les sources par mot-clé, pas par nom
  exact. « Musée » ne se compare pas de façon fiable entre Git Bash et Python sous Windows.
- **Backticks dans un `python -c "..."`** sous Git Bash : ils sont interprétés par le
  shell. Passer par un fichier `.py` ou un heredoc `<<'EOF'` pour tout texte contenant des
  backticks.

---

## 6. Décisions en attente, côté projet

Elles ne bloquent pas les livrables R5.01, mais elles figurent dans la charte et dans le
plan d'action.

- **`D2` et `D3`** (identification à la borne, microcontrôleur) : **échéance 17/10**, et il
  faut commander le matériel le jour même. C'est le chemin critique — la borne porte
  6 exigences *Must* sur 18 et n'est pas commencée. Action **A1**, responsable Shaleena.
- **K4, l'équilibre des contributions Git**, est hors cible : 47, 13, 1 et 0 commits selon
  les membres. Git étant noté individuellement, c'est le risque **R1** de la charte,
  classé IV.
- **Objectif O3** : 2 lettres d'intention et 20 testeurs avant le 29/11. Rien n'est
  commencé. Action **A6**.

---

## 7. Si on reprend avec Claude Code

Lui donner comme contexte : cette note, puis `docs/r501/charte-de-projet.html` et
`docs/r501/experience-utilisateur.html`. Préciser que la structure de la charte vient du
cours (parties 4 et 5 du PDF « SEANCE 3 et 4 »), que **la charte de projet est la partie 4
du cours et non le plan d'action**, et que c'est précisément l'erreur de la première
version.

Les deux PDF de cours sont dans le dossier Téléchargements de la machine personnelle.
Pages utiles : **3 à 8** pour la charte, **9 à 12** pour les risques, **21 à 33** pour le
plan d'action et les indicateurs, **139 à 150** pour le marketing mix, **145** pour la
Toolbox Persona.
