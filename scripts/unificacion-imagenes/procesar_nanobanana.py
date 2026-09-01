#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Procesa en lote una carpeta con Nano Banana (API de imagen de Gemini).
Aplica el mismo prompt a cada imagen y guarda el resultado. No vas una a una.

Pensado para la carpeta 'a_nanobanana/' que genera unificar_imagenes.py.
Despues, vuelve a pasar la carpeta de salida por unificar_imagenes.py para
dejar el encuadre 3:4 identico al resto.

Requisitos:
  pip install google-genai pillow
  API key gratuita/pago en https://aistudio.google.com/apikey
  export GEMINI_API_KEY="tu_clave"      (en Windows: setx GEMINI_API_KEY "tu_clave")

Uso:
  python3 procesar_nanobanana.py --in a_nanobanana --out nb_salida
  # opcionales:
  #   --model gemini-2.5-flash-image   (por defecto; el mas barato, sobra para fondo)
  #                gemini-3.1-flash-image  = Nano Banana 2
  #                gemini-3-pro-image      = Nano Banana Pro (mejor con texto/bordados, mas caro)
  #   --workers 3     llamadas en paralelo (bajalo si te limita la cuota)
  #   --max 20        procesa solo N (para una prueba antes de tirar toda la carpeta)
  #   --dry-run       no llama a la API: solo cuenta y estima coste
  #   --cost 0.039    coste por imagen en USD para la estimacion

Reanudable: si ya existe la salida de una imagen, la salta. Puedes cortar y
relanzar sin repetir trabajo. Los fallos se anotan en errores.log.
"""
import os, sys, time, argparse, random, threading
from io import BytesIO
from concurrent.futures import ThreadPoolExecutor, as_completed

EXTS = (".jpg", ".jpeg", ".png", ".webp", ".JPG", ".JPEG", ".PNG", ".WEBP")

DEFAULT_PROMPT = (
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

# Pase correctivo cuando la salida aun conserva el fondo blanco original
FIX_PROMPT = (
    "The background of this image is still wrong: the original photo is visible as a white "
    "rectangle (or the background is still white). Repaint the ENTIRE background as one uniform "
    "flat light gray #f1f1f0, edge to edge, removing any white rectangle, frame or border. "
    "Keep the product exactly where it is, preserving every fold, print and detail, and make "
    "the product itself clean bright white so it clearly stands out against the gray. "
    "No new shadows or reflections."
)

# Receta por fases para blanco-sobre-blanco dificil (se aplica si el pase correctivo no basta):
# 1) oscurecer el objeto  2) fondo a gris  3) re-aclarar el objeto a blanco
PHASED_PROMPTS = [
    "Step 1 of 3: Recolor the main product in this photo to a clearly visible medium gray "
    "(around #a8a8a8), keeping every fold, seam, print and detail intact. "
    "Do not change the white background yet.",
    "Step 2 of 3: Now replace the ENTIRE background with one uniform flat light gray #f1f1f0, "
    "edge to edge, so no white background remains anywhere. Keep the medium-gray product "
    "exactly unchanged.",
    "Step 3 of 3: Now recolor the product to a clean bright white, clearly brighter than the "
    "#f1f1f0 gray background, keeping every fold, seam and detail. "
    "Keep the gray background exactly unchanged.",
]

FIX = {"enabled": True}

# --- Re-encuadre (padding) integrado: la salida de Nano Banana ya sale con el
# mismo margen 3:4 que la version no-IA. No recorta el producto: detecta donde
# esta sobre el gris y lo recoloca centrado. Se puede desactivar con --no-pad. ---
PAD = {"enabled": True, "gray": (241, 241, 240), "w": 1200, "h": 1600, "margin": 150, "tol": 20}

def hex2rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))

def pad_frame_img(im, cfg):
    """Re-encuadra la salida de la IA. Primero remapea SUAVEMENTE el fondo al
    gris exacto (los pixeles cercanos al color de fondo se desplazan hacia
    #f1f1f0, el producto queda intacto), y despues centra el producto en el
    lienzo con el margen. Sin costuras y sin comerse tejidos claros."""
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
    sub = sub.resize((nw, nh))
    canvas = _Image.new("RGB", (cfg["w"], cfg["h"]), cfg["gray"])
    canvas.paste(sub, ((cfg["w"] - nw) // 2, (cfg["h"] - nh) // 2))
    return canvas

_print_lock = threading.Lock()
def log(msg):
    with _print_lock:
        print(msg, flush=True)

def fondo_mal(im, gray):
    """Detecta el fallo tipico de blanco-sobre-blanco: la salida conserva la foto
    original como rectangulo blanco, o el fondo no llego a cambiarse al gris.
    Devuelve el motivo (str) si esta mal, o None si el fondo es correcto."""
    import numpy as np
    from scipy import ndimage
    im2 = im.copy(); im2.thumbnail((1000, 1000))
    a = np.asarray(im2.convert("RGB")).astype(np.int16)
    h, w, _ = a.shape
    fr = np.concatenate([a[0:6].reshape(-1, 3), a[-6:].reshape(-1, 3),
                         a[:, 0:6].reshape(-1, 3), a[:, -6:].reshape(-1, 3)])
    bg = np.median(fr, 0)
    # blanco = fallo (fondo sin cambiar); un gris claro cualquiera vale, porque
    # el re-encuadre lo remapea despues al gris exacto
    if int(bg.min()) >= 250:
        return "el fondo sigue blanco (sin cambiar)"
    thr = max(int(bg.min()) + 4, 244)
    white = (a.min(axis=2) >= thr)
    if white.mean() < 0.10:
        return None
    lbl, n = ndimage.label(white)
    if n == 0:
        return None
    sizes = ndimage.sum(white, lbl, np.arange(1, n + 1))
    comp = (lbl == (int(sizes.argmax()) + 1))
    ys, xs = np.where(comp)
    x0, x1, y0, y1 = xs.min(), xs.max(), ys.min(), ys.max()
    if (x1 - x0 + 1) * (y1 - y0 + 1) < 0.20 * w * h:
        return None
    # un rectangulo de fondo original llena los 4 lados de su caja; un producto no
    per = (comp[y0, x0:x1 + 1].mean() + comp[y1, x0:x1 + 1].mean() +
           comp[y0:y1 + 1, x0].mean() + comp[y0:y1 + 1, x1].mean()) / 4.0
    if per > 0.70:
        return "sigue visible el rectangulo blanco del original"
    return None

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

def gen_img(client, model, prompt, img, attempts=5):
    """Una llamada de edicion a Nano Banana con reintentos. Devuelve PIL Image."""
    last = None
    for attempt in range(1, attempts + 1):
        try:
            return _extract_img(client.models.generate_content(model=model, contents=[prompt, img]))
        except Exception as e:
            last = e
            msg = str(e).lower()
            wait = (6 if ("429" in msg or "quota" in msg or "resource" in msg or "exhaust" in msg) else 2) * attempt
            time.sleep(wait + random.uniform(0, 1.5))
    raise RuntimeError(str(last))

def read_prompt(args):
    if args.prompt_file and os.path.isfile(args.prompt_file):
        return open(args.prompt_file, encoding="utf-8").read().strip()
    # si existe PROMPT_nanobanana.txt junto a la carpeta de entrada, usalo
    guess = os.path.join(os.path.dirname(os.path.abspath(args.inp)), "PROMPT_nanobanana.txt")
    if os.path.isfile(guess):
        return open(guess, encoding="utf-8").read().strip()
    return DEFAULT_PROMPT

def list_inputs(inp):
    out = []
    for dp, _, ns in os.walk(inp):
        for n in ns:
            if n.endswith(EXTS):
                out.append(os.path.join(dp, n))
    out.sort()
    return out

def process_one(client, model, prompt, path, outdir):
    from PIL import Image, ImageFile
    ImageFile.LOAD_TRUNCATED_IMAGES = True  # tolera imagenes ligeramente truncadas
    base = os.path.splitext(os.path.basename(path))[0]
    outpath = os.path.join(outdir, base + ".png")
    if os.path.exists(outpath) and os.path.getsize(outpath) > 0:
        return ("skip", path)
    # cargar la imagen de forma segura: si esta corrupta, se anota y se salta
    try:
        img = Image.open(path)
        img.load()
        img = img.convert("RGB")
    except Exception as e:
        return ("error", "%s\t[imagen corrupta o ilegible, se salta] %s" % (path, e))
    try:
        out = gen_img(client, model, prompt, img)
        note = ""
        if FIX["enabled"]:
            motivo = fondo_mal(out, PAD["gray"])
            if motivo:
                # pase correctivo unico sobre la salida fallida
                out2 = gen_img(client, model, FIX_PROMPT, out)
                if fondo_mal(out2, PAD["gray"]) is None:
                    out, note = out2, "arreglada con 1 pase extra"
                else:
                    # receta por fases desde el ORIGINAL: oscurecer -> fondo gris -> re-aclarar
                    cur = img
                    for fase in PHASED_PROMPTS:
                        cur = gen_img(client, model, fase, cur)
                    if fondo_mal(cur, PAD["gray"]) is None:
                        out, note = cur, "arreglada por fases"
                    else:
                        rev = os.path.join(outdir, "revisar")
                        os.makedirs(rev, exist_ok=True)
                        cur.save(os.path.join(rev, base + ".png"))
                        return ("error", "%s\t[%s; la IA no logro el fondo tras %d pases extra, guardada en revisar/]"
                                % (path, motivo, 1 + len(PHASED_PROMPTS)))
        if PAD["enabled"]:
            try:
                framed = pad_frame_img(out, PAD)
            except Exception:
                framed = None
            if framed is not None:
                out = framed
        out.save(outpath)
        return ("ok", path if not note else "%s  [%s]" % (path, note))
    except Exception as e:
        return ("error", "%s\t%s" % (path, e))

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="inp", required=True)
    ap.add_argument("--out", dest="out", required=True)
    ap.add_argument("--model", default="gemini-2.5-flash-image")
    ap.add_argument("--prompt-file", default=None)
    ap.add_argument("--workers", type=int, default=3)
    ap.add_argument("--max", type=int, default=0)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--cost", type=float, default=0.039, help="USD por imagen (estimacion)")
    ap.add_argument("--no-pad", dest="pad", action="store_false",
                    help="No re-encuadrar: guarda la salida cruda de Nano Banana, sin margenes")
    ap.set_defaults(pad=True)
    ap.add_argument("--no-fix", dest="fix", action="store_false",
                    help="Desactiva la deteccion y correccion automatica del fallo blanco-sobre-blanco")
    ap.set_defaults(fix=True)
    ap.add_argument("--w", type=int, default=1200)
    ap.add_argument("--h", type=int, default=1600)
    ap.add_argument("--margin", type=int, default=150)
    ap.add_argument("--gray", default="#f1f1f0")
    ap.add_argument("--pad-tol", type=int, default=20)
    args = ap.parse_args()
    PAD["enabled"] = args.pad
    PAD["gray"] = hex2rgb(args.gray)
    PAD["w"], PAD["h"], PAD["margin"], PAD["tol"] = args.w, args.h, args.margin, args.pad_tol
    FIX["enabled"] = args.fix

    files = list_inputs(args.inp)
    if args.max:
        files = files[:args.max]
    os.makedirs(args.out, exist_ok=True)
    def _done(f):
        op = os.path.join(args.out, os.path.splitext(os.path.basename(f))[0] + ".png")
        return os.path.exists(op) and os.path.getsize(op) > 0
    pending = [f for f in files if not _done(f)]
    prompt = read_prompt(args)

    log("Carpeta: %s" % args.inp)
    log("Imagenes totales: %d   pendientes: %d   ya hechas: %d" % (len(files), len(pending), len(files)-len(pending)))
    log("Modelo: %s" % args.model)
    log("Re-encuadre (padding): %s" % ("ON  ->  salida %dx%d, margen %d" % (PAD["w"], PAD["h"], PAD["margin"]) if PAD["enabled"] else "OFF (salida cruda)"))
    log("Correccion blanco-sobre-blanco: %s" % ("ON (detecta fondo mal hecho y reintenta: 1 pase extra y luego 3 fases)" if FIX["enabled"] else "OFF"))
    log("Coste estimado de esta pasada: unos %.2f USD (%d x %.3f)" % (len(pending)*args.cost, len(pending), args.cost))
    if args.dry_run:
        log("--dry-run: no se llama a la API. Fin.")
        return
    if not pending:
        log("Nada pendiente. Fin.")
        return

    key = os.environ.get("GEMINI_API_KEY") or os.environ.get("GOOGLE_API_KEY")
    if not key:
        log("ERROR: falta la variable GEMINI_API_KEY. Consiguela en https://aistudio.google.com/apikey")
        sys.exit(1)
    try:
        from google import genai
    except ImportError:
        log("ERROR: falta el SDK. Instala con:  pip install google-genai pillow")
        sys.exit(1)
    client = genai.Client(api_key=key)

    ok = err = 0
    errors = []
    t0 = time.time()
    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as ex:
        futs = {ex.submit(process_one, client, args.model, prompt, p, args.out): p for p in pending}
        done = 0
        for fut in as_completed(futs):
            try:
                status, info = fut.result()
            except Exception as e:
                status, info = ("error", "excepcion no controlada: %s" % e)
            done += 1
            if status == "ok": ok += 1
            elif status == "error":
                err += 1; errors.append(info)
            if done % 10 == 0 or done == len(pending):
                log("  %d/%d  ok=%d  error=%d" % (done, len(pending), ok, err))
    if errors:
        with open(os.path.join(args.out, "errores.log"), "w", encoding="utf-8") as f:
            f.write("\n".join(errors) + "\n")
    dt = time.time() - t0
    log("\nHECHO en %.0fs. ok=%d  error=%d  (errores en %s/errores.log)" % (dt, ok, err, args.out))
    if PAD["enabled"]:
        log("Las imagenes ya salen re-encuadradas a %dx%d con margen %d, listas para subir." % (PAD["w"], PAD["h"], PAD["margin"]))
    else:
        log("Salida cruda. Para el encuadre 3:4: unificar_imagenes.py --mode pad --in '%s' --out CARPETA_FINAL" % args.out)

if __name__ == "__main__":
    main()
