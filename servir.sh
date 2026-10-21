#!/usr/bin/env bash
# Levanta la app en tu computadora con el servidor que trae PHP.
#   ./servir.sh          -> http://localhost:8190/
#   ./servir.sh 8080     -> otro puerto
# Para que la vean otros dispositivos de tu red (por ejemplo, el celular que
# hace de escáner en la puerta), usá HOST=0.0.0.0 ./servir.sh
cd "$(dirname "$0")"
PHP="${PHP:-php}"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec "$PHP" -d display_errors=0 -d log_errors=1 -S "${HOST:-127.0.0.1}:${1:-8190}" -t app app/index.php
