# Quickstart: Abilities Business Logic and REST API

## 1. Create a database-managed ability

Send an administrator-authenticated request to create a new ability in draft status.

The Write controller prepends the `acrossai-abilities/` prefix automatically — submit only the suffix.

Example request payload:

```json
{
  "slug_suffix": "example-ability",
  "label": "Example Ability",
  "category": "custom",
  "callback_type": "noop",
  "callback_config": {},
  "show_in_rest": true,
  "show_in_mcp": false
}
```

Expected result:

- `source` is `db`
- `status` defaults to `draft`
- `ability_slug` in the response is `acrossai-abilities/example-ability`
- the response returns the full saved row

## 2. Browse and filter abilities

Request the paginated collection with source/status/search parameters.

Example:

```text
GET /wp-json/acrossai-abilities-manager/v1/abilities?source=db&status=draft&search=example&page=1&per_page=20
```

Expected result:

- filtered `items`
- accurate `pagination`
- matching `X-WP-Total` and `X-WP-TotalPages` headers

## 3. Perform a sparse update

Update only the fields that change, then confirm the untouched fields remain intact in the returned full record.

## 4. Publish and verify runtime registration

Change the managed row to `status = publish` only after category and execution configuration are valid.

Expected result at runtime bootstrap:

- valid `source = db` published abilities register
- invalid or incomplete rows are skipped
- runtime execution requires an authenticated user

## 5. Query exposure collections

Request `tool`, `resource`, or `prompt` collections for published database-managed abilities and verify that only matching rows are returned.
