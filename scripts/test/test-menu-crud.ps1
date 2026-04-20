#!/usr/bin/env pwsh
param([string]$Base = "http://localhost:8010/", [string]$Sheet = "AWGNK MENU")

$ErrorActionPreference = "Stop"
$pass = 0; $fail = 0
$testName = "__DUMMY_TEST_$(Get-Date -Format 'HHmmssff')__"

function Step([int]$n, [string]$label) { Write-Host "`n[$n] $label" -ForegroundColor Cyan }
function Pass([string]$msg) { Write-Host "    PASS  $msg" -ForegroundColor Green;  $script:pass++ }
function Fail([string]$msg) { Write-Host "    FAIL  $msg" -ForegroundColor Red;    $script:fail++; throw "step_failed" }

function API([hashtable]$h) {
    $encoded = "payload=" + [Uri]::EscapeDataString(($h | ConvertTo-Json -Depth 10 -Compress))
    $r = Invoke-WebRequest -Uri $Base -Method POST -ContentType "application/x-www-form-urlencoded" `
         -Body $encoded -UseBasicParsing -TimeoutSec 12
    return $r.Content | ConvertFrom-Json
}

try {
    # ── 1. Login ──────────────────────────────────────────────────────────────
    Step 1 "Login"
    $res = API @{ action = "auth_login"; username = "9371519999"; password = "8442" }
    if (-not $res.ok) { Fail "API returned ok=false: $($res.message)" }
    $tok = [string]$res.token
    Pass "Logged in | token present=$(-not [string]::IsNullOrEmpty($tok))"

    # ── 2. Load baseline ──────────────────────────────────────────────────────
    Step 2 "Load baseline sheet '$Sheet'"
    $l1 = API @{ action = "admin_menu_editor_load"; sheetName = $Sheet; token = $tok }
    if (-not $l1.ok) { Fail $l1.message }
    $bIds = @($l1.items | ForEach-Object { [int]($_.id ?? 0) } | Where-Object { $_ -gt 0 })
    Pass "Rows: $($l1.items.Count)  |  IDs tracked: $($bIds.Count)"

    # ── 3. Add blank row ──────────────────────────────────────────────────────
    Step 3 "Add blank row"
    $a = API @{ action = "admin_menu_editor_add_row"; sheetName = $Sheet; token = $tok }
    if (-not $a.ok) { Fail $a.message }
    Pass "Server accepted add-row."

    # ── 4. Find new row ───────────────────────────────────────────────────────
    Step 4 "Reload & find new row"
    $l2 = API @{ action = "admin_menu_editor_load"; sheetName = $Sheet; token = $tok }
    if (-not $l2.ok) { Fail $l2.message }
    $nr = $l2.items | Where-Object {
        $id = [int]($_.id ?? 0); $id -gt 0 -and ($bIds -notcontains $id)
    } | Select-Object -First 1
    if (-not $nr) { Fail "New row not identified (got $($l2.items.Count) rows, baseline had $($bIds.Count))" }
    $nId = [int]$nr.id
    $nRN = if ($nr.PSObject.Properties["rowNumber"]) { [int]$nr.rowNumber } else { [int]($l2.items.IndexOf($nr)) + 2 }
    Pass "New row  id=$nId  rowNumber=$nRN"

    # ── 5. Save test name ─────────────────────────────────────────────────────
    Step 5 "Save dummy item name"
    $update = @{ rowNumber = $nRN; id = $nId; cells = @{ "Item Name" = $testName; "Food Category" = "TEST_DUMMY" } }
    $s = API @{ action = "admin_menu_editor_save_changes"; sheetName = $Sheet; token = $tok; updates = @($update) }
    if (-not $s.ok) { Fail $s.message }
    Pass "Saved. updated=$($s.updated ?? '?')  saved=$($s.saved ?? '?')"

    # ── 6. Verify save ────────────────────────────────────────────────────────
    Step 6 "Reload & verify save persisted"
    $l3 = API @{ action = "admin_menu_editor_load"; sheetName = $Sheet; token = $tok }
    if (-not $l3.ok) { Fail $l3.message }
    $v = $l3.items | Where-Object { ($_ | ConvertTo-Json -Compress) -like "*$testName*" } | Select-Object -First 1
    if (-not $v) { Fail "Test name '$testName' not found in any row after save." }
    Pass "Row id=$([int]$v.id) has the test item name."

    # ── 7. Delete ─────────────────────────────────────────────────────────────
    Step 7 "Delete test row id=$nId"
    $del = API @{ action = "admin_menu_editor_delete_rows"; sheetName = $Sheet; token = $tok; ids = @($nId) }
    if (-not $del.ok) { Fail $del.message }
    Pass "Deleted. count=$($del.deleted ?? '?')"

    # ── 8. Verify deletion ────────────────────────────────────────────────────
    Step 8 "Verify deletion"
    $l4 = API @{ action = "admin_menu_editor_load"; sheetName = $Sheet; token = $tok }
    if (-not $l4.ok) { Fail $l4.message }
    $gone = $l4.items | Where-Object { [int]($_.id ?? 0) -eq $nId }
    if ($gone) { Fail "Row id=$nId still present after delete!" }
    if ($l4.items.Count -ne $bIds.Count) {
        Fail "Count mismatch: baseline=$($bIds.Count)  final=$($l4.items.Count)"
    }
    Pass "Row gone. Count restored to $($l4.items.Count) (matches baseline)."

} catch {
    if ($_.ToString() -ne "step_failed") {
        Write-Host "`n    ERROR  $($_.Exception.Message)" -ForegroundColor Red
        $fail++
    }
}

# ── Summary ───────────────────────────────────────────────────────────────────
$total = $pass + $fail
Write-Host "`n=================================================" -ForegroundColor Cyan
Write-Host "  PASSED $pass / $total    FAILED $fail" -ForegroundColor $(if ($fail -eq 0) { "Green" } else { "Red" })
Write-Host "=================================================" -ForegroundColor Cyan
if ($fail -gt 0) { exit 1 }
