#!/usr/bin/env bash
#
# Build the demo instance for the CI e2e job: native MariaDB creds + a localhost BASE_URL, then a
# GraphicsMagick GFX config so the *local* image processor (the v1 default — no imgproxy in CI) can
# materialise srcset candidates. Reuses the env-parameterised Build/Scripts/setup-demo-instance.sh.
#
# Run from the project root:
#     bash Build/Scripts/setup-ci-instance.sh
#
set -euo pipefail

export BASE_URL="${BASE_URL:-http://127.0.0.1:8080/}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_NAME="${DB_NAME:-imaginator_e2e}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-root}"
export DB_ROOT_USER="${DB_ROOT_USER:-root}"
export DB_ROOT_PASS="${DB_ROOT_PASS:-root}"

bash "$(dirname "$0")/setup-demo-instance.sh"

echo "› configuring GraphicsMagick for the local image processor"
mkdir -p config/system
cat > config/system/additional.php <<'PHP'
<?php
// CI: the demo runs the local image processor (no imgproxy), so enable GraphicsMagick.
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled'] = true;
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'GraphicsMagick';
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] = '/usr/bin/';
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_effects'] = false;
$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] = 'gif,jpg,jpeg,png,webp,tif,bmp,svg,avif';
PHP

.Build/bin/typo3 cache:flush
