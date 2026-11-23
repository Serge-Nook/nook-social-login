#!/usr/bin/env bash
# Builds an installable plugin archive: build/nook-social-login-<version>.zip
set -euo pipefail
cd "$(dirname "$0")/.."
version=$(sed -n 's/^ \* Version: *//p' nook-social-login.php | head -1)
rm -rf build && mkdir -p build/nook-social-login
cp -r nook-social-login.php uninstall.php readme.txt includes assets languages build/nook-social-login/
(cd build && zip -qr "nook-social-login-${version}.zip" nook-social-login)
echo "build/nook-social-login-${version}.zip"
