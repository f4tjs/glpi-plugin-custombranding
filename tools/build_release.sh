#!/bin/sh
# Gera dist/custombranding-<versão>.zip pronto para extrair em <glpi>/plugins/.
# Inclui apenas o necessário em produção (sem tools/, docs/, configs de dev).
# Uso (na raiz do plugin):  sh tools/build_release.sh
set -eu

cd "$(dirname "$0")/.."
VERSION=$(sed -n "s/.*PLUGIN_CUSTOMBRANDING_VERSION', '\([^']*\)'.*/\1/p" setup.php)
DIST=dist
STAGE="$DIST/custombranding"

# Garante traduções compiladas e atualizadas.
if command -v msgfmt >/dev/null 2>&1; then
    for po in locales/*.po; do msgfmt --check -o "${po%.po}.mo" "$po"; done
fi

rm -rf "$STAGE" "$DIST/custombranding-$VERSION.zip"
mkdir -p "$STAGE"
cp -R setup.php hook.php plugin.xml composer.json LICENSE README.md README.en.md CHANGELOG.md SECURITY.md src templates public locales "$STAGE/"
rm -f "$STAGE"/locales/*.pot

(cd "$DIST" && zip -qr "custombranding-$VERSION.zip" custombranding)
rm -rf "$STAGE"
echo "OK: $DIST/custombranding-$VERSION.zip"
