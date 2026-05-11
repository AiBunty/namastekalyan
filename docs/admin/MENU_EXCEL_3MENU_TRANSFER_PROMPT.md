# 3-Menu Excel Admin Editor Implementation Prompt

Use this prompt in another workspace to implement the same Excel-based admin menu system style used in this project, extended to 3 menus.

## How To Use This Prompt

1. Paste the prompt section below into the other workspace chat/agent.
2. Keep behavior parity for import, export, category designer, permissions, and diet logic.
3. Replace branding and menu names only.

## Prompt To Give In Other Workspace

```md
Implement a production-ready Admin Menu Management system using PHP + MySQL, with Excel import/export and category designer behavior matching the reference project.

The target project has 3 menus. Implement them as:
- menu_a (menu.html)
- menu_b (cockatail.html)
- menu_c (namaste_chef.html)

All 3 menus must support:
- admin load/edit/save
- add row/delete row
- visibility toggle
- import preview + import execute (from Excel)
- export to Excel
- category ordering and item ordering
- category visibility toggle
- item visibility toggle
- category clone
- snapshots (optional but strongly recommended)

Use the same architecture:
- Controllers -> Services -> Repositories
- token auth + permission gate
- permission key required: menuEditor

------------------------------------------------------------

# 1. Backend Stack (Mandatory)

- Language: PHP
- Database: MySQL
- DB access: PDO with prepared statements only
- Migrations: SQL migration files
- Encoding: utf8mb4 for all text

------------------------------------------------------------

# 2. Data Model (3-menu capable)

Use either one normalized table with menu_type, or separate per-menu tables.
Recommended approach for parity with current modern flow:

## 2.1 menu_items_<type> tables (or unified menu_items)
Required columns:
- id
- menu_type (menu_a | menu_b | menu_c) if unified
- category
- item_name
- description
- image_url
- is_available
- is_chef_special

Diet columns (must exist for food-like menus):
- is_veg
- is_nonveg
- is_jain
- is_universal
- primary_diet ENUM('veg','nonveg','jain','mixed','universal','bar','')

Pricing columns:
- pricing_mode ('standard' or 'custom_variants')
- price_veg
- price_jain
- price_chicken
- price_mutton
- price_basa
- price_prawns
- price_surmai
- price_pomfret
- price_crab
- price_egg
- price_half
- price_full
- price_plain
- price_butter
- price_medium
- price_large
- price_direct

Sorting:
- category_sort_order
- item_sort_order

Audit:
- source_row
- manually_edited
- created_at
- updated_at

## 2.2 menu_item_variants_<type> (or unified table)
Required columns:
- id
- item_id
- variant_label
- price
- variant_sort_order
- created_at
- updated_at

## 2.3 menu_categories
Required columns:
- id
- menu_type
- name
- sort_order
- is_active
- aliases_json (optional)
- created_at
- updated_at

------------------------------------------------------------

# 3. Diet Classification Rules (Must Match)

Implement exactly these diet rules during import and editor save:

1. is_veg = true when veg pricing exists and non-veg pricing does not exist.
2. is_nonveg = true when any non-veg price column has value.
3. is_jain = true when Jain flag or Jain price column exists.
4. is_universal = true for universal categories (drinks/desserts/common items).
5. primary_diet mapping:
   - jain if is_jain
   - nonveg if is_nonveg and not is_veg
   - veg if is_veg and not is_nonveg
   - mixed if both is_veg and is_nonveg
   - universal if is_universal
   - bar for bar-only menu rows

Universal category behavior:
- Universal items are visible in all filter modes.
- Universal items should not show veg/nonveg badge unless explicitly needed by product UX.

Recommended default universal categories:
- Mocktails
- Shakes & Smoothies
- Iced Tea
- Lemonades
- Cold Ones
- Breads
- Dessert
- Desserts

------------------------------------------------------------

# 4. Excel Import/Export Contract (3 menus)

Implement one sheet per menu OR one file per menu.
Preferred for clarity: one file with three sheets.

Required sheet names:
- MENU_A
- MENU_B
- MENU_C

For each sheet:
- read header row
- map aliases to DB columns
- detect unknown columns
- treat unknown numeric columns as variant columns
- support preview mode (no DB writes)
- execute mode with DB transaction

Import preview response must include:
- sheet_found
- total_rows
- data_rows
- blank_rows_skipped
- mapped_columns
- unmapped_columns
- sample_rows
- categories
- variant_columns
- previewSummary

Import execute response must include:
- ok
- type
- inserted
- updated
- skipped
- warnings/errors

Export behavior:
- include all standard columns
- include detected variant columns
- write human-friendly boolean values
- provide downloadable .xlsx

Template behavior:
- downloadable template per menu
- includes valid headers and one sample row

------------------------------------------------------------

# 5. Admin Actions / APIs

Implement equivalent actions:

Editor:
- admin_menu_editor_load
- admin_menu_editor_save_changes
- admin_menu_editor_add_row
- admin_menu_editor_delete_rows
- admin_menu_editor_set_visibility

Designer:
- admin_menu_designer_load
- admin_menu_designer_save_category_order
- admin_menu_designer_save_item_order
- admin_menu_designer_toggle_category
- admin_menu_designer_toggle_item
- admin_menu_designer_clone_category

Import/Export:
- admin_menu_import_preview
- admin_menu_import_execute
- admin_menu_export
- admin_menu_template

All actions must enforce:
- valid token
- role admin or superadmin
- permission menuEditor

------------------------------------------------------------

# 6. Frontend Module Behavior

Implement admin modules:
- menu-editor module
- menu-category-designer module
- data-import module

Required UX:
- embedded or standalone page under admin portal
- table/grid editor with grouped columns
- category picker support
- per-row variant editing
- preview import modal
- unresolved-category confirmation before execute
- export and template buttons
- clear status and error messaging

------------------------------------------------------------

# 7. Code Snippets (Reference Style)

## 7.1 PHP diet classifier helper

```php
private function classifyPrimaryDiet(array $row, string $menuType): string
{
    if ($menuType === 'menu_b') {
        return 'bar';
    }

    $isJain = !empty($row['is_jain']) || (isset($row['price_jain']) && $row['price_jain'] !== null);
    $isVeg = !empty($row['is_veg']) || (isset($row['price_veg']) && $row['price_veg'] !== null);
    $isNonVeg = !empty($row['is_nonveg']) || $this->hasAnyNonVegPrice($row);
    $isUniversal = !empty($row['is_universal']) || $this->isUniversalCategory((string)($row['category'] ?? ''));

    if ($isUniversal) {
        return 'universal';
    }
    if ($isJain) {
        return 'jain';
    }
    if ($isVeg && $isNonVeg) {
        return 'mixed';
    }
    if ($isNonVeg) {
        return 'nonveg';
    }
    if ($isVeg) {
        return 'veg';
    }

    return '';
}

private function hasAnyNonVegPrice(array $row): bool
{
    $keys = [
        'price_chicken','price_mutton','price_basa','price_prawns',
        'price_surmai','price_pomfret','price_crab','price_egg'
    ];
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
            return true;
        }
    }
    return false;
}
```

## 7.2 PHP menu editor save flow

```php
public function saveChanges(array $body): array
{
    $auth = $this->authorizeMenuEditor($body);
    if (!$auth['ok']) {
        return $auth;
    }

    $menuType = (string)($body['menuType'] ?? 'menu_a');
    $changes = is_array($body['changes'] ?? null) ? $body['changes'] : [];

    $updated = 0;
    foreach ($changes as $change) {
        $id = (int)($change['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $payload = $this->normalizeEditorPayload($change, $menuType);
        $payload['primary_diet'] = $this->classifyPrimaryDiet($payload, $menuType);

        $this->menuRepo->updateItem($menuType, $id, $payload);

        if (isset($change['variants']) && is_array($change['variants'])) {
            $variants = $this->normalizeVariants($change['variants']);
            $this->variantRepo->replaceVariantsForItem($menuType, $id, $variants);
            $payloadMode = empty($variants) ? 'standard' : 'custom_variants';
            $this->menuRepo->updateItem($menuType, $id, ['pricing_mode' => $payloadMode]);
        }

        $updated++;
    }

    return ['ok' => true, 'updatedCount' => $updated];
}
```

## 7.3 Frontend import preview + execute sequence

```javascript
async function previewImport(file, menuType) {
  const fd = new FormData();
  fd.append('action', 'admin_menu_import_preview');
  fd.append('menuType', menuType);
  fd.append('file', file);
  return postMultipart(fd);
}

async function executeImport(tmpPath, menuType, createCategories) {
  return postJson({
    action: 'admin_menu_import_execute',
    menuType,
    tmpPath,
    createCategories,
    takeSnapshot: true
  });
}
```

------------------------------------------------------------

# 8. Three-Menu Specific Requirements

For menu_a, menu_b, menu_c:
- independent category sort order
- independent item sort order
- independent import/export/template flow
- independent visibility toggles
- shared permission and auth model

If menu_c has a different pricing structure:
- still use standard + custom_variants modes
- keep same payload shape so admin UI remains consistent

------------------------------------------------------------

# 9. Acceptance Checklist

Do not mark complete unless all pass:

1. Admin can load each menu (A/B/C) in editor.
2. Save/add/delete/visibility works for each menu.
3. Designer category/item order persists for each menu.
4. Import preview detects columns and shows sample rows.
5. Import execute writes items and variants transactionally.
6. Export produces valid Excel with expected columns.
7. Template download works for each menu.
8. Jain/Veg/NonVeg/Universal logic is correct in DB and frontend filters.
9. Universal items appear in all filters.
10. permission menuEditor blocks unauthorized users.

------------------------------------------------------------

# 10. Deliverables

Provide:
- migration files
- repositories
- controllers/services
- admin modules/pages
- action routing map
- sample request/response docs
- old->new file map and old->new action map

Implement complete end-to-end functionality, not mock screens.
```

## Add-On Prompt: Drag And Drop Menu Designer

Use this add-on prompt when you want the other workspace to implement only the designer behavior first.

```md
Implement a drag-and-drop Menu Category Designer for 3 menus (menu_a, menu_b, menu_c) with behavior matching the reference admin panel.

Do not build a visual-only mock. Persist every reorder/toggle operation to MySQL.

Required backend actions:
- admin_menu_designer_load
- admin_menu_designer_save_category_order
- admin_menu_designer_save_item_order
- admin_menu_designer_toggle_category
- admin_menu_designer_toggle_item
- admin_menu_designer_clone_category

Auth and security:
- token required
- role admin/superadmin required
- permission menuEditor required

Designer load contract:
- input: menuType (menu_a|menu_b|menu_c)
- response:
    - ok
    - menuType
    - categories[]
    - each category contains:
        - name
        - sortOrder
        - isActive
        - items[]
    - each item contains:
        - id
        - itemName
        - isAvailable
        - itemSortOrder

Drag-and-drop behavior:
1. Drag category card to reorder categories.
2. Drag item inside same category to reorder itemSortOrder.
3. Optional: allow move item across categories (if enabled, update category + item order).
4. On drop, call save endpoint immediately or queue and batch-save.
5. Show success/error toast and rollback UI if save fails.

Category operations:
- toggle category active/inactive
- clone category with optional cloneItems=true|false
- if cloneItems=true, clone rows and variants preserving price logic

Item operations:
- toggle item visible/hidden
- item visibility change must reflect on public menu listing

Persistence rules:
- category_sort_order is integer and unique per menuType
- item_sort_order is integer and unique per category per menuType
- all reorder saves must be transactional

Frontend UX requirements:
- desktop drag-and-drop
- mobile long-press drag support or explicit move up/down actions
- sticky save state indicator
- unsaved-changes warning on page exit if batch mode is used

Acceptance criteria:
1. Category reorder persists after reload.
2. Item reorder persists after reload.
3. Toggle category and toggle item update DB and public output.
4. Clone category works with and without item clone.
5. Works independently for each menu (A/B/C).
```

## Add-On Prompt: Import And Export Menu Via Excel

Use this add-on prompt when you want only the Excel pipeline implemented first.

```md
Implement Excel import/export for 3 menus (menu_a, menu_b, menu_c) in PHP + MySQL with preview, validation, execution, and template download.

Required backend actions:
- admin_menu_import_preview
- admin_menu_import_execute
- admin_menu_export
- admin_menu_template

Required permissions:
- token auth
- menuEditor permission

Sheet strategy:
- one workbook with 3 sheets: MENU_A, MENU_B, MENU_C
- OR one file per menu if product prefers, but API contract must still include menuType

Import preview (no DB writes):
- parse headers
- map aliases to canonical fields
- detect unknown headers
- detect variant columns
- build sample rows
- return category mapping recommendations

Preview response must include:
- ok
- menuType
- sheet_found
- total_rows
- data_rows
- blank_rows_skipped
- mapped_columns
- unmapped_columns
- variant_columns
- sample_rows
- previewSummary

Import execute (transactional):
- validate tmpPath or uploaded file
- parse full sheet
- resolve categories (existing/new)
- normalize booleans and prices
- compute diet flags (jain/veg/nonveg/universal)
- compute primary_diet
- upsert/replace items
- write variant rows when pricing_mode=custom_variants
- optional pre-import snapshot
- commit or rollback atomically

Execute response must include:
- ok
- menuType
- inserted
- updated
- skipped
- warnings
- errors

Export behavior:
- include stable header order
- include standard price columns
- include dynamic variant columns discovered from DB
- include visibility and helper metadata needed by admin
- stream downloadable xlsx

Template behavior:
- provide downloadable template per menuType
- include required columns + sample row
- include Jain/Veg/NonVeg/Universal examples

Validation rules:
- reject empty workbook/sheet
- reject missing item_name
- trim all textual fields
- normalize yes/no, true/false, 1/0 booleans
- convert numeric cells safely
- protect against path traversal for tmpPath

Acceptance criteria:
1. Preview works without writing DB.
2. Execute writes rows and variants correctly.
3. Export round-trips with imported data.
4. Templates are valid and easy for ops team.
5. Diet flags and universal behavior are correct after import.
6. Works for menu_a/menu_b/menu_c independently.
```

## Reference Files In This Workspace (for parity)

Frontend modules and pages:
- public/js/admin-modules/menu-editor.js
- public/js/admin-modules/menu-category-designer.js
- public/js/admin-modules/data-import.js
- public/admin/admin-menu-price-editor.html
- public/admin/admin-menu-category-designer.html
- public/admin/admin-portal.html

Backend core:
- app/Controllers/MenuController.php
- app/Controllers/ImportController.php
- app/Services/Menu/MenuImportService.php
- app/Services/Menu/FoodMenuExcelImporter.php
- app/Services/Menu/FoodMenuExcelExporter.php
- app/Repositories/FoodMenuRepository.php
- app/Repositories/FoodMenuVariantRepository.php
- app/Repositories/BarMenuRepository.php
- app/Repositories/BarMenuVariantRepository.php
- app/Routes/ActionRouter.php

Database and diet model history:
- database/migrations/019_create_food_menu_items.sql
- database/migrations/020_create_food_menu_item_variants.sql
- database/migrations/021_create_bar_menu_items.sql
- database/migrations/022_create_bar_menu_item_variants.sql
- database/migrations/023_create_menu_categories.sql
- database/migrations/014_alter_menu_items_add_diet_classification.sql
- database/migrations/018_add_diet_bool_flags.sql

Public filter behavior reference:
- public/menu/index.html
