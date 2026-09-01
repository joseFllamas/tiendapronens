#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Unificacion de fotos de producto Pronens.
Normaliza a fondo gris + espaciado (lienzo comun) SOLO las fotos que salen
impecables de forma deterministica. Las dudosas las aparta para Nano Banana.

Regla (todo lo dudoso a IA):
  - Se queda en 'ok/' si:
      * es PNG con transparencia real (ya recortado), o
      * el fondo es blanco/claro y uniforme (esquinas), y
      * el resultado pasa el control de calidad (producto compacto, con margen).
  - Todo lo demas (fondo de color, textura, escena, atrezo, blanco sobre
    blanco que se come el producto, varios fondos) se copia a 'a_nanobanana/'.

Uso:
  pip install pillow numpy scipy
  python3 unificar_imagenes.py --in CARPETA_ENTRADA --out CARPETA_SALIDA
  # opcionales: --w 1200 --h 1600 --margin 150 --gray "#f1f1f0"

Salida:
  out/ok/            imagenes normalizadas listas para subir
  out/a_nanobanana/  originales que hay que pasar por Nano Banana
  out/manifest.csv   decision y metricas de cada imagen
  out/PROMPT_nanobanana.txt
"""
import os, sys, csv, argparse, shutil
import numpy as np
from PIL import Image
from scipy import ndimage

EXTS = (".jpg", ".jpeg", ".png", ".webp", ".JPG", ".JPEG", ".PNG", ".WEBP")

PROMPT = (
    "Put this product photo on a plain #f1f1f0 light-gray background. "
    "The gray background must cover the ENTIRE image, edge to edge: no white background, "
    "no white rectangle, no frame or border of the original photo may remain anywhere. "
    "If there is a single clear product, cut it out cleanly and center it. "
    "If there are several elements or no single clear object, keep the composition intact "
    "and change only the background. "
    "If the product is white or very light, keep it clean bright white so it clearly stands "
    "out against the #f1f1f0 gray. "
    "Never move, resize, add or remove any object or person, and preserve every print, text, "
    "fold and embroidery exactly. No new shadows or reflections."
)

def hex2rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))

def load(path):
    im = Image.open(path).convert("RGBA")
    if max(im.size) > 1500:
        r = 1500 / max(im.size)
        im = im.resize((int(im.size[0]*r), int(im.size[1]*r)))
    has_alpha = im.getchannel("A").getextrema()[0] < 250
    bg = Image.new("RGBA", im.size, (255, 255, 255, 255))
    bg.alpha_composite(im)
    return bg.convert("RGB"), has_alpha, im

def corner_stats(rgb):
    a = np.asarray(rgb).astype(np.float32); k = 10
    cs = [a[:k,:k].reshape(-1,3).mean(0), a[:k,-k:].reshape(-1,3).mean(0),
          a[-k:,:k].reshape(-1,3).mean(0), a[-k:,-k:].reshape(-1,3).mean(0)]
    lum = float(np.mean([c.mean() for c in cs]) / 255.0)
    spread = float(max(np.linalg.norm(cs[i]-cs[j]) for i in range(4) for j in range(i+1,4)) / 441.67)
    return lum, spread

def fg_from_bg(rgb, tol=46):
    a = np.asarray(rgb).astype(np.int16)
    fr = np.concatenate([a[0:6].reshape(-1,3), a[-6:].reshape(-1,3),
                         a[:,0:6].reshape(-1,3), a[:,-6:].reshape(-1,3)])
    bgc = np.median(fr, 0)
    dist = np.sqrt(((a - bgc)**2).sum(2))
    cand = dist < tol
    lbl, n = ndimage.label(cand)
    border = set(lbl[0]) | set(lbl[-1]) | set(lbl[:,0]) | set(lbl[:,-1]); border.discard(0)
    fg = ~np.isin(lbl, list(border))
    return ndimage.binary_fill_holes(fg)

def normalize(path, GRAY, CW, CH, MARGIN):
    rgb, has_alpha, im = load(path)
    lum, spread = corner_stats(rgb)
    if has_alpha:
        fg = np.asarray(im.getchannel("A")) > 40
    else:
        fg = fg_from_bg(rgb)
    fg = ndimage.binary_fill_holes(fg)
    ys, xs = np.where(fg)
    if len(xs) < 30:
        return None, has_alpha, lum, spread
    x0, x1, y0, y1 = xs.min(), xs.max(), ys.min(), ys.max()
    sub = rgb.crop((x0, y0, x1+1, y1+1))
    m = Image.fromarray((fg[y0:y1+1, x0:x1+1]*255).astype("uint8"))
    IW, IH = CW - 2*MARGIN, CH - 2*MARGIN
    s = min(IW/sub.width, IH/sub.height, 3.0)
    nw, nh = max(1, int(sub.width*s)), max(1, int(sub.height*s))
    sub = sub.resize((nw, nh)); m = m.resize((nw, nh))
    canvas = Image.new("RGB", (CW, CH), GRAY)
    canvas.paste(sub, ((CW-nw)//2, (CH-nh)//2), m)
    return canvas, has_alpha, lum, spread

def qc(canvas, GRAY, CW, CH):
    a = np.asarray(canvas).astype(np.int16)
    d = np.sqrt(((a - np.array(GRAY))**2).sum(2))
    mask = d > 22
    T = int(mask.sum()); frac = T/(CW*CH)
    if frac < 0.045: return False, "producto comido"
    if frac > 0.82:  return False, "sin margen"
    lbl, n = ndimage.label(mask)
    sizes = ndimage.sum(mask, lbl, range(1, n+1))
    dom = float(sizes.max()/T)
    ys, xs = np.where(mask)
    dens = T / ((xs.max()-xs.min()+1)*(ys.max()-ys.min()+1))
    if dom < 0.80:  return False, "disperso dom=%.2f" % dom
    if dens < 0.38: return False, "disperso dens=%.2f" % dens
    return True, "ok dom=%.2f dens=%.2f" % (dom, dens)

def pad_frame(path, GRAY, CW, CH, MARGIN, tol=20):
    """Modo pad (para salidas de Nano Banana): NO recorta el producto. Detecta
    el producto sobre el fondo gris que ya trae la imagen y lo recoloca centrado
    con margen uniforme. Tolerancia baja para no comerse productos blancos."""
    im = Image.open(path).convert("RGB")
    a = np.asarray(im).astype(np.int16)
    fr = np.concatenate([a[0:6].reshape(-1, 3), a[-6:].reshape(-1, 3),
                         a[:, 0:6].reshape(-1, 3), a[:, -6:].reshape(-1, 3)])
    bgc = np.median(fr, 0)
    dist = np.sqrt(((a - bgc) ** 2).sum(2))
    # remapeo suave del fondo al gris exacto: los pixeles cercanos al color de
    # fondo se desplazan hacia GRAY, el producto queda intacto (sin costuras)
    af = np.asarray(im).astype(np.float32)
    target = np.array(GRAY, dtype=np.float32)
    peso = np.clip(1.0 - dist / (2.0 * tol), 0.0, 1.0)[:, :, None]
    af = np.clip(af + (target - bgc)[None, None, :] * peso, 0, 255)
    im2 = Image.fromarray(af.astype("uint8"))
    ys, xs = np.where(dist > tol)
    if len(xs) < 30:
        return None
    x0, x1, y0, y1 = int(xs.min()), int(xs.max()), int(ys.min()), int(ys.max())
    sub = im2.crop((x0, y0, x1 + 1, y1 + 1))
    IW, IH = CW - 2 * MARGIN, CH - 2 * MARGIN
    s = min(IW / sub.width, IH / sub.height, 3.0)
    nw, nh = max(1, int(sub.width * s)), max(1, int(sub.height * s))
    sub = sub.resize((nw, nh))
    canvas = Image.new("RGB", (CW, CH), GRAY)
    canvas.paste(sub, ((CW - nw) // 2, (CH - nh) // 2))
    return canvas

def decide(path, GRAY, CW, CH, MARGIN):
    canvas, has_alpha, lum, spread = normalize(path, GRAY, CW, CH, MARGIN)
    if canvas is None:
        return False, canvas, "comido/vacio", lum, spread
    ok, why = qc(canvas, GRAY, CW, CH)
    if has_alpha:
        return ok, canvas, "png "+why, lum, spread
    if lum < 0.82:
        return False, canvas, "fondo no-blanco lum=%.2f" % lum, lum, spread
    if spread > 0.12:
        return False, canvas, "esquinas no uniformes %.2f" % spread, lum, spread
    return ok, canvas, why, lum, spread

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="inp", required=True)
    ap.add_argument("--out", dest="out", required=True)
    ap.add_argument("--w", type=int, default=1200)
    ap.add_argument("--h", type=int, default=1600)
    ap.add_argument("--margin", type=int, default=150)
    ap.add_argument("--gray", default="#f1f1f0")
    ap.add_argument("--include-all", action="store_true",
                    help="No saltar la carpeta styles/ ni los banners (por defecto se saltan)")
    ap.add_argument("--mode", choices=["auto", "pad"], default="auto",
                    help="auto = separa y normaliza (catalogo original). "
                         "pad = solo re-encuadra con margenes, sin recortar (para salidas de Nano Banana)")
    ap.add_argument("--pad-tol", type=int, default=20,
                    help="Tolerancia para detectar el borde gris en modo pad (bajo = no se come productos claros)")
    args = ap.parse_args()
    GRAY = hex2rgb(args.gray)
    okdir = os.path.join(args.out, "ok")
    aidir = os.path.join(args.out, "a_nanobanana")
    os.makedirs(okdir, exist_ok=True); os.makedirs(aidir, exist_ok=True)
    with open(os.path.join(args.out, "PROMPT_nanobanana.txt"), "w") as f:
        f.write(PROMPT + "\n")
    SKIP_DIRS = {"styles"}  # derivadas de Drupal, se regeneran solas
    BANNER_HINTS = ("banner", "portada", "cabecera", "slider", "logo", "newsletter", "cartel")
    files = []
    for dp, dns, ns in os.walk(args.inp):
        if not args.include_all:
            dns[:] = [d for d in dns if d.lower() not in SKIP_DIRS]
        for n in ns:
            if not n.endswith(EXTS):
                continue
            if not args.include_all and any(h in n.lower() for h in BANNER_HINTS):
                continue
            files.append(os.path.join(dp, n))
    files.sort()
    rows = []; nok = nai = 0
    for i, p in enumerate(files, 1):
        rel = os.path.relpath(p, args.inp)
        base = os.path.splitext(os.path.basename(p))[0]
        if args.mode == "pad":
            try:
                canvas = pad_frame(p, GRAY, args.w, args.h, args.margin, args.pad_tol)
            except Exception as e:
                canvas = None
            if canvas is not None:
                canvas.save(os.path.join(okdir, base + ".jpg"), quality=90)
                nok += 1; rows.append([rel, "PAD", "ok", "", ""])
            else:
                shutil.copy2(p, os.path.join(aidir, os.path.basename(p)))
                nai += 1; rows.append([rel, "PAD-SIN-PRODUCTO", "revisar a mano", "", ""])
            if i % 25 == 0:
                print("  %d/%d  pad=%d" % (i, len(files), nok))
            continue
        try:
            keep, canvas, why, lum, spread = decide(p, GRAY, args.w, args.h, args.margin)
        except Exception as e:
            keep, canvas, why, lum, spread = False, None, "error:%s" % e, 0, 0
        if keep and canvas is not None:
            canvas.save(os.path.join(okdir, base + ".jpg"), quality=90)
            nok += 1; dec = "SCRIPT"
        else:
            shutil.copy2(p, os.path.join(aidir, os.path.basename(p)))
            nai += 1; dec = "NANOBANANA"
        rows.append([rel, dec, why, "%.3f" % lum, "%.3f" % spread])
        if i % 25 == 0:
            print("  %d/%d  script=%d  nano=%d" % (i, len(files), nok, nai))
    with open(os.path.join(args.out, "manifest.csv"), "w", newline="") as f:
        w = csv.writer(f); w.writerow(["archivo", "decision", "motivo", "lum_esquinas", "dispersion_esquinas"])
        w.writerows(rows)
    print("\nHECHO. total=%d  script(ok)=%d  a_nanobanana=%d" % (len(files), nok, nai))
    print("Revisa out/ok visualmente; lo que no te guste, muevelo a a_nanobanana/.")

if __name__ == "__main__":
    main()
