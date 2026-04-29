# Inventors Rollout

Status: active
Owner: `prophet-openai`

## Goal

Roll the dual-strategy package into Little Inventors without consumer schema
changes and without changing the default strategy away from `assistants`.

## Default

Use `DecodeLabs\Prophet\Platform\OpenAiConfig` with:

- `defaultStrategy: 'assistants'`
- `responsesBackground: true`
- `responsesStore: true`

Do not flip Inventors to `responses` in the first rollout tranche.

## Proof Already In Package

- public `OpenAi` entrypoint stays stable
- Assistants path remains the default route
- Responses path is package-config gated
- local tests cover strategy selection, reply chaining, message synthesis, and
  terminal status normalization

## Inventors Rollout Steps

1. Upgrade `prophet-openai` with no consumer code changes.
2. Keep the runtime config on `assistants`.
3. Verify existing thread start, poll, fetch, and delete flow in Inventors.
4. Capture any message-history or polling mismatches as package issues before
   touching strategy defaults.
5. Only trial `responses` behind explicit environment-level config in a bounded
   non-default check path.

## Required Proof Before Any Responses Trial

- no repository schema change is needed
- no subject payload shape change is needed
- current Inventors async poll flow still maps cleanly to Prophet thread state
- `fetchMessages()` returns enough ordered history for current UI/workflow needs
- thread deletion remains safe after result retrieval

## Blockers That Stop The Rollout

- any need for new consumer persistence columns or tables
- any mismatch between current Inventors polling expectations and response
  status mapping
- any message reconstruction gap that requires hidden local persistence
- any need for per-blueprint or per-subject runtime hints to keep behavior sane

## Next Task

Run the compatibility-first Inventors package update on `assistants`, record
observed proof points, then decide whether a bounded `responses` trial is safe.
