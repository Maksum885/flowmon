#!/bin/sh
cd /opt/flowmon && exec docker compose -f compose.prod.yaml -f compose.vps.yaml --env-file .env.production "$@"