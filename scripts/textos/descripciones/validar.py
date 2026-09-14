#!/usr/bin/env python3
"""Valida las descripciones nuevas antes de importarlas.

Uso:
  python3 scripts/textos/descripciones/validar.py <export.json> <lote.json> [<lote.json>...]
  python3 scripts/textos/descripciones/validar.py <export.json> --csv salida.csv <lote.json>...

Comprueba, por producto: HTML permitido, longitud, primera frase (meta description) entre
90 y 155 caracteres, palabras y datos prohibidos (composición, lavado, envío, precio,
franquicias), y solapamiento de frases entre productos. Con --csv escribe el CSV final
(id;titulo;body) con todo lo que pasa.
"""

import csv
import html
import json
import re
import sys
from collections import Counter, defaultdict

PERMITIDAS = {'p', 'strong', 'em', 'ul', 'ol', 'li', 'h3', 'br'}

PROHIBIDAS = [
    (r'\b\d{2,3}\s?%', 'porcentaje de composición'),
    (r'\b\d{2,4}\s?(g|gr|grs|g/m2|g/m²)\b', 'gramaje'),
    (r'\blavad(?:o|ora|os|ar)\b(?!.{0,20}\b(25|30)\b)', 'instrucciones de lavado'),
    (r'\blej[ií]a\b|\bsecadora\b|\bplancha(?:r|do)?\b', 'cuidados (van en el desplegable)'),
    (r'\b\d{2}\s?[ºo°]\s?C?\b(?!.{0,30}\b25\b)', 'temperatura'),
    (r'\benv[ií]o\b|\bentrega\b|\b24/48\b|\b3-5 d[ií]as\b|\b72\s?h', 'envío o plazos'),
    (r'\bdevoluci', 'devoluciones'),
    (r'\b\d+([,.]\d+)?\s?€|\beuros?\b|\bgratis\b|\bsin coste\b|\brecargo de\b', 'precio'),
    (r'\bPRONENS\b', 'marca en mayúsculas'),
    (r'\bhech[oa]s? a mano\b|\bbordad[oa]s? a mano\b', 'a mano'),
    (r'\bcovid|\bpandemia|\bcansinovirus|\breapertura', 'pandemia'),
    (r'\bsum[ée]rgete\b|\bdescubre\b|\bimprescindible\b|\bmust have\b|\bcompañer[oa] (perfect|ideal)|'
     r'\bno lo pienses m[aá]s\b|\bhoy mismo\b|\bde la mejor calidad\b|\bm[aá]xima calidad\b|\balta calidad\b',
     'muletilla prohibida'),
    (r'\bDisney\b|\bMarvel\b|\bNintendo\b|\bNickelodeon\b|\bPeppa Pig\b|\bPaw Patrol\b|\bPatrulla Canina\b|'
     r'\bStar Wars\b|\bLucasfilm\b|\bDC Comics\b|\bWarner\b|\bHasbro\b|\bMattel\b|\bPixar\b|\bSega\b|\bCapcom\b|'
     r'\bStreet Fighter\b|\bZelda\b|\bHalo\b|\bBatman\b|\bSuperman\b|\bJuego de Tronos\b|\bGame of Thrones\b|\bHBO\b',
     'franquicia o marca ajena'),
    (r'\blicencia\b', 'licencia'),
    (r'\bComprar\b', 'Comprar'),
    (r'\.\.\.|…', 'puntos suspensivos'),
    (r'[\U0001F300-\U0001FAFF☀-➿]', 'emoji'),
]

# Franquicias permitidas solo en el lote del merch (Dragon Ball va en los títulos).
PERMITIDAS_MERCH = {'Dragon Ball'}


def texto_plano(cuerpo: str) -> str:
    t = re.sub(r'<br\s*/?>', ' ', cuerpo)
    t = re.sub(r'</(p|li|h3|ul|ol)>', ' ', t)
    t = html.unescape(re.sub(r'<[^>]+>', '', t))
    return re.sub(r'\s+', ' ', t).strip()


def primera_frase(plano: str) -> str:
    m = re.search(r'^(.+?[.!?])(?=\s|$)', plano)
    return m.group(1) if m else plano


def frases(plano: str):
    for s in re.split(r'(?<=[.!?])\s+', plano):
        s = s.strip()
        if len(s.split()) >= 8:
            yield re.sub(r'[^\wáéíóúüñ ]', '', s.lower())


def main(argv):
    if len(argv) < 3:
        print(__doc__)
        return 2
    export = {p['id']: p for p in json.load(open(argv[1], encoding='utf-8'))}
    salida_csv = None
    ficheros = []
    i = 2
    while i < len(argv):
        if argv[i] == '--csv':
            salida_csv = argv[i + 1]
            i += 2
        else:
            ficheros.append(argv[i])
            i += 1

    textos = {}
    problemas = defaultdict(list)
    for f in ficheros:
        es_merch = 'merch' in f
        for fila in json.load(open(f, encoding='utf-8')):
            pid = int(fila['id'])
            cuerpo = (fila.get('body') or '').strip()
            if pid in textos:
                problemas[pid].append('id repetido en dos lotes')
            textos[pid] = (fila.get('titulo', ''), cuerpo, f)
            if pid not in export:
                problemas[pid].append('id que no existe en el export')
                continue
            if export[pid]['titulo'] != fila.get('titulo'):
                problemas[pid].append(f"título distinto: {fila.get('titulo')!r} vs {export[pid]['titulo']!r}")
            etiquetas = set(re.findall(r'</?([a-zA-Z0-9]+)', cuerpo))
            raras = etiquetas - PERMITIDAS
            if raras:
                problemas[pid].append(f'etiquetas no permitidas: {sorted(raras)}')
            if re.search(r'<[a-z]+\s+[a-z-]+=', cuerpo):
                problemas[pid].append('atributos HTML')
            if '\n' in cuerpo:
                problemas[pid].append('salto de línea dentro del body')
            if not cuerpo.startswith('<p>'):
                problemas[pid].append('no empieza con <p>')
            plano = texto_plano(cuerpo)
            palabras = len(plano.split())
            uniforme = not export[pid]['categorias']
            minimo, maximo = (45, 110) if uniforme else (100, 200)
            if not (minimo <= palabras <= maximo):
                problemas[pid].append(f'{palabras} palabras (esperado {minimo}-{maximo})')
            f1 = primera_frase(plano)
            if not (90 <= len(f1) <= 158):
                problemas[pid].append(f'primera frase de {len(f1)} caracteres: {f1[:80]!r}')
            if re.match(r'^(Este|Esta|Estos|Estas|Nuestr|Original|Divertid|Precios)', f1):
                problemas[pid].append(f'primera frase que no se sostiene sola: {f1[:60]!r}')
            if plano.count('!') > 1:
                problemas[pid].append(f"{plano.count('!')} exclamaciones")
            for patron, motivo in PROHIBIDAS:
                # La marca en mayúsculas se busca distinguiendo caja: "Pronens" es correcto.
                m = re.search(patron, plano, 0 if motivo == 'marca en mayúsculas' else re.I)
                if m:
                    if motivo == 'franquicia o marca ajena' and es_merch and m.group(0) in PERMITIDAS_MERCH:
                        continue
                    problemas[pid].append(f'{motivo}: {m.group(0)!r}')
            fuertes = len(re.findall(r'<strong>', cuerpo))
            if fuertes == 0 and not uniforme:
                problemas[pid].append('sin ninguna negrita')
            if fuertes > 6:
                problemas[pid].append(f'{fuertes} negritas')
            p = export[pid]
            menciona_bordado = re.search(r'bordad|nombre|inicial', plano, re.I)
            if not p['personalizable'] and menciona_bordado and not re.search(r'\binicial(?:es)?\b', p['titulo'], re.I):
                if re.search(r'bordad', plano, re.I):
                    problemas[pid].append('habla de bordado en un producto no personalizable')
            if p['modo'] == 'inicial' and re.search(r'dos iniciales|2 iniciales', plano, re.I):
                problemas[pid].append('promete dos iniciales')
            if p['modo'] == 'texto' and re.search(r'elige el color del hilo|color del hilo a elegir', plano, re.I):
                problemas[pid].append('promete elegir el color del hilo')
            if p['fondos'] and 'nube' not in plano.lower():
                problemas[pid].append('tiene nube y no la menciona')
            if not p['fondos'] and p['modo'] == 'texto' and 'nube' in plano.lower():
                problemas[pid].append('menciona nube sin tenerla')

    # Solapamiento entre productos: ninguna frase de 8+ palabras en dos productos.
    indice = defaultdict(set)
    for pid, (_, cuerpo, _) in textos.items():
        for s in frases(texto_plano(cuerpo)):
            indice[s].add(pid)
    for s, ids in indice.items():
        if len(ids) > 1:
            for pid in ids:
                problemas[pid].append(f'frase repetida en {sorted(ids)}: {s[:70]!r}')
    # Arranques iguales (primeras seis palabras).
    arranques = defaultdict(set)
    for pid, (_, cuerpo, _) in textos.items():
        arranques[' '.join(texto_plano(cuerpo).lower().split()[:6])].add(pid)
    for a, ids in arranques.items():
        if len(ids) > 1:
            for pid in ids:
                problemas[pid].append(f'mismo arranque que {sorted(ids - {pid})}: {a!r}')
    # Similitud por 5-gramas entre pares (Jaccard > 0.35 se marca).
    grams = {}
    for pid, (_, cuerpo, _) in textos.items():
        w = re.sub(r'[^\wáéíóúüñ ]', '', texto_plano(cuerpo).lower()).split()
        grams[pid] = {' '.join(w[i:i + 5]) for i in range(max(0, len(w) - 4))}
    ids = sorted(grams)
    for a in range(len(ids)):
        for b in range(a + 1, len(ids)):
            ga, gb = grams[ids[a]], grams[ids[b]]
            if not ga or not gb:
                continue
            j = len(ga & gb) / len(ga | gb)
            if j > 0.35:
                problemas[ids[a]].append(f'demasiado parecido a {ids[b]} (jaccard {j:.2f})')
                problemas[ids[b]].append(f'demasiado parecido a {ids[a]} (jaccard {j:.2f})')

    faltan = sorted(set(export) - set(textos))
    print(f'{len(textos)} textos leídos; {len(faltan)} productos del export sin texto')
    if faltan and len(faltan) < 40:
        print('  faltan:', faltan)
    malos = {k: v for k, v in problemas.items() if v}
    for pid in sorted(malos):
        print(f'\n[{pid}] {textos.get(pid, ("?",))[0]}')
        for m in malos[pid]:
            print('   -', m)
    print(f'\n{len(malos)} productos con avisos, {len(textos) - len(malos)} limpios')

    if salida_csv:
        with open(salida_csv, 'w', encoding='utf-8', newline='') as fh:
            w = csv.writer(fh, delimiter=';', quoting=csv.QUOTE_ALL)
            w.writerow(['id', 'titulo', 'body'])
            for pid in sorted(textos):
                if pid in malos:
                    continue
                w.writerow([pid, textos[pid][0], textos[pid][1]])
        print(f'CSV escrito: {salida_csv} ({len(textos) - len(malos)} filas)')
    return 1 if malos else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv))
