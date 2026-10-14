# -*- coding: utf-8 -*-
"""Toolbox Persona : donnees brutes des entretiens et calcul du prix optimal.

Ce fichier EST la trace des entretiens. Les reponses sont recopiees telles
qu'elles ont ete donnees, sans retouche ; les decisions de lecture (inclusion,
exclusion, interpretation d'une reponse ambigue) sont ecrites a cote.

Il regenere la partie 4 du livrable « experience utilisateur ».
Usage : python docs/r501/outils/toolbox-prix.py
"""
import io, pathlib

NIVEAUX = [0.49, 0.99, 1.49, 1.99, 2.49, 2.99, 3.99, 4.99, 6.99, 9.99]

# --------------------------------------------------------------------------
# Entretiens menes en message prive individuel, le 14/10/2026.
#
#   max_exclu  : « trop cher a partir de X » -> X et au-dessus sont refuses
#   max_inclus : « X c'est le maximum »      -> X est encore accepte
#   min_inclus : « louche en dessous de Y »  -> Y est encore accepte
# --------------------------------------------------------------------------
ENTRETIENS = [
    dict(nom="Radu", age=19, ville="Aurillac (15)", pas=None,
         pas_note="podomètre jamais activé sur son téléphone",
         budget=100, min_inclus=0.49, max_exclu=6.99, cible=True),

    dict(nom="Stacy", age=20, ville="Saint-Laurent-du-Var (06)", pas=4000,
         budget=30, min_inclus=0.99, max_exclu=2.99, cible=True),

    dict(nom="Damien L.", age=20, ville="Cabris (06)", pas=8000,
         budget=50, min_inclus=0.49, max_exclu=3.99, cible=True),

    dict(nom="Damien N.", age=19, ville="Nice (06)", pas=10500,
         budget=None, budget_note="variable, n'a pas su chiffrer",
         min_inclus=None,
         min_note="a contesté la question : pour un objet décoratif, un prix bas "
                  "ne fait pas douter de la qualité, puisqu'il ne change rien au jeu",
         max_inclus=3.99, cible=True),

    dict(nom="Nathan", age=19, ville="Nice (06)", pas=5000,
         budget=60, min_inclus=0.49, max_exclu=3.99, cible=True),

    # Hors cible : 16 ans, en dessous des 18-30 ans vises. Conserve a part,
    # pour ne pas ecarter une reponse au seul motif qu'elle derange.
    dict(nom="Damien B.", age=16, ville="Le Mans (72)", pas=6000,
         pas_note="podomètre désactivé, chiffre donné de mémoire",
         budget=5, budget_note="entre 0 et 10 €",
         min_inclus=0.99, max_exclu=2.99, cible=False),
]

# Ecarte : Diego, 17 ans. Reponses contradictoires (« trop cher a partir de 8 EUR »
# mais « louche en dessous de 5 EUR », puis « 5 EUR 8 EUR » sans trancher), ville
# non exploitable, et hors de la tranche d'age visee. Une relance n'a pas permis
# de lever la contradiction. A refaire proprement plutot qu'a interpreter.
ECARTES = [
    dict(nom="Diego", age=17,
         raison="reponses contradictoires sur les deux bornes de prix, et hors "
                "de la tranche d'age visee"),
]


def accepte(e, p):
    """Le niveau p est-il dans la fourchette acceptable de cette personne ?"""
    if e.get("min_inclus") is not None and p < e["min_inclus"]:
        return False
    if e.get("max_exclu") is not None and p >= e["max_exclu"]:
        return False
    if e.get("max_inclus") is not None and p > e["max_inclus"]:
        return False
    return True


CIBLE = [e for e in ENTRETIENS if e["cible"]]
COUV = [sum(accepte(e, p) for e in CIBLE) for p in NIVEAUX]
PLEIN = [p for p, c in zip(NIVEAUX, COUV) if c == len(CIBLE)]


def eur(p):
    return ("%.2f" % p).replace(".", ",") + " €"


# ============================ Graphiques SVG ==============================

def svg_couverture():
    """Barres verticales : combien de personnes acceptent chaque niveau."""
    W, H = 760, 250
    ml, mr, mt, mb = 38, 12, 20, 52
    gw = W - ml - mr
    gh = H - mt - mb
    bw = gw / len(NIVEAUX)
    n = len(CIBLE)

    out = ['<svg viewBox="0 0 %d %d" role="img" aria-label="Couverture par niveau de prix">' % (W, H)]

    # bande de chevauchement, en fond
    i0 = NIVEAUX.index(PLEIN[0]); i1 = NIVEAUX.index(PLEIN[-1])
    out.append('<rect x="%.1f" y="%d" width="%.1f" height="%d" fill="#E0F5EA"/>'
               % (ml + i0 * bw, mt, (i1 - i0 + 1) * bw, gh))

    # lignes horizontales et graduations
    for k in range(n + 1):
        y = mt + gh - gh * k / n
        out.append('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#DCEBEE" stroke-width="1"/>'
                   % (ml, y, W - mr, y))
        out.append('<text x="%d" y="%.1f" font-size="11" fill="#6b6b76" text-anchor="end">%d</text>'
                   % (ml - 6, y + 4, k))

    for i, (p, c) in enumerate(zip(NIVEAUX, COUV)):
        x = ml + i * bw + bw * .18
        w = bw * .64
        h = gh * c / n
        plein = c == n
        out.append('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3" fill="%s"/>'
                   % (x, mt + gh - h, w, h, "#34C77B" if plein else "#8FB9C2"))
        if c:
            out.append('<text x="%.1f" y="%.1f" font-size="11" font-weight="700" fill="%s" text-anchor="middle">%d</text>'
                       % (x + w / 2, mt + gh - h - 5, "#1C6B3F" if plein else "#4C6B73", c))
        out.append('<text x="%.1f" y="%d" font-size="10.5" fill="%s" text-anchor="middle" font-weight="%s">%s</text>'
                   % (x + w / 2, mt + gh + 16, "#1E3A42" if plein else "#6b6b76",
                      "700" if plein else "400", eur(p)))

    out.append('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#4C6B73" stroke-width="1.5"/>'
               % (ml, mt + gh, W - mr, mt + gh))
    out.append('<text x="%.1f" y="%d" font-size="11.5" font-weight="700" fill="#1C6B3F" text-anchor="middle">'
               'zone de chevauchement : %s à %s</text>'
               % (ml + (i0 + i1 + 1) / 2 * bw, mt + gh + 38, eur(PLEIN[0]), eur(PLEIN[-1])))
    out.append('<text x="4" y="12" font-size="10.5" fill="#6b6b76">personnes sur %d</text>' % n)
    out.append('</svg>')
    return "\n".join(out)


def svg_fourchettes():
    """Une barre horizontale par personne : sa fourchette acceptable."""
    lignes = CIBLE + [e for e in ENTRETIENS if not e["cible"]]
    W = 760
    ml, mr, mt = 92, 12, 22
    rh, gap = 24, 7
    H = mt + len(lignes) * (rh + gap) + 34
    gw = W - ml - mr
    bw = gw / len(NIVEAUX)

    out = ['<svg viewBox="0 0 %d %d" role="img" aria-label="Fourchette acceptable de chaque personne">' % (W, H)]

    i0 = NIVEAUX.index(PLEIN[0]); i1 = NIVEAUX.index(PLEIN[-1])
    out.append('<rect x="%.1f" y="%d" width="%.1f" height="%d" fill="#E0F5EA"/>'
               % (ml + i0 * bw, mt - 6, (i1 - i0 + 1) * bw, len(lignes) * (rh + gap) + 6))

    for i, p in enumerate(NIVEAUX):
        x = ml + i * bw
        out.append('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#DCEBEE" stroke-width="1"/>'
                   % (x, mt - 6, x, mt + len(lignes) * (rh + gap)))
        out.append('<text x="%.1f" y="%d" font-size="10" fill="#6b6b76" text-anchor="middle">%s</text>'
                   % (x + bw / 2, mt + len(lignes) * (rh + gap) + 14, eur(p)))

    for r, e in enumerate(lignes):
        y = mt + r * (rh + gap)
        idx = [i for i, p in enumerate(NIVEAUX) if accepte(e, p)]
        hors = not e["cible"]
        out.append('<text x="%d" y="%.1f" font-size="11.5" fill="%s" text-anchor="end" font-weight="%s">%s</text>'
                   % (ml - 8, y + rh * .68, "#8FB9C2" if hors else "#1E3A42",
                      "400" if hors else "600", e["nom"]))
        if idx:
            x = ml + idx[0] * bw
            w = (idx[-1] - idx[0] + 1) * bw
            out.append('<rect x="%.1f" y="%.1f" width="%.1f" height="%d" rx="5" fill="%s" opacity="%s"/>'
                       % (x + 2, y, w - 4, rh - 4, "#8FB9C2" if hors else "#2F6B7A", ".55" if hors else "1"))
        if e.get("min_inclus") is None:
            out.append('<text x="%.1f" y="%.1f" font-size="9" fill="#CFE3E8" font-style="italic">borne basse non exprimée</text>'
                       % (ml + 6, y + rh * .66))
    out.append('</svg>')
    return "\n".join(out)


# ============================ Tableaux HTML ===============================

def tableau_profils():
    """Tableau des reponses. Les precisions partent en remarques sous le tableau,
    sinon elles font exploser la hauteur des lignes."""
    l = ['<table class="tight">',
         '<thead><tr><th style="width:22mm;">Personne</th><th class="num" style="width:11mm;">Âge</th>'
         '<th style="width:34mm;">Ville</th><th class="num" style="width:20mm;">Pas/jour</th>'
         '<th class="num" style="width:22mm;">Budget loisirs</th>'
         '<th class="num" style="width:22mm;">Doute en dessous de</th>'
         '<th class="num" style="width:22mm;">Trop cher à partir de</th></tr></thead><tbody>']
    notes = []
    for e in ENTRETIENS:
        marque = ""
        n = [x for x in (e.get("pas_note"), e.get("budget_note"), e.get("min_note")) if x]
        if n:
            marque = '<sup>%d</sup>' % (len(notes) + 1)
            notes.append((len(notes) + 1, e["nom"], " ; ".join(n)))
        hors = "" if e["cible"] else ' <span class="tag" style="background:#FDE7DC;color:#D96A41;">hors cible</span>'
        pas = "{:,}".format(e["pas"]).replace(",", " ") if e["pas"] else "—"
        bud = ("%d €" % e["budget"]) if e["budget"] is not None else "—"
        mini = eur(e["min_inclus"]) if e.get("min_inclus") is not None else '<em>non exprimé</em>'
        if e.get("max_exclu") is not None:
            maxi = eur(e["max_exclu"])
        else:
            maxi = "%s <span class=\"small muted\">(inclus)</span>" % eur(e["max_inclus"])
        l.append('<tr><td class="k">%s%s%s</td><td class="num">%d</td><td>%s</td>'
                 '<td class="num">%s</td><td class="num">%s</td><td class="num">%s</td><td class="num">%s</td></tr>'
                 % (e["nom"], marque, hors, e["age"], e["ville"], pas, bud, mini, maxi))
    l.append('</tbody></table>')
    if notes:
        l.append('<p class="small muted" style="margin-top:1.5mm;">')
        l.append(" &middot; ".join('<sup>%d</sup>&nbsp;<b>%s</b> : %s' % n for n in notes))
        l.append('</p>')
    return '\n'.join(l)


def tableau_grille():
    n = len(CIBLE)
    l = ['<table class="price">', '<thead><tr><th style="width:26mm;">Personne</th>']
    l += ['<th>%s</th>' % eur(p) for p in NIVEAUX]
    l.append('</tr></thead><tbody>')
    for e in CIBLE:
        l.append('<tr><th>%s</th>' % e["nom"])
        for p in NIVEAUX:
            if accepte(e, p):
                cls = "sweet" if p in PLEIN else "in"
                l.append('<td class="%s"></td>' % cls)
            elif e.get("min_inclus") is not None and p < e["min_inclus"]:
                l.append('<td class="under">doute</td>')
            else:
                l.append('<td class="over">trop</td>')
        l.append('</tr>')
    l.append('<tr><th style="background:var(--ocean-dark);color:#fff;">Personnes couvertes</th>')
    for p, c in zip(NIVEAUX, COUV):
        cls = ' class="sweet"' if c == n else ''
        l.append('<td%s>%d / %d</td>' % (cls, c, n))
    l.append('</tr></tbody></table>')
    return "\n".join(l)


# ============================== Injection =================================

FRAGMENT = """<!-- ===================== TOOLBOX : RESULTATS ===================== -->
<section class="page">
  <h2><span class="n">4</span>Résultats des entretiens</h2>

  <p class="lead" style="margin-bottom:4mm;">
    Sept personnes ont été interrogées en message privé individuel, le 14/10/2026.
    <strong>Cinq réponses sont exploitables et dans la cible</strong> — c'est le minimum
    demandé par le cours. Une sixième, hors tranche d'âge, est conservée à part ; une
    septième a été écartée. Les réponses brutes et les règles de lecture sont dans
    <code>docs/r501/outils/toolbox-prix.py</code>.
  </p>

  <h3 style="margin-top:0;">Les réponses</h3>
  __PROFILS__

  <div class="card card-accent" style="margin-top:3mm;">
    <h4 style="margin-top:0;">Trois décisions de lecture, assumées</h4>
    <ul class="small" style="margin-bottom:0;">
      <li><strong>Diego, 17 ans, est écarté.</strong> Il situait le « trop cher » à 8 € et
        le « doute sur la qualité » à 5 €, puis a répondu « 5 € 8 € » sans trancher
        lorsqu'on lui a demandé de préciser. Une fourchette inversée n'est pas
        exploitable, et il est hors de la tranche d'âge visée. Mieux vaut un entretien
        refait qu'une réponse interprétée.</li>
      <li><strong>Damien B., 16 ans, est conservé mais hors cible.</strong> Il est en
        dessous des 18-30 ans visés, donc exclu du calcul. Nous l'affichons quand même :
        écarter une réponse parce qu'elle dérange serait une faute, et la sienne
        <em>confirme</em> la fourchette trouvée.</li>
      <li><strong>Damien N. n'a pas de borne basse.</strong> Il a contesté la deuxième
        question, et son objection est juste : pour un objet purement décoratif, qui ne
        change rien au jeu, un prix bas ne fait pas douter de la qualité. Nous le comptons
        donc comme acceptant tous les niveaux sous son maximum. Cela n'affecte que la
        colonne 0,49 €, qui n'est pas couverte à 100 % de toute façon.</li>
    </ul>
  </div>

  <h3>La grille</h3>
  __GRILLE__

  <div class="legend" style="margin-bottom:4mm;">
    <span><i style="background:#D8EFE2;"></i> Dans la fourchette acceptable</span>
    <span><i style="background:var(--green);"></i> Zone de chevauchement</span>
    <span><i style="background:#FDF3D7;"></i> Trop bas : doute sur la qualité</span>
    <span><i style="background:#F7CFC7;"></i> Trop cher : pas d'achat</span>
  </div>
</section>

<!-- ===================== TOOLBOX : LECTURE ===================== -->
<section class="page">
  <h2><span class="n">5</span>Lecture et prix retenu</h2>

  <h3 style="margin-top:0;">Combien de personnes chaque prix convainc-t-il ?</h3>
  <figure style="margin-bottom:4mm;">
    __SVG_COUVERTURE__
    <figcaption>Nombre de personnes, sur les cinq de la cible, pour qui le prix est
      acceptable. <b>Quatre niveaux font l'unanimité</b> : de 0,99 € à 2,49 €.</figcaption>
  </figure>

  <h3>La fourchette de chacun</h3>
  <figure style="margin-bottom:4mm;">
    __SVG_FOURCHETTES__
    <figcaption>Chaque barre est l'intervalle que la personne juge acceptable. La bande
      verte est leur intersection. Damien B., hors cible, est en clair : sa fourchette
      tombe entièrement dans la zone commune.</figcaption>
  </figure>

  <div class="grid g-2-1" style="margin-bottom:3mm;">
    <div class="card card-ocean" style="margin-bottom:0;">
      <h4 style="margin-top:0;">Ce que les entretiens disent</h4>
      <ul class="small" style="margin-bottom:0;">
        <li><strong>La zone de chevauchement va de 0,99 € à 2,49 €</strong> : quatre
          niveaux conviennent aux cinq personnes de la cible.</li>
        <li><strong>Au-delà de 2,99 €, ça décroche vite</strong> : 4 personnes sur 5 à
          2,99 €, plus que 2 sur 5 à 3,99 €, et une seule à 4,99 €.</li>
        <li><strong>Personne n'accepte 6,99 € ni 9,99 €.</strong> Le haut de l'échelle est
          mort : il n'y a pas de marché pour un skin au-delà de 5 € sur cette cible.</li>
        <li><strong>Le bas de l'échelle inquiète peu.</strong> Trois personnes sur cinq
          acceptent 0,49 €. Le mécanisme de doute sur la qualité, central dans la méthode,
          joue faiblement sur un objet décoratif.</li>
      </ul>
    </div>
    <div class="card on-white" style="margin-bottom:0;text-align:center;">
      <p class="small muted" style="margin-bottom:1mm;">Prix retenu</p>
      <p style="font-family:'Baloo 2',sans-serif;font-size:30pt;font-weight:800;color:var(--ocean);line-height:1;margin:0;">1,99 €</p>
      <p class="small" style="margin-top:2mm;margin-bottom:0;">Zone de chevauchement<br><strong>0,99 € — 2,49 €</strong></p>
    </div>
  </div>

  <div class="card card-accent">
    <h4 style="margin-top:0;">Pourquoi 1,99 € et pas 2,49 €</h4>
    <p class="small" style="margin-bottom:0;">
      2,49 € est le haut de la zone d'unanimité : ce serait le prix qui rapporte le plus
      en conservant 100 % de couverture. Nous retenons pourtant <strong>1,99 €</strong>,
      pour deux raisons. D'abord, 2,49 € est <em>exactement</em> le plafond de Stacy : au
      moindre optimisme dans sa réponse, nous la perdons, et la couverture n'est plus
      réelle. Ensuite 1,99 € laisse un cran de marge des deux côtés, ce qui autorise une
      remise ponctuelle à 1,49 € et une gamme rare à 2,49 € sans jamais sortir de la zone.
      Un prix qui tient sous la pression vaut mieux qu'un prix optimal en théorie.
    </p>
  </div>

  <h3>Trois enseignements inattendus</h3>
  <table class="tight">
    <tbody>
      <tr>
        <td class="k" style="width:42mm;">Le podomètre n'est pas activé</td>
        <td><strong>Deux personnes sur sept n'ont pas su donner leur nombre de pas</strong>,
          parce que le capteur n'est pas actif sur leur téléphone. C'est un signal produit
          sérieux : notre promesse repose entièrement sur ce capteur. L'activation doit
          être une étape explicite et guidée de la mise en route, pas une permission
          demandée en passant.</td>
      </tr>
      <tr>
        <td class="k">La question du prix bas s'applique mal</td>
        <td>L'objection de Damien N. est fondée. La deuxième question du cours suppose que
          le prix signale la qualité ; pour un objet décoratif sans effet sur le jeu, ce
          lien est faible. Trois personnes sur cinq acceptent d'ailleurs le niveau le plus
          bas. <strong>Nous gardons la méthode telle que le cours la définit</strong>, mais
          nous notons que sa borne basse est peu informative dans ce cas précis.</td>
      </tr>
      <tr>
        <td class="k">Le persona Léa est confirmé, les deux autres non</td>
        <td>L'échantillon valide <strong>Léa</strong> : 19-20 ans, 30 à 60 € de budget
          loisirs, 4 000 à 8 000 pas par jour. En revanche <strong>Yanis (27 ans) et
          Camille (34 ans) ne sont représentés par personne</strong> : toutes les réponses
          viennent de 16-20 ans. Ces deux personas restent des hypothèses.</td>
      </tr>
    </tbody>
  </table>

  <div class="card card-accent">
    <h4 style="margin-top:0;">Les limites, et la suite</h4>
    <ul class="small" style="margin-bottom:0;">
      <li><strong>Cinq réponses, c'est le minimum du cours, pas un échantillon
        statistique.</strong> On obtient un ordre de grandeur et un point de départ, pas
        une certitude.</li>
      <li><strong>L'échantillon n'est pas sur le marché pilote.</strong> Les réponses
        viennent des Alpes-Maritimes, du Cantal et de la Sarthe, alors que le Business
        Model Canvas vise Paris intra-muros. Le prix d'un cosmétique varie peu d'une ville
        à l'autre, mais <strong>le pouvoir d'achat parisien est plus élevé</strong> : notre
        fourchette est donc probablement prudente. À retester sur le territoire visé, ou à
        rapprocher le territoire de notre terrain réel.</li>
      <li><strong>La méthode mesure une intention déclarée.</strong> Entre dire « j'achète
        à 1,99 € » et sortir sa carte, il y a un écart que seul un vrai lancement mesure.</li>
      <li><strong>Prochaine étape</strong> : reposer les deux questions aux 20 testeurs du
        campus (action <strong>A6</strong> de la charte, échéance 29/11), cette fois après
        qu'ils auront utilisé l'application une semaine. Un joueur attaché à son compagnon
        n'a pas la même disposition à payer qu'un inconnu à qui on montre une capture.</li>
    </ul>
  </div>

  <div class="foot">
    <span>Scubanion &middot; équipe Scubatana &middot; expérience utilisateur v2.0</span>
    <span>R5.01 &middot; 14 octobre 2026</span>
  </div>
</section>
"""


def main():
    ici = pathlib.Path(__file__).resolve().parent
    cible = ici.parent / "experience-utilisateur.html"
    s = io.open(cible, encoding="utf-8").read()

    frag = (FRAGMENT
            .replace("__PROFILS__", tableau_profils())
            .replace("__GRILLE__", tableau_grille())
            .replace("__SVG_COUVERTURE__", svg_couverture())
            .replace("__SVG_FOURCHETTES__", svg_fourchettes()))

    debut = s.index("<!-- ===================== TOOLBOX : ")
    fin = s.rindex("</section>") + len("</section>")
    io.open(cible, "w", encoding="utf-8", newline="\n").write(s[:debut] + frag.rstrip() + s[fin:])

    print("Niveaux    :", " ".join(eur(p) for p in NIVEAUX))
    print("Couverture :", " ".join("%d/%d" % (c, len(CIBLE)) for c in COUV))
    print("Chevauchement : %s a %s" % (eur(PLEIN[0]), eur(PLEIN[-1])))
    print("Entretiens : %d dans la cible, %d hors cible, %d ecarte(s)"
          % (len(CIBLE), len(ENTRETIENS) - len(CIBLE), len(ECARTES)))
    print("-> experience-utilisateur.html mis a jour")


if __name__ == "__main__":
    main()
