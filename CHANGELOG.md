# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Until the API
stabilizes at 1.0 a `0.x` bump may carry breaking changes.

## [Unreleased]

## [0.1.8] - 2026-09-24

### Added

- Auto-verify to-card payments from forwarded bank deposit SMS: a per-bot
  webhook (HMAC-signed, Blu Bank format to start) parses deposit
  notifications and auto-accepts the single pending attempt whose amount
  matches within a 30-minute window; an unmatched or ambiguous SMS falls
  back to the admin transactions chat for manual review.
- "Unique Payment Amounts" setting: adds a small random amount to each
  to-card invoice, retried against amounts already used by other pending
  attempts on the same bot, so concurrent payments of the same price can
  always be told apart automatically instead of colliding into manual
  review.

## [0.1.7] - 2026-09-22

### Changed

- Accepts `telegram-bot-essentials/essence` `^0.14` as well as `^0.13`:
  0.14.0 only removed `DoneLimited`/`CannotSetItAsDone`/`HidesDone`, none of
  which this package uses.

## [0.1.6] - 2026-09-20

### Added

- The manual card payment flow now shows the invoice amount and any offer
  code's effect: on the "pay to this card" message the member sees, and on
  the accept/reject message posted to the admin transactions chat (which
  previously carried no price at all).

## [0.1.4] - 2026-09-01

### Changed

- **BREAKING:** requires `telegram-bot-essentials/essence` `^0.12`.

### Added

- Pest test suite, Laravel Pint, Larastan (level max), GitHub Actions CI,
  Laravel Workbench, `LICENSE` (MIT) and this changelog.

### Removed

- The `phpstan-bootstrap.php` `ExceptionHandler` recursion stub — essence's
  handler now guards its own fallback path.
