# GIFTSPB-1578 — Loyalty System P1 Backfixes (common-resources scope)

**Branch:** `bug/GIFTSPB-1578/loyalty-system-p1-backfixes`
**Date:** 2026-06-26
**Repo role:** event definition. This is one of three repos in GIFTSPB-1578. The
full cross-repo design lives in the **gifts-api** repo at
`docs/superpowers/specs/2026-06-26-loyalty-system-p1-backfixes-design.md`.

## Context

A paid order that is later cancelled triggers a loyalty earn-revoke in gifts-api.
The earn flow already carries `orderUuid` (added to `OrderPaid` in commit
`edbe2ea`); the cancellation flow must mirror it so the revoke transaction can be
correlated with the order. This repo owns the broker event definition.

## Change

`OrderCancelled` (`src/Queue/Events/Order/OrderCancelled.php`):

- Add `public ?string $orderUuid = null` as the **last** constructor parameter,
  after `isB2b`. This mirrors `OrderPaid` exactly.
- No serialization changes required: `AbstractEmotiEvent` uses the reflection-based
  `ArrayableTrait` (`fromArray`/`toArray`), which picks up constructor-promoted
  properties automatically.

`orderUuid` is nullable with a `null` default, so the change is
backward-compatible with already-serialized messages in flight.

## Tests

`tests/Unit/Queue/Events/Order/OrderCancelledTest.php`:

- Round-trip: `orderUuid` survives `toArray()` → `fromArray()`.
- Backward-compat: a payload **without** `orderUuid` deserializes with
  `orderUuid === null`.

## Release

- Tag **`1.52.0`** (minor — new optional field on a public event).
- Consumers (agcore, gifts-api) bump their constraint to `^1.52.0` **after** the
  tag is published.
