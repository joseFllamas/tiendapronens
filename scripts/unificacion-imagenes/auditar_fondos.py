#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Audita (y opcionalmente arregla) los fondos de una carpeta de imagenes ya
generadas: detecta las que no tienen el gris canonico, las que traen degradado
de estudio y las que se guardaron CRUDAS (sin el re-encuadre 3:4).

El arreglo NO llama a la API: ajusta un modelo suave del fondo (polinomio),
lo aplana al gris exacto respetando el producto, y recoloca el sujeto en el
lienzo 1200x1600 con el margen estandar.

Requisitos:  pip install pillow numpy scipy

Uso (desde la raiz del proyecto):
  # solo auditar (no toca nada)
  python3 scripts/unificacion-imagenes/auditar_fondos.py --dir salida_unificacion/baberos_bebe

  # auditar y arreglar: escribe las corregidas en <dir>/_corregidas
  python3 scripts/unificacion-imagenes/auditar_fondos.py --dir CARPETA --fix

  # arreglar sobrescribiendo los originales (haz copia antes)
  python3 scripts/unificacion-imagenes/auditar_fondos.py --dir CARPETA --fix --in-place

Opcionales: --gray "#f1f1f0" --w 1200 --h 1600 --margin 150

Que marca cada aviso:
  CRUDA      el tamano no es el del lienzo -> no paso por el re-encuadre
  FONDO      el color de fondo se aleja del gris objetivo
  DEGRADADO  el fondo no es plano (mas claro en el centro, viñeteado...)
  DESCENTRADA  el producto no esta centrado: casi siempre una sombra lateral
             se colo en la caja de recorte y desplazo (y encogio) el producto
Nota: el % de "restos" puede dar falso positivo cuando el propio producto es
de un tono claro y neutro (crema, beige). Por eso solo se avisa, no decide.
"""
import os, sys, glob, argparse
import numpy as np
from PIL import Image, ImageFile
from scipy import ndimage

ImageFile.LOAD_TRUNCATED_IMAGES = True

def hex2rgb(h):
    h = h.lstrip("#")
    return np.array([int(h[i:i+2], 16) for i in (0, 2, 4)], dtype=np.float32)

def _terms(X, Y):
    return [np.ones_like(X), X, Y, X*X, X*Y, Y*Y, X*X*Y, X*Y*Y, X*X*Y*Y]

def _fit_bg(a, mask, step=6):
    h, w, _ = a.shape
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    X = xx/w - .5; Y = yy/h - .5
    Xs, Ys, ms, as_ = X[::step, ::step], Y[::step, ::step], mask[::step, ::step], a[::step, ::step]
    A = np.stack([t[ms] for t in _terms(Xs, Ys)], 1)
    T = _terms(X, Y)
    model = np.empty_like(a)
    for c in range(3):
        coef, *_ = np.linalg.lstsq(A, as_[..., c][ms], rcond=None)
        model[..., c] = sum(cf*t for cf, t in zip(coef, T))
    return model

def medir(path, gray):
    im = Image.open(path).convert("RGB")
    a = np.asarray(im).astype(np.float32)
    h, w, _ = a.shape
    k = max(8, int(min(h, w)*0.02))
    fr = np.concatenate([a[:k].reshape(-1, 3), a[-k:].reshape(-1, 3),
                         a[:, :k].reshape(-1, 3), a[:, -k:].reshape(-1, 3)])
    bg = np.median(fr, 0)
    desv = float(np.sqrt(((bg - gray)**2).sum()))
    c = [a[:k, :k].reshape(-1, 3).mean(0), a[:k, -k:].reshape(-1, 3).mean(0),
         a[-k:, :k].reshape(-1, 3).mean(0), a[-k:, -k:].reshape(-1, 3).mean(0)]
    grad = float(max(np.linalg.norm(c[i]-c[j]) for i in range(4) for j in range(i+1, 4)))
    chroma = a.max(2) - a.min(2); dist = np.sqrt(((a - gray)**2).sum(2))
    restos = float(((chroma < 25) & (dist > 8) & (dist < 90)).mean())
    # descentrado horizontal del producto (0 = centrado)
    fuerte = dist > 45
    dx = float((np.where(fuerte)[1].mean() - (w-1)/2)/w) if fuerte.sum() > 100 else 0.0
    return im.size, desv, grad, restos, dx

def arreglar(path, gray, W, H, MARGIN, resid_thr=26):
    im = Image.open(path).convert("RGB")
    a = np.asarray(im).astype(np.float32)
    h, w, _ = a.shape
    k = max(8, int(min(h, w)*0.02))
    borde = np.concatenate([a[:k].reshape(-1, 3), a[-k:].reshape(-1, 3),
                            a[:, :k].reshape(-1, 3), a[:, -k:].reshape(-1, 3)])
    bg0 = np.median(borde, 0)
    mask = np.sqrt(((a - bg0)**2).sum(2)) < 70
    for _ in range(3):
        if mask.sum() < 5000: break
        model = _fit_bg(a, mask)
        resid = np.sqrt(((a - model)**2).sum(2))
        mask = resid < resid_thr
    model = _fit_bg(a, mask)
    resid = np.sqrt(((a - model)**2).sum(2))
    # SOMBRA proyectada: neutra y mas oscura que el fondo modelado. No es producto:
    # se aplana igual que el fondo y se excluye de la caja de recorte.
    chroma = a.max(2) - a.min(2)
    sombra = (chroma < 22) & (a.mean(2) < model.mean(2)) & (resid < 150)
    peso = np.clip(1.0 - (resid - resid_thr)/resid_thr, 0, 1)
    peso[sombra] = 1.0
    peso = ndimage.gaussian_filter(peso.astype(np.float32), 1.8)
    peso[(resid > resid_thr*2) & ~sombra] = 0.0
    peso = peso[:, :, None]
    out = np.clip(a + (gray[None, None, :] - model)*peso, 0, 255)
    sujeto = ndimage.binary_fill_holes(ndimage.binary_opening(resid > resid_thr*1.6, np.ones((5, 5))) & ~sombra)
    lbl, n = ndimage.label(sujeto)
    if n:
        sizes = ndimage.sum(sujeto, lbl, range(1, n+1))
        sujeto = np.isin(lbl, [i+1 for i, s in enumerate(sizes) if s > 0.004*h*w])
    ys, xs = np.where(sujeto)
    if len(xs) < 50:
        return None
    x0, x1, y0, y1 = int(xs.min()), int(xs.max()), int(ys.min()), int(ys.max())
    sub = Image.fromarray(out.astype("uint8")).crop((x0, y0, x1+1, y1+1))
    IW, IH = W - 2*MARGIN, H - 2*MARGIN
    s = min(IW/sub.width, IH/sub.height, 3.0)
    nw, nh = max(1, int(sub.width*s)), max(1, int(sub.height*s))
    canvas = Image.new("RGB", (W, H), tuple(int(v) for v in gray))
    canvas.paste(sub.resize((nw, nh)), ((W-nw)//2, (H-nh)//2))
    return canvas

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dir", required=True, help="Carpeta con las imagenes generadas")
    ap.add_argument("--fix", action="store_true", help="Corregir las que fallen")
    ap.add_argument("--in-place", action="store_true", help="Sobrescribir los originales (con --fix)")
    ap.add_argument("--gray", default="#f1f1f0")
    ap.add_argument("--w", type=int, default=1200)
    ap.add_argument("--h", type=int, default=1600)
    ap.add_argument("--margin", type=int, default=150)
    args = ap.parse_args()
    gray = hex2rgb(args.gray)

    files = sorted(f for f in glob.glob(os.path.join(args.dir, "*"))
                   if f.lower().endswith((".png", ".jpg", ".jpeg", ".webp")))
    if not files:
        print("No hay imagenes en", args.dir); sys.exit(1)

    malas = []
    print("%-32s %11s %7s %7s %8s %7s" % ("imagen", "tamano", "desv", "grad", "restos", "descen"))
    filas = []
    for p in files:
        try:
            sz, d, g, r, dx = medir(p, gray)
        except Exception as e:
            print("  ERROR leyendo %s: %s" % (os.path.basename(p), e)); continue
        avisos = []
        if sz != (args.w, args.h): avisos.append("CRUDA")
        if d > 10: avisos.append("FONDO")
        if g > 18: avisos.append("DEGRADADO")
        if abs(dx) > 0.03: avisos.append("DESCENTRADA")
        filas.append((p, sz, d, g, r, dx, avisos))
        if avisos: malas.append(p)
    for p, sz, d, g, r, dx, av in sorted(filas, key=lambda x: -(x[2]+x[3]+abs(x[5])*100)):
        print("%-32s %11s %7.1f %7.1f %7.1f%% %+7.3f  %s" %
              (os.path.basename(p)[:32], "%dx%d" % sz, d, g, r*100, dx, " ".join(av)))

    print("\n== RESUMEN ==  total %d | correctas %d | a corregir %d" % (len(filas), len(filas)-len(malas), len(malas)))
    if not args.fix or not malas:
        if malas and not args.fix:
            print("Ejecuta otra vez con --fix para corregirlas.")
        return

    dest = args.dir if args.in_place else os.path.join(args.dir, "_corregidas")
    if not args.in_place:
        os.makedirs(dest, exist_ok=True)
    ok = fallo = 0
    for p in malas:
        try:
            out = arreglar(p, gray, args.w, args.h, args.margin)
            if out is None:
                print("  no se detecta el producto:", os.path.basename(p)); fallo += 1; continue
            out.save(os.path.join(dest, os.path.splitext(os.path.basename(p))[0] + ".png"))
            ok += 1
        except Exception as e:
            print("  fallo en %s: %s" % (os.path.basename(p), str(e)[:70])); fallo += 1
    print("\nCorregidas %d (fallos %d) en: %s" % (ok, fallo, dest))
    if not args.in_place:
        print("Revisalas y, si te convencen, muevelas sobre las originales.")

if __name__ == "__main__":
    main()
