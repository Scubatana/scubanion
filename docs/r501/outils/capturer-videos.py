# -*- coding: utf-8 -*-
"""Rend les animations de borne en MP4, image par image.

On met la timeline GSAP en pause et on la deplace a t = i/fps avant chaque
capture : aucune image n'est sautee, contrairement a un enregistrement d'ecran
en temps reel. Les animations exposent window.__tl et window.__upAll pour ca.

Prerequis :
    pip install playwright && python -m playwright install chromium
    ffmpeg doit etre dans le PATH

Usage, depuis la racine du depot :
    python docs/r501/outils/capturer-videos.py
    python docs/r501/outils/capturer-videos.py chemin/de/sortie
"""
import pathlib, shutil, subprocess, sys

from playwright.sync_api import sync_playwright

FPS = 30
HOLD_END = 1.2            # secondes de pause sur la derniere image
W, H = 1920, 1080

ICI = pathlib.Path(__file__).resolve().parent          # docs/r501/outils
R501 = ICI.parent                                      # docs/r501
SOURCES = R501 / "animations"
SORTIE = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else R501 / "videos"

JOBS = [
    ("borne-musee.html", "scubanion-borne-musee"),
    ("borne-trattoria.html", "scubanion-borne-trattoria"),
]


def capture(src, slug, pw):
    frames = SORTIE / ("_frames_" + slug)
    if frames.exists():
        shutil.rmtree(frames)
    frames.mkdir(parents=True)

    navigateur = pw.chromium.launch(args=["--force-device-scale-factor=1"])
    page = navigateur.new_context(
        viewport={"width": W, "height": H},
        device_scale_factor=1,
        reduced_motion="no-preference",     # sinon l'animation se joue en mode reduit
    ).new_page()

    page.goto(src.as_uri())
    page.wait_for_function("() => window.__tl && window.gsap", timeout=30000)
    page.wait_for_timeout(1200)             # polices chargees et premier rendu

    duree = page.evaluate(
        "() => { window.__tl.pause(0); return window.__tl.totalDuration(); }")
    n = int(duree * FPS) + 1
    print("  %s : %.2f s -> %d images" % (slug, duree, n), flush=True)

    for i in range(n):
        page.evaluate(
            "t => { window.__tl.pause(t); if (window.__upAll) window.__upAll(); }",
            i / FPS)
        page.screenshot(path=str(frames / ("f%05d.png" % i)), animations="disabled")

    # on prolonge la derniere image, pour laisser lire le logo et le slogan
    derniere = frames / ("f%05d.png" % (n - 1))
    for k in range(int(HOLD_END * FPS)):
        shutil.copyfile(derniere, frames / ("f%05d.png" % (n + k)))

    navigateur.close()

    mp4 = SORTIE / (slug + ".mp4")
    subprocess.run([
        "ffmpeg", "-y", "-loglevel", "error",
        "-framerate", str(FPS),
        "-i", str(frames / "f%05d.png"),
        "-c:v", "libx264", "-preset", "slow", "-crf", "19",
        "-pix_fmt", "yuv420p",              # indispensable pour Windows Media Player
        "-movflags", "+faststart",
        str(mp4),
    ], check=True)
    shutil.rmtree(frames)
    print("  -> %s (%.1f Mo)" % (mp4.name, mp4.stat().st_size / 1e6), flush=True)


SORTIE.mkdir(parents=True, exist_ok=True)
with sync_playwright() as pw:
    for fichier, slug in JOBS:
        src = SOURCES / fichier
        if not src.exists():
            print("  absent : %s" % src)
            continue
        capture(src, slug, pw)
print("termine")
