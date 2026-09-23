---
title: Unresolved security advisories
labels: security advisory
---

`cargo audit`, `composer audit` or `npm audit` reports an advisory that could
not be resolved automatically (only npm has an auto-fix path; the other two
are report-only upstream and need a manual version bump or accepted-risk call).

## Audit output

```text
{{ env.AUDIT_REPORT }}
```
