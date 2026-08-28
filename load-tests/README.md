# ShopNXE load tests

This directory contains an intentionally read-only k6 harness for measuring
Store-scoped Product Detail traffic. It never provisions Stores, creates
Products, or sends writes. Use only staging tokens and public IDs in a local,
untracked copy of `stores.example.json`.

## Run a smoke test

Install k6, copy `stores.example.json` to `stores.local.json`, and replace its
placeholders with staging-only credentials. Keep that local file untracked.

```powershell
$env:BASE_URL = 'https://staging-api.example.com'
$env:STORES_FILE = './stores.local.json'
$env:TARGET_RPS = '5'
$env:STAGE_DURATION = '1m'
k6 run load-tests/product-detail-read.js
```

Increase `TARGET_RPS`, `PRE_ALLOCATED_VUS`, `MAX_VUS`, and `STAGE_DURATION`
gradually. Set `WITH_REFERENCE_DATA=true` to measure the intentionally heavier
reference-data path. `SECTIONS` defaults to `product,images,variants`.

Do not run this harness against production until rate limits, alarms, an abort
owner, and an approved traffic window are in place. Concurrent write testing is
not included because it necessarily changes Product revisions and belongs only
in a disposable AWS staging database.
