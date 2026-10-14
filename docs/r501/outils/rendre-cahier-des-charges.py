# -*- coding: utf-8 -*-
"""Rend docs/cahier-des-charges.md en PDF, dans la charte graphique du projet.

Le cahier des charges vit en Markdown parce que c'est un document de travail
qu'on modifie a chaque PR. Ce script en produit la version lisible a envoyer.

Prerequis : pip install markdown
Usage     : python docs/r501/outils/rendre-cahier-des-charges.py
"""
import io, pathlib, re, subprocess, sys

try:
    import markdown
except ImportError:
    print("Il manque la bibliotheque : pip install markdown"); sys.exit(1)

ICI = pathlib.Path(__file__).resolve().parent
R501 = ICI.parent
DOCS = R501.parent
SRC = DOCS / "cahier-des-charges.md"
OUT_HTML = R501 / "cahier-des-charges.html"
OUT_PDF = R501 / "cahier-des-charges.pdf"

texte = io.open(SRC, encoding="utf-8").read()

# Les blocs mermaid sont sortis du Markdown, sinon ils finissent en <code>.
schemas = []


def retirer_mermaid(m):
    schemas.append(m.group(1))
    return "\n@@MERMAID%d@@\n" % (len(schemas) - 1)


texte = re.sub(r"```mermaid\n(.*?)```", retirer_mermaid, texte, flags=re.S)

corps = markdown.markdown(texte, extensions=["tables", "fenced_code", "toc", "attr_list"])

for i, s in enumerate(schemas):
    corps = corps.replace("<p>@@MERMAID%d@@</p>" % i,
                          '<div class="mermaid">%s</div>' % s)

GABARIT = """<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Scubanion — Cahier des charges</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>
  @page{size:A4 portrait;margin:14mm 15mm;}
  @page cover{size:A4 portrait;margin:0;}
  body{font-size:9.2pt;}
  .page{box-shadow:none;}

  .doc{max-width:180mm;margin:0 auto;padding:0;}
  @media screen{.doc{background:#fff;padding:14mm 15mm;margin:8mm auto;box-shadow:var(--shadow-float);}}

  .doc h1{display:none;}            /* le titre est sur la couverture */

  .doc h2{
    font-family:'Baloo 2',sans-serif;font-size:16pt;font-weight:700;
    color:var(--ink);margin:7mm 0 3mm;padding-bottom:2mm;
    border-bottom:2.5px solid var(--mist);break-after:avoid;
  }
  .doc h3{font-family:'Baloo 2',sans-serif;font-size:12.5pt;font-weight:700;color:var(--ocean-dark);margin:5mm 0 2mm;break-after:avoid;}
  .doc h4{font-family:'Baloo 2',sans-serif;font-size:10.5pt;font-weight:700;color:var(--ocean-dark);margin:4mm 0 1.5mm;break-after:avoid;}

  .doc p{margin:0 0 2.4mm;}
  .doc ul,.doc ol{margin:0 0 2.4mm;padding-left:0;}
  .doc li{margin-bottom:1.2mm;}
  .doc ul > li::before{top:3.4px;}

  .doc table{width:100%;border-collapse:collapse;font-size:8.3pt;margin:0 0 3mm;}
  .doc th,.doc td{text-align:left;vertical-align:top;padding:1.8mm 2mm;border-bottom:1px solid var(--mist);}
  .doc thead th{
    font-size:7.4pt;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
    color:var(--ocean-dark);background:var(--mist);border-bottom:none;
  }
  .doc tbody tr{break-inside:avoid;}
  .doc tbody tr:nth-child(even){background:var(--paper);}

  .doc code{
    font-family:'Consolas','SFMono-Regular',monospace;font-size:.88em;
    background:var(--mist);color:var(--ocean-dark);padding:.5px 4px;border-radius:4px;
  }
  .doc pre{
    background:var(--ink);color:#CFE3E8;padding:3mm 4mm;border-radius:var(--radius-sm);
    font-size:8pt;overflow:hidden;break-inside:avoid;margin:0 0 3mm;
  }
  .doc pre code{background:none;color:inherit;padding:0;}

  .doc blockquote{
    margin:0 0 3mm;padding:2.5mm 4mm;background:#FFF8F4;
    border-left:3px solid var(--coral);border-radius:0 var(--radius-sm) var(--radius-sm) 0;
  }
  .doc blockquote p{margin:0;font-size:8.8pt;}

  .doc hr{border:none;border-top:1px solid var(--mist);margin:5mm 0;}

  .doc input[type=checkbox]{margin-right:4px;}

  .mermaid{text-align:center;margin:0 0 3mm;break-inside:avoid;}
  .mermaid svg{max-width:100%;height:auto;}
</style>
</head>
<body>

<section class="page cover">
  <div class="cover-top">
    <img src="assets/images/logo.png" alt="">
    <span class="cover-brand">Scubanion</span>
  </div>
  <div>
    <span class="cover-kicker">Document de travail de l'équipe</span>
    <h1>Cahier<br>des charges</h1>
    <div class="cover-rule"></div>
    <p class="cover-lead">
      L'outil d'exécution : exigences numérotées et priorisées, architecture,
      décisions, planning et risques. Il se coche au fil de l'avancement et se
      met à jour à chaque contribution.
    </p>
  </div>
  <dl class="cover-meta">
    <div><dt>Équipe</dt><dd>Scubatana &middot; 4 associés</dd></div>
    <div><dt>Version</dt><dd>__VERSION__</dd></div>
    <div><dt>Charte de projet</dt><dd>document séparé, en posture entreprise</dd></div>
    <div><dt>Jalon zéro</dt><dd>12 décembre 2026</dd></div>
  </dl>
  <p class="cover-slogan">« Il ne grandit que si <span>toi</span> tu sors. »</p>
</section>

<div class="doc">
__CORPS__
</div>

<script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.1/dist/mermaid.min.js"></script>
<script>
  mermaid.initialize({
    startOnLoad: true,
    theme: 'base',
    themeVariables: {
      primaryColor: '#DCEBEE', primaryTextColor: '#1E3A42', primaryBorderColor: '#2F6B7A',
      lineColor: '#4C6B73', secondaryColor: '#FDE7DC', tertiaryColor: '#F5FAFB',
      fontFamily: 'Inter, sans-serif', fontSize: '13px'
    }
  });
</script>
</body>
</html>
"""

version = "inconnue"
m = re.search(r"\*\*Version\*\*\s*:\s*([^\n·]+)", io.open(SRC, encoding="utf-8").read())
if m:
    version = m.group(1).strip()

html = GABARIT.replace("__CORPS__", corps).replace("__VERSION__", version)
io.open(OUT_HTML, "w", encoding="utf-8", newline="\n").write(html)
print("HTML ecrit : %s" % OUT_HTML.name)

# Rendu PDF par le meme navigateur que les autres livrables
edge = pathlib.Path("C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe")
if not edge.exists():
    edge = pathlib.Path("C:/Program Files/Google/Chrome/Application/chrome.exe")
subprocess.run([str(edge), "--headless", "--disable-gpu", "--no-sandbox",
                "--no-pdf-header-footer",
                "--print-to-pdf=%s" % OUT_PDF,
                "--virtual-time-budget=25000",      # mermaid a besoin de temps
                OUT_HTML.as_uri()],
               stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

if OUT_PDF.exists():
    print("PDF ecrit : %s (%.1f Mo)" % (OUT_PDF.name, OUT_PDF.stat().st_size / 1e6))
else:
    print("echec du rendu PDF")
