#!/usr/bin/env python3
"""Genera la imagen para compartir (1200x630) y los íconos de la aplicación
instalable con el color de acento. Son dibujos propios, sin fotos de terceros.

    python3 scripts/generar_marca.py "Ticketera QR"   ->  app/estaticos/compartir.jpg y app/estaticos/app/*.png

Necesita Pillow (pip install pillow) y la tipografía DejaVu Sans (viene con casi
todas las distribuciones de Linux; en otro sistema, pasá la ruta con --fuente).
"""
import argparse
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

RAIZ = Path(__file__).resolve().parent.parent
EST = RAIZ / "app" / "estaticos"
FONDO, TINTA, ACENTO, TENUE = "#0b0a09", "#eef1f4", "#2ec4b6", "#9a9184"
PUNTOS = ["#2ec4b6", "#e9c46a", "#7b8cff"]


def fuente(ruta, tam):
    return ImageFont.truetype(ruta, tam)


def puntos(d, x, y, r):
    for i, c in enumerate(PUNTOS):
        cx = x + i * r * 3
        d.ellipse([cx - r, y - r, cx + r, y + r], fill=c)


def icono(lado, ruta_f, redondeado=True, margen=0.0):
    im = Image.new("RGBA", (lado, lado), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    if redondeado:
        d.rounded_rectangle([0, 0, lado - 1, lado - 1], radius=int(lado * 0.22), fill=FONDO)
    else:
        d.rectangle([0, 0, lado, lado], fill=FONDO)
    f = fuente(ruta_f, int(lado * (0.42 - margen)))
    w = d.textlength("QR", font=f)
    d.text(((lado - w) / 2, lado * 0.16 + lado * margen / 2), "QR", font=f, fill=ACENTO)
    r = max(2, int(lado * 0.035))
    puntos(d, lado / 2 - r * 3, lado * 0.74 - lado * margen / 3, r)
    return im


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("nombre", nargs="?", default="Ticketera QR")
    ap.add_argument("--fuente", default="/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf")
    a = ap.parse_args()

    im = Image.new("RGB", (1200, 630), FONDO)
    d = ImageDraw.Draw(im)
    d.text((80, 80), "ENTRADAS CON QR", font=fuente(a.fuente, 26), fill=TENUE)
    f = fuente(a.fuente, 84)
    d.text((80, 180), "EL EVENTO, EN VIVO.", font=f, fill=TINTA)
    d.text((80, 285), "LA ENTRADA, EN", font=f, fill=ACENTO)
    d.text((80, 390), "TU CELULAR.", font=f, fill=ACENTO)
    d.text((80, 545), a.nombre.upper(), font=fuente(a.fuente, 34), fill=TINTA)
    puntos(d, 1050, 560, 10)
    im.save(EST / "compartir.jpg", quality=88)

    (EST / "app").mkdir(exist_ok=True)
    for lado, nombre in [(64, "icono-64.png"), (192, "icono-192.png"), (512, "icono-512.png")]:
        icono(lado, a.fuente).save(EST / "app" / nombre)
    icono(512, a.fuente, redondeado=False, margen=0.12).convert("RGB").save(EST / "app" / "icono-maskable-512.png")
    icono(180, a.fuente, redondeado=False).convert("RGB").save(EST / "app" / "apple-touch-icon.png")
    print("Listo: compartir.jpg e íconos en", EST)


if __name__ == "__main__":
    main()
