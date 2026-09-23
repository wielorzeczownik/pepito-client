#!/usr/bin/env bash

set -uo pipefail

: "${GITHUB_OUTPUT:?GITHUB_OUTPUT is required}"

report="${REPORT_FILE:-audit-report.txt}"
: >"$report"

emit() {
  echo "$1=$2" >>"$GITHUB_OUTPUT"
}

emit_report() {
  {
    echo 'report<<AUDIT_REPORT_EOF'
    if [[ -s "$report" ]]; then
      cat "$report"
    else
      echo 'no advisories found'
    fi
    echo 'AUDIT_REPORT_EOF'
  } >>"$GITHUB_OUTPUT"
}

unresolved=false

echo "### cargo audit" >>"$report"
if ! cargo audit --color never >>"$report" 2>&1; then
  unresolved=true
fi

echo >>"$report"
echo "### composer audit" >>"$report"
if ! composer audit --no-interaction >>"$report" 2>&1; then
  unresolved=true
fi

echo >>"$report"
echo "### npm audit (js/)" >>"$report"
changed=false
if ! (cd js && npm audit) >>"$report" 2>&1; then
  echo "Advisories found in js/, attempting npm audit fix" >>"$report"
  (cd js && npm audit fix) >>"$report" 2>&1 || echo "npm audit fix could not resolve everything" >>"$report"

  if ! git diff --quiet -- js/package-lock.json; then
    changed=true
  fi

  if ! (cd js && npm audit) >>"$report" 2>&1; then
    unresolved=true
  fi
fi

emit changed "$changed"
emit unresolved "$unresolved"
emit_report
echo "lockfile changed: $changed, unresolved: $unresolved"
