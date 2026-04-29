# OpenAI Strategy Split

Status: active
Owner: `prophet-openai`

## Goal

Split OpenAI execution into internal Assistants and Responses strategies while
keeping the public adapter entrypoint and first-lane consumer behavior stable.

## Batches

1. Install package-local docs and promote contract corrections.
2. Extract the current monolithic `OpenAi` class into a coordinator plus
   Assistants strategy.
3. Add Responses strategy behind config with `assistants` still default.
4. Add local verification for strategy selection, mapping, and compatibility.
5. Write rollout notes for Inventors first, then the later Acowtancy lane.

## Done In This Batch

- package-local docs spine installed
- strategy ownership documented
- migration rationale documented with current OpenAI references
- first-lane guardrails and rollout order promoted
- internal strategy seam extracted with Assistants as the default path
- package-level `OpenAiConfig` added for strategy selection
- Responses strategy added behind config without changing the public adapter
  entrypoint
- local test harness added for coordinator routing, Responses mapping, and
  status normalization
- package validated with PHPUnit and PHPStan in this repo
- rollout notes added for Inventors-first adoption and deferred Acowtancy work

## Switch Criteria For A Later Default Flip

Do not make `responses` the default until:

- Inventors path is documented and locally verified
- status mapping is deterministic across terminal states
- `fetchMessages()` preserves minimum history expectations
- no consumer schema change is needed

## Next Task

Use the new rollout notes to run Inventors on `assistants`, capture proof for
or against a bounded `responses` trial, and keep default-switch decisions out
of implementation until that evidence exists.
