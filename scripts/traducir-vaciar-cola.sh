#!/usr/bin/env bash
# Vacía la cola de traducción de ai_tmgmt sobreviviendo a los límites de la API.
#
# Por qué hace falta (2026-09-14): cuando OpenAI contesta con un 429, el worker
# guarda en State (ai_tmgmt.queue.suspend_until) el momento hasta el que la cola
# queda suspendida, y a partir de ahí CUALQUIER item lanza SuspendQueueException,
# que aborta la ejecución entera de `drush queue:run`. O sea que un solo 429 mata
# el comando aunque la pausa sea de 60 segundos. Este bucle lo relanza.
#
# Uso, desde la raíz del proyecto (donde está vendor/):
#   bash scripts/traducir-vaciar-cola.sh              # con el drush del vendor
#   DRUSH="ddev drush" bash scripts/traducir-vaciar-cola.sh
#   PAUSA=90 bash scripts/traducir-vaciar-cola.sh     # espera entre pasadas
#
# Se para solo cuando la cola llega a cero. Ctrl-C en cualquier momento: lo que
# ya está traducido se queda guardado y el resto sigue en la cola.

set -u
DRUSH="${DRUSH:-vendor/drush/drush/drush}"
PAUSA="${PAUSA:-60}"
LIMITE="${LIMITE:-600}"

pendientes() {
  $DRUSH php:eval 'print \Drupal::queue("ai_translator_worker")->numberOfItems();' 2>/dev/null | tr -dc '0-9'
}

anterior=$(pendientes)
[ -z "$anterior" ] && { echo "No consigo leer la cola: ¿es correcto DRUSH=$DRUSH?"; exit 1; }
echo "$(date +%H:%M:%S)  quedan $anterior trozos"
vuelta=0

while [ "${anterior:-0}" -gt 0 ]; do
  vuelta=$((vuelta + 1))
  $DRUSH queue:run ai_translator_worker --time-limit="$LIMITE" >/dev/null 2>&1

  ahora=$(pendientes)
  [ -z "$ahora" ] && ahora=$anterior
  hechos=$((anterior - ahora))
  echo "$(date +%H:%M:%S)  pasada $vuelta: $hechos trozos, quedan $ahora"

  # Un item que falla las tres veces marca su entidad como abortada; eso no lo
  # arregla esperar, así que se avisa en cuanto aparece la primera.
  abortados=$($DRUSH php:eval 'print (int) \Drupal::database()->query("SELECT COUNT(*) FROM {tmgmt_job_item} ji INNER JOIN {tmgmt_job} j ON j.tjid = ji.tjid WHERE j.label LIKE :l AND ji.state = :s", [":l" => "Descripciones %", ":s" => 4])->fetchField();' 2>/dev/null | tr -dc '0-9')
  if [ "${abortados:-0}" -gt 0 ]; then
    echo "  AVISO: $abortados entidades abortadas (fallaron los tres intentos). Mira el registro:"
    echo "    $DRUSH ws --type=ai_tmgmt --count=5"
  fi

  if [ "$ahora" -eq 0 ]; then
    break
  fi
  if [ "$hechos" -eq 0 ]; then
    echo "  sin avance (límite de la API activo): espero ${PAUSA}s"
  fi
  anterior=$ahora
  sleep "$PAUSA"
done

echo "$(date +%H:%M:%S)  cola vacía"
echo "Ahora: drush php:script scripts/descripciones-traducir.php -- --estado"
echo "y si no queda nada en curso:  -- --apagar"
