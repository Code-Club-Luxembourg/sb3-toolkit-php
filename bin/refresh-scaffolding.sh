#!/usr/bin/env bash
#
# Refresh the vendored TurboWarp scaffolding runtime used by the pure-PHP
# `pack` command. Node/npm is required ONLY for this script — never at runtime.
#
# Usage: bin/refresh-scaffolding.sh [version]
#   version  npm version of @turbowarp/packager (default: 3.12.0)
set -euo pipefail

VERSION="${1:-3.12.0}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/resources/scaffolding"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Fetching @turbowarp/packager@$VERSION ..."
( cd "$TMP" && npm pack "@turbowarp/packager@$VERSION" >/dev/null && tar -xzf ./*.tgz )

SRC="$TMP/package/dist/scaffolding"
mkdir -p "$DEST"
cp "$SRC/scaffolding-min.js" "$DEST/"
cp "$SRC/scaffolding-min.js.LICENSE.txt" "$DEST/"

cat > "$DEST/VERSION" <<EOF
@turbowarp/packager scaffolding runtime
source-version: $VERSION
bundle: scaffolding-min.js
license: MPL-2.0 (see scaffolding-min.js.LICENSE.txt)
EOF

echo "Updated $DEST (version $VERSION)."
