#!/usr/bin/env bash
# Nasadí aktuálny stav pluginu na GitHub.
# Použitie: ./scripts/deploy.sh "sprava commitu"

set -euo pipefail

cd "$(dirname "$0")/.."

MESSAGE="${1:-}"

if [ -z "$MESSAGE" ]; then
  echo "Použitie: $0 \"sprava commitu\""
  exit 1
fi

git add -A

if git diff --cached --quiet; then
  echo "Nie su ziadne zmeny na commitnutie."
  exit 0
fi

git commit -m "$MESSAGE"
git push origin main

echo "Hotovo: $MESSAGE"