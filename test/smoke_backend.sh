#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="8010"
BASE="http://127.0.0.1:${PORT}"

php -S 127.0.0.1:${PORT} "$ROOT/index.php" > "$ROOT/var/log/test-http.log" 2>&1 &
PID=$!
cleanup() {
  kill "$PID" >/dev/null 2>&1 || true
}
trap cleanup EXIT

sleep 1

health_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/health")
[ "$health_code" = "200" ] || { echo "health failed: $health_code"; exit 1; }

auth_json=$(curl -sS -X POST "$BASE/api/auth/login" -H 'Content-Type: application/json' -d '{"name":"ivan","password":"1111"}')
TOKEN=$(php -r '$j=json_decode(stream_get_contents(STDIN), true); echo $j["token"] ?? "";' <<< "$auth_json")
[ -n "$TOKEN" ] || { echo "login token missing"; exit 1; }

doc_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/documents/11" -H "Authorization: Bearer $TOKEN")
[ "$doc_code" = "200" ] || { echo "document read failed: $doc_code"; exit 1; }

sess_json=$(curl -sS -X POST "$BASE/api/sessions" -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" -d '{"documentId":11}')
ws_enabled=$(php -r '$j=json_decode(stream_get_contents(STDIN), true); echo ($j["wsEnabled"] ?? false) ? "1" : "0";' <<< "$sess_json")
[ "$ws_enabled" = "1" ] || { echo "session wsEnabled expected 1"; exit 1; }

save_json=$(curl -sS -X POST "$BASE/api/documents/11/revisions" -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" -d '{"code":"@startuml\nAlice->Bob:Smoke\n@enduml"}')
is_valid=$(php -r '$j=json_decode(stream_get_contents(STDIN), true); echo ($j["isValid"] ?? false) ? "1" : "0";' <<< "$save_json")
[ "$is_valid" = "1" ] || { echo "save expected valid render"; exit 1; }

echo "smoke backend: OK"
