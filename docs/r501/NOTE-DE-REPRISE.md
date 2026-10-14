# Note de reprise — livrables R5.01

> **Note de travail interne, pas un livrable.** À supprimer avant le rendu final du 12/12.
> Elle sert à reprendre le travail sur une autre machine, ou après une coupure.

- **Branche** : `feature/r501-charte-marketing-ux`
- **Pull request** : [#24](https://github.com/Picsou06/scubanion/pull/24)
- **Dernière mise à jour** : 14/10/2026

---

## 1. Où on en est

Les trois livrables R5.01 sont **écrits, rendus en PDF et prêts à envoyer**. Le ZIP
complet a été généré (9,7 Mo) et déposé dans le Drive.

Il reste **une seule chose** avant de pouvoir envoyer une version pleinement aboutie :
les **interviews de prix** de la méthode Toolbox Persona. Le document contient
aujourd'hui une grille simulée, **annoncée explicitement comme une simulation**. Elle est
défendable telle quelle, mais la remplacer par de vraies réponses fait passer l'exercice
de « on maîtrise l'outil » à « on l'a fait ».

| Livrable | Fichier | État |
| -------- | ------- | ---- |
| Charte de projet | `charte-de-projet.pdf` (14 p.) | Terminé |
| Marketing mix | `marketing-mix.pdf` (14 p.) | Terminé, sauf le prix issu de la toolbox |
| Expérience utilisateur | `experience-utilisateur.pdf` (8 p.) | Terminé, sauf la grille de prix |
| Films de borne | `videos/*.mp4` | Terminé |

---

## 2. La seule tâche qui reste

### Collecter les réponses

Un message type a été rédigé pour être envoyé **en message privé individuel** à des
étudiants. Il demande six informations :

1. le prix à partir duquel c'est **trop cher** ;
2. le prix en dessous duquel on **doute de la qualité** ;
3. l'âge ; 4. la ville ; 5. le nombre de pas par jour ; 6. le budget loisirs mensuel.

**Viser 8 à 10 réponses** et non 5 : le cours demande 5 au minimum, mais il y a toujours
du déchet, et une zone de chevauchement se lit beaucoup mieux avec 8 réponses.

L'envoi individuel en message privé est important : en public, les premières réponses
influencent les suivantes et faussent la zone de chevauchement.

### Ce qu'il faudra modifier une fois les réponses obtenues

| Fichier | Quoi |
| ------- | ---- |
| `experience-utilisateur.html` | Remplacer le bloc « Avertissement — ceci est une simulation » et le tableau des cinq profils par les vraies réponses. Recalculer la ligne « Personnes couvertes » et la zone verte. |
| `marketing-mix.html` | Mettre à jour la section « Ce qui reste à valider » et le prix de référence du skin, aujourd'hui à 1,99 € d'après la simulation. |
| `cahier-des-charges.md` | Rien, sauf si le résultat change la grille tarifaire. |

Puis régénérer les PDF (voir § 4) et reconstruire le ZIP.

**Échelle de prix utilisée** (ne pas la changer, les réponses s'y réfèrent) :
`0,49 — 0,99 — 1,49 — 1,99 — 2,49 — 2,99 — 3,99 — 4,99 — 6,99 — 9,99 €`

---

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
