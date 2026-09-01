#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Genera con Nano Banana, para cada bolsa mochila del CSV, una foto de un nino
de 2-3 anos DE ESPALDAS llevandola como mochila (cordones blancos como
tirantes), sobre fondo gris #f1f1f0, lienzo 3:4 (1200x1600) y margen 150,
como el resto del catalogo.

Usa DOS referencias por llamada: la imagen ancla de estilo (tu resultado
aprobado, en salida_unificacion/mochila_ejemplo.png: define pose, encuadre,
iluminacion y fondo) y la foto del producto (define el dibujo exacto).
El nino varia poco y de forma estable por producto_id: SOLO pelo, peinado,
tono de piel y colores de la ropa; la pose y el estilo vienen del ancla.

Requisitos:  pip install google-genai pillow numpy scipy
             export GEMINI_API_KEY="tu_clave"

Uso (desde la raiz del proyecto):
  python3 scripts/unificacion-imagenes/generar_mochilas_espalda.py
  # opcionales:
  #   --csv salida_unificacion/bolsasmochila.csv   --webroot web
  #   --out salida_unificacion/bolsasmochila_espalda
  #   --model gemini-2.5-flash-image   --workers 3   --max 2   --dry-run
  #   --w 1200 --h 1600 --margin 150 --gray "#f1f1f0"

Reanudable (salta lo ya hecho). Si el re-encuadre falla, guarda la cruda en
revisar/ y lo anota en errores.log (nunca cruda silenciosa). El re-encuadre
aplana el degradado de estudio con ajuste polinomico y elimina la sombra
proyectada antes de calcular la caja, para que quede centrado de verdad.
"""
import os, sys, csv, time, argparse, random, threading, unicodedata
from io import BytesIO
from concurrent.futures import ThreadPoolExecutor, as_completed

SUFIJO = "_espalda"

NINOS = [
    "short wavy light-brown hair, a plain white t-shirt and denim shorts",
    "straight black hair, a soft yellow t-shirt and gray shorts",
    "curly blonde hair, a mint-green t-shirt and beige shorts",
    "dark skin and short curly hair, a light-blue t-shirt and navy shorts",
    "wavy red hair, a cream t-shirt and olive-green shorts",
    "brown hair in a tiny ponytail, a pale-pink t-shirt and denim shorts",
    "East Asian features and straight dark hair, a white polo and light-gray shorts",
    "very short blonde hair, a striped light-blue t-shirt and beige shorts",
    "shoulder-length brown hair, a lavender t-shirt and white shorts",
    "dark curly hair, a light aqua t-shirt and sand-colored shorts",
]

def build_prompt(desc, titulo=""):
    tema = (" (the '%s' design)" % titulo) if titulo else ""
    return (
        "Shot in a professional studio with wrap-around softbox lighting that eliminates any "
        "perceivable contact shadow or reflection: an isolated product-catalogue look on a "
        "clean, uniform, flat light-gray #f1f1f0 background covering the ENTIRE image edge to "
        "edge. "

        "The FIRST attached image is the style reference for pose, framing, lighting and "
        "background. The SECOND attached image is the SOLE reference for the bag design%s. "

        "Create a photorealistic studio photograph of a young toddler, aged 2 to 3 years old, "
        "seen completely FROM BEHIND, standing in the same pose, framing and proportions as the "
        "child in the first image, with this variation: %s. Vary ONLY the hair, the skin tone "
        "and the clothing colors; keep everything else as in the first image. The child's WHOLE "
        "body is inside the frame, including both shoes fully visible, with clear empty "
        "background below the feet; never crop the feet. "

        "Worn securely as a backpack on the child's back is EXACTLY the drawstring bag of the "
        "second image. Reproduce its printed artwork with perfect fidelity, centered on the bag "
        "panel and undistorted: same colors, same characters, same name text and same logo, "
        "clean and sharp. The bag is gently filled with unseen items, creating a few soft "
        "realistic fabric folds, placed away from the artwork, that never wrinkle, warp or "
        "deform the characters' faces or the printed name, like a catalogue photo where the "
        "folds flatter the product. The bag's WHITE drawstring cords are visible, looped over "
        "both shoulders as the backpack straps, exactly like in the first image. Do not invent "
        "any hardware that is not in the second image: no black corner patches, no metal "
        "eyelets, grommets, buckles or zippers. "

        "FRAMING: vertical 3:4 portrait, the child centered, with ample even margin of plain "
        "background on all sides." % (tema, desc)
    )

REFUERZO = (" IMPORTANT: the background must be a plain flat light-gray #f1f1f0 studio "
            "backdrop covering the whole image, with nothing else in the scene, and "
            "absolutely no shadow on the floor or wall.")

PAD = {"enabled": True, "gray": (241, 241, 240), "w": 1200, "h": 1600, "margin": 150, "tol": 20}

def hex2rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))

def _terms(X, Y):
    return [__import__("numpy").ones_like(X), X, Y, X*X, X*Y, Y*Y, X*X*Y, X*Y*Y, X*X*Y*Y]

def _fit_bg(a, mask, step=6):
    import numpy as np
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

def pad_frame_img(im, cfg, resid_thr=26):
    """Aplana el fondo al gris exacto reemplazandolo POR COMPLETO fuera del
    sujeto (sin costuras ni parches de tono), elimina la sombra proyectada y
    centra al sujeto. El sujeto se detecta con umbral sensible para que los
    zapatos y tejidos claros no queden fuera de la caja (pies cortados)."""
    import numpy as np
    from scipy import ndimage
    from PIL import Image as _Image
    gray = np.array(cfg["gray"], dtype=np.float32)
    a = np.asarray(im.convert("RGB")).astype(np.float32)
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
    chroma = a.max(2) - a.min(2)
    sombra = (chroma < 22) & (a.mean(2) < model.mean(2)) & (resid < 150)
    nucleo = ndimage.binary_opening((resid > resid_thr*0.9) & ~sombra, np.ones((3, 3)))
    lbl, n = ndimage.label(nucleo)
    if n:
        sizes = ndimage.sum(nucleo, lbl, range(1, n+1))
        nucleo = np.isin(lbl, [i+1 for i, s in enumerate(sizes) if s > 0.003*h*w])
    sujeto = ndimage.binary_fill_holes(ndimage.binary_closing(nucleo, np.ones((7, 7))))
    ys, xs = np.where(sujeto)
    if len(xs) < 50:
        return None
    sub_soft = ndimage.gaussian_filter(ndimage.binary_dilation(sujeto, np.ones((3, 3))).astype(np.float32), 2.0)
    peso_res = np.clip(1.0 - (resid - resid_thr)/resid_thr, 0, 1)
    peso = np.clip(np.maximum(peso_res, 1.0 - sub_soft), 0, 1)
    out = np.clip(a + (gray[None, None, :] - model)*peso[:, :, None], 0, 255)
    x0, x1, y0, y1 = int(xs.min()), int(xs.max()), int(ys.min()), int(ys.max())
    sub = _Image.fromarray(out.astype("uint8")).crop((x0, y0, x1+1, y1+1))
    IW, IH = cfg["w"] - 2*cfg["margin"], cfg["h"] - 2*cfg["margin"]
    s = min(IW/sub.width, IH/sub.height, 3.0)
    nw, nh = max(1, int(sub.width*s)), max(1, int(sub.height*s))
    canvas = _Image.new("RGB", (cfg["w"], cfg["h"]), cfg["gray"])
    canvas.paste(sub.resize((nw, nh)), ((cfg["w"]-nw)//2, (cfg["h"]-nh)//2))
    return canvas

def pad_simple(im, cfg):
    """Plan B: remapeo suave del tono de fondo + encuadre (sin scipy)."""
    import numpy as np
    from PIL import Image as _Image
    rgb = im.convert("RGB")
    a = np.asarray(rgb).astype(np.float32)
    fr = np.concatenate([a[0:6].reshape(-1, 3), a[-6:].reshape(-1, 3),
                         a[:, 0:6].reshape(-1, 3), a[:, -6:].reshape(-1, 3)])
    bgc = np.median(fr, 0)
    dist = np.sqrt(((a - bgc) ** 2).sum(2))
    target = np.array(cfg["gray"], dtype=np.float32)
    peso = np.clip(1.0 - dist / (2.0 * cfg["tol"]), 0.0, 1.0)[:, :, None]
    a = np.clip(a + (target - bgc)[None, None, :] * peso, 0, 255)
    im2 = _Image.fromarray(a.astype("uint8"))
    ys, xs = np.where(dist > cfg["tol"])
    if len(xs) < 30:
        return None
    x0, x1, y0, y1 = int(xs.min()), int(xs.max()), int(ys.min()), int(ys.max())
    sub = im2.crop((x0, y0, x1 + 1, y1 + 1))
    IW, IH = cfg["w"] - 2 * cfg["margin"], cfg["h"] - 2 * cfg["margin"]
    s = min(IW / sub.width, IH / sub.height, 3.0)
    nw, nh = max(1, int(sub.width * s)), max(1, int(sub.height * s))
    canvas = _Image.new("RGB", (cfg["w"], cfg["h"]), cfg["gray"])
    canvas.paste(sub.resize((nw, nh)), ((cfg["w"] - nw) // 2, (cfg["h"] - nh) // 2))
    return canvas

def fondo_gris_ok(im):
    """None si el fondo sirve (gris claro, aunque tenga algo de degradado: el
    re-encuadre lo aplana). Solo rechaza blanco, color, oscuro o escena."""
    import numpy as np
    im2 = im.copy(); im2.thumbnail((800, 800))
    a = np.asarray(im2.convert("RGB")).astype(np.float32)
    fr = np.concatenate([a[0:6].reshape(-1, 3), a[-6:].reshape(-1, 3),
                         a[:, 0:6].reshape(-1, 3), a[:, -6:].reshape(-1, 3)])
    bg = np.median(fr, 0)
    if bg.min() >= 250: return "fondo blanco"
    if bg.max() - bg.min() > 14: return "fondo con color"
    if bg.mean() < 170: return "fondo oscuro o escena"
    k = 12
    for c in [a[:k, :k], a[:k, -k:], a[-k:, :k], a[-k:, -k:]]:
        if np.sqrt(((c.reshape(-1, 3).mean(0) - bg) ** 2).sum()) > 60:
            return "fondo no uniforme (escena)"
    return None

def pies_cortados(im):
    """True si el sujeto toca el borde inferior de la imagen generada
    (pies cortados por el encuadre de la IA)."""
    import numpy as np
    a = np.asarray(im.convert("RGB")).astype(np.float32)
    h, w, _ = a.shape
    k = max(6, int(min(h, w)*0.02))
    borde = np.concatenate([a[:k].reshape(-1, 3), a[:, :k].reshape(-1, 3), a[:, -k:].reshape(-1, 3)])
    bg = np.median(borde, 0)
    dist = np.sqrt(((a - bg)**2).sum(2))
    return bool((dist[-3:, :] > 45).mean() > 0.02)

_print_lock = threading.Lock()
def log(m):
    with _print_lock: print(m, flush=True)

def _extract_img(resp):
    from PIL import Image as _I
    cand = (resp.candidates or [None])[0]
    if cand is None or not getattr(cand, "content", None):
        raise RuntimeError("sin candidato (posible bloqueo): %s" % getattr(resp, "prompt_feedback", None))
    for part in cand.content.parts:
        inline = getattr(part, "inline_data", None)
        if inline and inline.data:
            return _I.open(BytesIO(inline.data)).convert("RGB")
    raise RuntimeError("la respuesta no traia imagen")

def gen_img(client, model, prompt, imgs, attempts=4):
    last = None
    for attempt in range(1, attempts + 1):
        try:
            return _extract_img(client.models.generate_content(model=model, contents=[prompt] + list(imgs)))
        except Exception as e:
            last = e; msg = str(e).lower()
            wait = (6 if ("429" in msg or "quota" in msg or "resource" in msg or "exhaust" in msg) else 2) * attempt
            time.sleep(wait + random.uniform(0, 1.5))
    raise RuntimeError(str(last))

def slug(s):
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    s = "".join(c if c.isalnum() else "-" for c in s.lower())
    while "--" in s: s = s.replace("--", "-")
    return s.strip("-")

def process_row(client, model, row, webroot, outdir, ancla_path):
    from PIL import Image, ImageFile
    ImageFile.LOAD_TRUNCATED_IMAGES = True
    pid = (row.get("producto_id") or "").strip()
    ruta = (row.get("ruta") or "").strip()
    titulo = (row.get("titulo") or "").strip()
    src = os.path.join(webroot, ruta)
    if not os.path.isfile(src):
        src = ruta
    base = os.path.splitext(os.path.basename(ruta))[0]
    outpath = os.path.join(outdir, "%s_%s%s.png" % (pid, slug(base), SUFIJO))
    if os.path.exists(outpath) and os.path.getsize(outpath) > 0:
        return ("skip", outpath)
    if not os.path.isfile(src):
        return ("error", "%s\t[no encuentro la imagen de referencia: %s]" % (titulo, ruta))
    try:
        ref = Image.open(src); ref.load(); ref = ref.convert("RGB")
    except Exception as e:
        return ("error", "%s\t[referencia corrupta: %s]" % (ruta, e))
    try:
        ancla = Image.open(ancla_path); ancla.load(); ancla = ancla.convert("RGB")
        desc = NINOS[int(pid) % len(NINOS)] if pid.isdigit() else random.choice(NINOS)
        prompt = build_prompt(desc, titulo)
        out, motivo = None, "sin intento"
        for intento in range(3):
            p = prompt if intento == 0 else prompt + REFUERZO
            cand = gen_img(client, model, p, [ancla, ref])
            motivo = fondo_gris_ok(cand)
            if motivo is None and pies_cortados(cand):
                motivo = "pies cortados por el borde inferior"
            if motivo is None:
                out = cand; break
        if out is None:
            rev = os.path.join(outdir, "revisar"); os.makedirs(rev, exist_ok=True)
            cand.save(os.path.join(rev, os.path.basename(outpath)))
            return ("error", "%s\t[%s tras 3 intentos, guardada en revisar/]" % (ruta, motivo))
        if PAD["enabled"]:
            framed, pad_err = None, ""
            try:
                framed = pad_frame_img(out, PAD)
            except Exception as e:
                pad_err = str(e)
            if framed is None:
                try:
                    framed = pad_simple(out, PAD)
                except Exception as e:
                    pad_err = pad_err or str(e)
            if framed is None:
                rev = os.path.join(outdir, "revisar"); os.makedirs(rev, exist_ok=True)
                out.save(os.path.join(rev, os.path.basename(outpath)))
                return ("error", "%s\t[re-encuadre fallido (%s); cruda guardada en revisar/]" % (ruta, pad_err or "caja vacia"))
            out = framed
        out.save(outpath)
        return ("ok", outpath)
    except Exception as e:
        return ("error", "%s\t%s" % (ruta, e))

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", default="salida_unificacion/bolsasmochila.csv")
    ap.add_argument("--webroot", default="web")
    ap.add_argument("--out", default="salida_unificacion/bolsasmochila_espalda")
    ap.add_argument("--model", default="gemini-3-pro-image")  # Nano Banana Pro: mejor texto/caras a la primera
    ap.add_argument("--workers", type=int, default=3)
    ap.add_argument("--max", type=int, default=0)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--cost", type=float, default=0.15)
    ap.add_argument("--no-pad", dest="pad", action="store_false"); ap.set_defaults(pad=True)
    ap.add_argument("--w", type=int, default=1200)
    ap.add_argument("--h", type=int, default=1600)
    ap.add_argument("--margin", type=int, default=150)
    ap.add_argument("--gray", default="#f1f1f0")
    ap.add_argument("--pad-tol", type=int, default=20)
    ap.add_argument("--ejemplo", default="salida_unificacion/mochila_ejemplo.png",
                    help="Imagen ancla de estilo (tu resultado aprobado de Gemini)")
    args = ap.parse_args()
    PAD["enabled"] = args.pad; PAD["gray"] = hex2rgb(args.gray)
    PAD["w"], PAD["h"], PAD["margin"], PAD["tol"] = args.w, args.h, args.margin, args.pad_tol

    with open(args.csv, newline="", encoding="utf-8-sig") as f:
        rows = [r for r in csv.DictReader(f) if (r.get("ruta") or "").strip()]
    if args.max: rows = rows[:args.max]
    os.makedirs(args.out, exist_ok=True)
    def hecho(r):
        base = os.path.splitext(os.path.basename(r["ruta"].strip()))[0]
        p = os.path.join(args.out, "%s_%s%s.png" % ((r.get("producto_id") or "").strip(), slug(base), SUFIJO))
        return os.path.exists(p) and os.path.getsize(p) > 0
    pend = [r for r in rows if not hecho(r)]
    faltan = [r["ruta"] for r in pend if not (os.path.isfile(os.path.join(args.webroot, r["ruta"].strip())) or os.path.isfile(r["ruta"].strip()))]
    log("CSV: %s  ->  %d productos   pendientes: %d   ya hechos: %d" % (args.csv, len(rows), len(pend), len(rows) - len(pend)))
    log("Lienzo: %dx%d (3:4)  margen %d  gris %s   modelo %s" % (PAD["w"], PAD["h"], PAD["margin"], args.gray, args.model))
    if faltan:
        log("AVISO: %d referencias no encontradas bajo '%s': %s%s"
            % (len(faltan), args.webroot, ", ".join(faltan[:5]), "..." if len(faltan) > 5 else ""))
    if not os.path.isfile(args.ejemplo):
        log("ERROR: falta la imagen ancla de estilo: %s" % args.ejemplo)
        log("Guarda ahi tu resultado aprobado (el nino de espaldas de Gemini) y relanza.")
        sys.exit(1)
    log("Coste estimado: unos %.2f USD (%d x %.3f, mas reintentos si el fondo sale mal)" % (len(pend) * args.cost, len(pend), args.cost))
    if args.dry_run: log("--dry-run: fin."); return
    if not pend: log("Nada pendiente."); return
    key = os.environ.get("GEMINI_API_KEY") or os.environ.get("GOOGLE_API_KEY")
    if not key:
        log("ERROR: falta GEMINI_API_KEY (https://aistudio.google.com/apikey)"); sys.exit(1)
    try:
        from google import genai
    except ImportError:
        log("ERROR: pip install google-genai pillow numpy scipy"); sys.exit(1)
    client = genai.Client(api_key=key)
    ok = err = 0; errores = []; t0 = time.time()
    manifest = os.path.join(args.out, "manifest.csv")
    nuevo = not os.path.exists(manifest)
    mf = open(manifest, "a", newline="", encoding="utf-8"); mw = csv.writer(mf)
    if nuevo: mw.writerow(["producto_id", "titulo", "origen", "salida", "estado"])
    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as ex:
        futs = {ex.submit(process_row, client, args.model, r, args.webroot, args.out, args.ejemplo): r for r in pend}
        done = 0
        for fut in as_completed(futs):
            r = futs[fut]
            try:
                st, info = fut.result()
            except Exception as e:
                st, info = "error", "%s\t%s" % (r.get("ruta"), e)
            done += 1
            if st == "ok": ok += 1
            elif st == "error": err += 1; errores.append(info)
            mw.writerow([r.get("producto_id"), r.get("titulo"), r.get("ruta"),
                         info if st == "ok" else "", st]); mf.flush()
            if done % 3 == 0 or done == len(pend):
                log("  %d/%d  ok=%d  error=%d" % (done, len(pend), ok, err))
    mf.close()
    if errores:
        with open(os.path.join(args.out, "errores.log"), "a", encoding="utf-8") as f:
            f.write("\n".join(errores) + "\n")
    log("\nHECHO en %.0fs. ok=%d  error=%d.  Salidas en %s." % (time.time() - t0, ok, err, args.out))
    log("Recomendado: auditar con  auditar_fondos.py --dir %s" % args.out)

if __name__ == "__main__":
    main()
