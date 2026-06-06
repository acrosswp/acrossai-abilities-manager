# Tasks: Add-ons Page Integration (Feature 026)

## CHANGE-1 — composer.json

- [x] T001: Add `repositories` array with path entry pointing at `../../../wpb-addons-page`
- [x] T002: Add `"wpboilerplate/addons-page": "@dev"` to `require` block
- [x] T003: Run `composer update wpboilerplate/addons-page` and verify `vendor/wpboilerplate/addons-page/` exists

## CHANGE-2 — includes/Main.php

- [x] T004: Instantiate `AddonsPage` after the Settings submenu block, wrapped in `class_exists()` guard per Integration Resilience rule

## CHANGE-3 — README.txt

- [x] T005: Read `vendor/wpboilerplate/addons-page/docs/readme-template.txt` and identify the three sections
- [x] T006: Append `== Installation ==`, `== External Services ==`, and `== Privacy Policy ==` sections verbatim to `README.txt`

## Quality Gates

- [x] T007: Run `composer run phpstan` — verify zero new errors
- [x] T008: Run `composer run phpcs` on changed PHP files — verify zero new errors
