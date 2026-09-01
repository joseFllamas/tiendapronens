#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Genera con Nano Banana una foto de un bebe/nino llevando puesto cada babero
del CSV, sobre fondo gris #f1f1f0 y con el mismo margen que el resto del
catalogo. Lienzo 3:4 vertical (1200x1600), como todo el catalogo.

Lee el CSV de productos (producto_id, titulo, ruta, ...), usa la foto del
producto como referencia y pide a la IA la escena con el babero puesto.
El nino varia (pelo, sexo, rasgos) de forma estable por producto.

Requisitos:  pip install google-genai pillow numpy scipy
             export GEMINI_API_KEY="tu_clave"

Uso (desde la raiz del proyecto):
  python3 scripts/unificacion-imagenes/generar_baberos_bebe.py
  # opcionales:
  #   --csv salida_unificacion/baberos.csv   --webroot web
  #   --out salida_unificacion/baberos_bebe
  #   --model gemini-2.5-flash-image   --workers 3   --max 3   --dry-run
  #   --w 1200 --h 1600 --margin 150 --gray "#f1f1f0"

Reanudable (salta lo ya hecho). Fallos en errores.log; si tras 3 intentos el
fondo no sale gris limpio, guarda el ultimo intento en revisar/.
"""
import os, sys, csv, time, argparse, random, threading, unicodedata
from io import BytesIO
from concurrent.futures import ThreadPoolExecutor, as_completed

SUFIJO = "_bebe"

NINOS = [
    "a baby girl with dark curly hair",
    "a baby boy with straight black hair",
    "a toddler girl with blonde hair",
    "a toddler boy with light brown hair",
    "a baby girl with afro-textured hair",
    "a baby boy with dark skin and short curly hair",
    "a toddler girl with red hair and freckles",
    "a baby boy with East Asian features and straight dark hair",
    "a toddler girl with brown wavy hair",
    "a baby boy with very short blonde hair",
]

def build_prompt(desc):
    return (
        "Using the baby bib shown in this reference product photo, create a photorealistic "
        "studio photograph of %s, around 1 to 2 years old, happily wearing EXACTLY this bib, "
        "sitting upright and facing the camera, visible from the waist up so the whole bib is "
        "clearly seen. Reproduce the bib exactly as in the reference: same shape, same colors, "
        "same print, same characters and same text if any. "
        "Plain flat light-gray #f1f1f0 studio background covering the ENTIRE image edge to edge. "
        "Soft even lighting, no props, no furniture, no text or watermarks, no harsh shadows. "
        "The bib must be fully visible and in focus." % desc
    )

REFUERZO = (" IMPORTANT: the background must be a plain flat light-gray #f1f1f0 studio "
            "backdrop covering the whole image, with nothing else in the scene.")

PAD = {"enabled": True, "gray": (241, 241, 240), "w": 1200, "h": 1600, "margin": 150, "tol": 20}

def hex2rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))

def pad_frame_img(im, cfg):
    """Remapea suavemente el fondo al gris exacto y centra el sujeto con margen."""
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
    import numpy as _np
    ys, xs = _np.where(dist > cfg["tol"])
    if len(xs) < 30:
        return None
    x0, x1, y0, y1 = int(xs.min()), int(xs.max()), int(ys.min()), int(ys.max())
    sub = im2.crop((x0, y0, x1 + 1, y1 + 1))
    IW, IH = cfg["w"] - 2 * cfg["margin"], cfg["h"] - 2 * cfg["margin"]
    s = min(IW / sub.width, IH / sub.height, 3.0)
    nw, nh = max(1, int(sub.width * s)), max(1, int(sub.height * s))
    sub = sub.resize((nw, nh))
    canvas = _Image.new("RGB", (cfg["w"], cfg["h"]), cfg["gray"])
    canvas.paste(sub, ((cfg["w"] - nw) // 2, (cfg["h"] - nh) // 2))
    return canvas

def pad_simple(im, cfg):
    return pad_frame_img(im, cfg)

def fondo_gris_ok(im):
    """None si el fondo es un gris claro uniforme; si no, el motivo."""
    import numpy as np
    im2 = im.copy(); im2.thumbnail((800, 800))
    a = np.asarray(im2.convert("RGB")).astype(np.float32)
    fr = np.concatenate([a[0:6].reshape(-1, 3), a[-6:].reshape(-1, 3),
                         a[:, 0:6].reshape(-1, 3), a[:, -6:].reshape(-1, 3)])
    bg = np.median(fr, 0)
    if bg.min() >= 250: return "fondo blanco"
    if bg.max() - bg.min() > 14: return "fondo con color"
    if bg.mean() < 190: return "fondo oscuro o escena"
    k = 12
    for c in [a[:k, :k], a[:k, -k:], a[-k:, :k], a[-k:, -k:]]:
        if np.sqrt(((c.reshape(-1, 3).mean(0) - bg) ** 2).sum()) > 30:
            return "fondo no uniforme (escena)"
    return None

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

def gen_img(client, model, prompt, img, attempts=4):
    last = None
    for attempt in range(1, attempts + 1):
        try:
            return _extract_img(client.models.generate_content(model=model, contents=[prompt, img]))
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

def process_row(client, model, row, webroot, outdir):
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
        desc = NINOS[int(pid) % len(NINOS)] if pid.isdigit() else random.choice(NINOS)
        prompt = build_prompt(desc)
        out, motivo = None, "sin intento"
        for intento in range(3):
            p = prompt if intento == 0 else prompt + REFUERZO
            cand = gen_img(client, model, p, ref)
            motivo = fondo_gris_ok(cand)
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
    ap.add_argument("--csv", default="salida_unificacion/baberos.csv")
    ap.add_argument("--webroot", default="web")
    ap.add_argument("--out", default="salida_unificacion/baberos_bebe")
    ap.add_argument("--model", default="gemini-2.5-flash-image")
    ap.add_argument("--workers", type=int, default=3)
    ap.add_argument("--max", type=int, default=0)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--cost", type=float, default=0.039)
    ap.add_argument("--no-pad", dest="pad", action="store_false"); ap.set_defaults(pad=True)
    ap.add_argument("--w", type=int, default=1200)
    ap.add_argument("--h", type=int, default=1600)
    ap.add_argument("--margin", type=int, default=150)
    ap.add_argument("--gray", default="#f1f1f0")
    ap.add_argument("--pad-tol", type=int, default=20)
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
        log("AVISO: %d referencias no encontradas bajo '%s' (se anotaran como error): %s%s"
            % (len(faltan), args.webroot, ", ".join(faltan[:5]), "..." if len(faltan) > 5 else ""))
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
        futs = {ex.submit(process_row, client, args.model, r, args.webroot, args.out): r for r in pend}
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
            if done % 5 == 0 or done == len(pend):
                log("  %d/%d  ok=%d  error=%d" % (done, len(pend), ok, err))
    mf.close()
    if errores:
        with open(os.path.join(args.out, "errores.log"), "a", encoding="utf-8") as f:
            f.write("\n".join(errores) + "\n")
    log("\nHECHO en %.0fs. ok=%d  error=%d.  Salidas en %s (manifest.csv incluido)." % (time.time() - t0, ok, err, args.out))

if __name__ == "__main__":
    main()
