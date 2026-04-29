# Prophet OpenAI Contract Index

Status: active

This package owns OpenAI-specific behavior inside the stable Prophet adapter
boundary.

## Public Contract Set

`DecodeLabs\Prophet\Platform\OpenAi`

- public adapter class remains stable
- internal strategies stay package-private

Package config:

- strategy selection is package-level config
- package config now lives in `DecodeLabs\Prophet\Platform\OpenAiConfig`
- first-lane default is `assistants`
- no per-subject runtime hint in this lane
- no blueprint-level override in this lane unless later implementation proves
  config-only selection is too rigid

## Contract Corrections Promoted In This Lane

`reply()` behavior:

- current behavior submits work and updates thread execution state
- returned `Message` is the submission-side message, not the final assistant
  result
- docs and tests must align to that contract

Assistant persistence semantics:

- under `assistants`, assistant persistence may imply a remote assistant object
- under `responses`, assistant persistence is local compatibility state first
- consumers must not need to care which mapping is active

Thread lifecycle semantics:

- `serviceId`, `runId`, timestamps, `rawStatus`, and normalized `status` remain
  the compatibility envelope
- `refreshThread()` is the poll path for non-ready work
- `deleteThread()` must stay safe and idempotent

Messages contract:

- `fetchMessages()` must return ordered Prophet messages on either strategy
- Responses message reconstruction may be synthetic as long as the Prophet
  contract is preserved

## Explicit Non-Goals For This Lane

- no consumer schema change
- no required consumer code change
- no public runtime hint API
- no hidden contract rewrite of `Prophet::reply()`

## Failure Rule

If Responses execution cannot satisfy current message-history expectations
without new consumer persistence requirements, stop and split that into a new
contract lane instead of smuggling it into this one.
