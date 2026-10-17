#!/usr/bin/env bash
set -uo pipefail

errors=$(mktemp)
audit=$(composer audit --format=json --locked --no-interaction "$@" 2>"$errors")
lock=$(cat composer.lock 2>/dev/null || echo '{}')

if ! jq -e '.advisories' <<<"$audit" >/dev/null 2>&1; then
  printf '## composer audit Report\n\n⚠️ composer audit could not run: %s\n' "$(grep -v '^\s*$' "$errors" | tail -n 1 | sed 's/^[[:space:]]*//')"
  exit 1
fi

jq -r --argjson lock "$lock" '
  def icon: {critical: "🔴", high: "🟠", moderate: "🟡", low: "⚪"}[.] // "";
  def order: {critical: 0, high: 1, moderate: 2, low: 3}[.] // 4;
  ([($lock.packages // []), ($lock["packages-dev"] // [])] | add | map({(.name): .version}) | add // {}) as $versions
  | [.advisories | to_entries[] | .value | (if type == "array" then .[] else .[] end)
     | . + {sev: ({medium: "moderate"}[.severity // ""] // .severity // "n/a")}] as $vulns
  | [(.abandoned // {}) | to_entries[]] as $abandoned
  | "## composer audit Report", "",
    "### Summary", "",
    "- **Dependencies:** \($versions | length) checked",
    (if ($vulns | length) == 0 then "- **Advisories:** ✅ none"
     else "- **Advisories:** ❌ \($vulns | length) total · " + ($vulns | group_by(.sev) | sort_by(.[0].sev | order) | map("\(.[0].sev | icon) \(length) \(.[0].sev)") | join(" · ")) end),
    (if ($abandoned | length) > 0 then "- **Warnings:** ⚠️ \($abandoned | length) total · \($abandoned | length) abandoned" else empty end),
    (if ($vulns | length) > 0 then
       "", "### Advisories", "",
       "| Package | Severity | Advisory | Fix |",
       "| --- | --- | --- | --- |",
       ($vulns | sort_by(.sev | order) | .[]
        | "| `\(.packageName)` \($versions[.packageName] // "") | \(.sev | icon) \(.sev) | [\(.cve // .advisoryId): \(.title | gsub("\\|"; "/"))](\(.link)) | affected `\(.affectedVersions | gsub("\\|"; " or "))` |")
     else empty end),
    (if ($abandoned | length) > 0 then
       "", "### Warnings", "",
       "| Package | Kind | Advisory |",
       "| --- | --- | --- |",
       ($abandoned[] | "| `\(.key)` \($versions[.key] // "") | abandoned | \(if .value then "use `\(.value)` instead" else "no replacement" end) |")
     else empty end)
' <<<"$audit"

[[ "$(jq '[.advisories[] | length] | add // 0' <<<"$audit")" == 0 ]]
