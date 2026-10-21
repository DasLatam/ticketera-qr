#!/usr/bin/python3
"""Genera las imágenes de los eventos de ejemplo: manchas de color difusas
con grano, una paleta por evento. Son propias (nada de fotos de terceros) y
se borran junto con los datos de ejemplo.

    /usr/bin/python3 scripts/generar_fotos_ejemplo.py   →  semilla/fotos/*.jpg
"""
from pathlib import Path
import numpy as np
from PIL import Image, ImageFilter

SALIDA = Path(__file__).resolve().parent.parent / "semilla" / "fotos"
W, H = 1800, 1200

PALETAS = {
    "noche-de-terraza":     ["#0d0f1a", "#c8402a", "#f2a65a", "#3b2f63"],
    "cena-a-ciegas":        ["#14110f", "#7a1f1f", "#d9a066", "#2b2420"],
    "jazz-en-la-biblioteca":["#0e1416", "#1f4e5f", "#d8b25c", "#26323a"],
    "after-office-palermo": ["#121212", "#ff6b4a", "#ffd166", "#2d6a8f"],
    "cata-vinos-naturales": ["#170c12", "#6b1d3a", "#c97b84", "#e8c8a8"],
    "techno-galpon":        ["#050507", "#3a0ca3", "#7209b7", "#f72585"],
    "brunch-de-domingo":    ["#f3e9dc", "#e0a458", "#c44536", "#8aa29e"],
    "taller-ceramica":      ["#e9e2d6", "#b5835a", "#6d5d4b", "#d6c3a6"],
    "yoga-al-amanecer":     ["#f6efe6", "#f4a261", "#e76f51", "#90be6d"],
    "gala-fin-de-anio":     ["#0b0b0c", "#b08d57", "#e8d9b5", "#2a2a2e"],
    "stand-up-intimo":      ["#101014", "#e63946", "#f1faee", "#1d3557"],
    "lanzamiento-producto": ["#0f1a1f", "#2ec4b6", "#e0fbfc", "#3d5a80"],
    "cumbia-y-vermut":      ["#1a0f0a", "#f77f00", "#fcbf49", "#d62828"],
    "cine-bajo-estrellas":  ["#070b14", "#16324f", "#e9c46a", "#2a9d8f"],
}


def hexrgb(h):
    h = h.lstrip("#")
    return np.array([int(h[i:i + 2], 16) for i in (0, 2, 4)], dtype=np.float32)


def imagen(nombre, colores, semilla):
    rng = np.random.default_rng(semilla)
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    fondo = hexrgb(colores[0])
    lienzo = np.zeros((H, W, 3), np.float32) + fondo
    # Resplandores: pocas manchas, medianas, que dejan ver el fondo.
    for i in range(4):
        c = hexrgb(colores[1 + i % (len(colores) - 1)])
        cx, cy = rng.uniform(.05, .95) * W, rng.uniform(.05, .95) * H
        r = rng.uniform(.12, .26) * W
        peso = np.exp(-(((xx - cx) ** 2 + (yy - cy) ** 2) / (2 * r * r)))[..., None] * .95
        lienzo = lienzo * (1 - peso) + c * peso
    img = Image.fromarray(np.clip(lienzo, 0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(18))
    a = np.asarray(img).astype(np.float32)
    # Un círculo nítido, grande y corrido del centro: el «afiche».
    c = hexrgb(colores[1])
    cx, cy = rng.choice([.28, .72]) * W, rng.uniform(.35, .65) * H
    r = rng.uniform(.26, .36) * H
    dist = np.sqrt((xx - cx) ** 2 + (yy - cy) ** 2)
    disco = np.clip(r - dist, 0, 1.5)[..., None] / 1.5
    luz = (1 - np.clip((yy - (cy - r)) / (2 * r), 0, 1))[..., None]  # más claro arriba
    tono = c * (0.75 + 0.35 * luz)
    a = a * (1 - disco * .92) + tono * (disco * .92)
    # Líneas finas horizontales que cruzan el disco: textura sin ruido visual.
    lineas = ((yy.astype(int) % 22) < 2)[..., None] & (disco > .5)
    a = np.where(lineas, a * .82, a)
    d = np.sqrt(((xx - W / 2) / W) ** 2 + ((yy - H / 2) / H) ** 2)
    a *= (1 - .5 * d ** 2)[..., None]
    a += rng.normal(0, 6, (H, W, 1))
    Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)).save(SALIDA / f"{nombre}.jpg", quality=88)


if __name__ == "__main__":
    SALIDA.mkdir(parents=True, exist_ok=True)
    for i, (n, c) in enumerate(PALETAS.items()):
        imagen(n, c, 11 + i)
        # Una segunda imagen por evento para que la galería tenga qué mostrar.
        imagen(n + "-2", [c[0]] + c[1:][::-1], 101 + i)
        print("ok", n)
