#!/usr/bin/env bash
# Zip del plugin pronto da caricare (Plugin → Aggiungi nuovo → Carica plugin): build/wvoce-iscrizioni.zip
# con dentro la cartella wvoce-iscrizioni. Contiene l'ultimo commit (le modifiche non salvate restano
# fuori); i file di sviluppo sono esclusi da .gitattributes.
set -euo pipefail

cd "$(dirname "$0")/.."
nome=wvoce-iscrizioni
versione=$(git show HEAD:wvoce-iscrizioni.php | sed -n 's/^ \* Version: *//p' | tr -d '\r')

if ! git diff --quiet HEAD -- . ':!build'; then
	echo "Attenzione: ci sono modifiche non salvate in un commit, nello zip non entrano." >&2
fi

mkdir -p build
git archive --format=zip --prefix="$nome/" -o "build/$nome.zip" HEAD
echo "build/$nome.zip (versione $versione)"
