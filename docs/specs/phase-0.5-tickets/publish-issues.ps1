# Publish the Phase 0.5 tickets to GitHub as issues, in dependency order (blockers first).
#
# Prerequisites (gh is NOT installed on this machine yet):
#   winget install --id GitHub.cli -e
#   gh auth login
# Then run this from the REPO ROOT:
#   pwsh docs/specs/phase-0.5-tickets/publish-issues.ps1
#
# Blocking edges are stated in each issue body ("Blocked by"). GitHub-native issue
# dependencies can optionally be added afterwards from the printed issue numbers.

$ErrorActionPreference = 'Stop'
$dir = 'docs/specs/phase-0.5-tickets'

# Ensure the triage label exists (ignore failure if it already does).
try { gh label create 'ready-for-agent' --color '0E8A16' --description 'Fully specified, ready for an AFK agent' } catch {}

# Issue titles keyed by local ticket number (creation order = dependency order).
$titles = [ordered]@{
  '01' = 'Phase 0.5 - T1 - Token-driven theme foundation'
  '02' = 'Phase 0.5 - T2 - Self-hosted bilingual fonts'
  '03' = 'Phase 0.5 - T3 - App-shell chrome (app bar + role-aware side nav)'
  '04' = 'Phase 0.5 - T4 - StatusChip presentational component'
  '05' = 'Phase 0.5 - T5 - Contrast gate (util + token-contrast test)'
  '06' = 'Phase 0.5 - T6 - Re-skin screens + no-raw-hex ESLint gate'
  '07' = 'Phase 0.5 - T7 - /style gallery + hybrid visual smoke + axe'
}

foreach ($nn in $titles.Keys) {
  $file = Get-ChildItem "$dir/$nn-*.md" | Where-Object { $_.Name -notlike '*publish*' } | Select-Object -First 1
  $url = gh issue create --title $titles[$nn] --body-file $file.FullName --label 'ready-for-agent'
  Write-Host "Created $nn -> $url"
}

Write-Host ''
Write-Host 'Done. Work the frontier with /implement: 01 first, then 02/03/04/05 in parallel, then 06, then 07.'
