#!/usr/bin/env bash

set -uo pipefail

: "${GITHUB_OUTPUT:?GITHUB_OUTPUT is required}"

dir="$(cd "$(dirname "$0")" && pwd)"
unresolved=false
changed=false

cargo_report=$(bash "$dir/cargo-audit-report.sh") || unresolved=true
composer_report=$(bash "$dir/composer-audit-report.sh") || unresolved=true

if ! npm_report=$(cd js && bash "$dir/npm-audit-report.sh"); then
  echo "Advisories found in js/, attempting npm audit fix"
  (cd js && npm audit fix) || echo "npm audit fix could not resolve everything"

  if ! git diff --quiet -- js/package-lock.json; then
    changed=true
  fi

  npm_report=$(cd js && bash "$dir/npm-audit-report.sh") || unresolved=true
fi

report=$(printf '%s\n\n%s\n\n%s\n' "$cargo_report" "$composer_report" "$npm_report")

{
  echo "changed=$changed"
  echo "unresolved=$unresolved"
  echo 'report<<AUDIT_REPORT_EOF'
  echo "$report"
  echo 'AUDIT_REPORT_EOF'
} >>"$GITHUB_OUTPUT"

echo "$report"
echo "lockfile changed: $changed, unresolved: $unresolved"
