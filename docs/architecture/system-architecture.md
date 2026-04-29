# Prophet OpenAI System Architecture

Status: active
Lane: OpenAI strategy split

## Migration Rationale

OpenAI now recommends the Responses API for new work and has deprecated the
Assistants API with shutdown scheduled for August 26, 2026. Prompt caching also
works automatically for repeated prompt prefixes on supported requests, so
persisted remote assistants are no longer the only reuse primitive.

This rationale is based on OpenAI's current official docs:

- Assistants migration guide: https://platform.openai.com/docs/assistants/how-it-works
- Responses migration guide: https://platform.openai.com/docs/guides/migrate-to-responses
- Prompt caching guide: https://platform.openai.com/docs/guides/prompt-caching

## Public Shape

`DecodeLabs\Prophet\Platform\OpenAi` remains the public `Platform`
implementation.

In this lane it becomes a coordinator, not the single place where every OpenAI
branch lives.

## Internal Shape

Coordinator:

- selects the configured execution strategy
- keeps public constructor and platform role stable for consumers
- delegates lifecycle operations to strategy-specific internals

Internal strategy seam:

- `AssistantsExecutionStrategy`
- `ResponsesExecutionStrategy`
- `ConversationsExecutionStrategy`

Shared internal collaborators:

- model suggestion policy
- status normalization
- message/content mappers
- assistant metadata builder
- thread state mapper
- response input/output mapper

## Strategy Rules

Assistants strategy:

- preserves current behavior
- keeps assistant records backed by remote OpenAI assistants
- keeps current thread, run, refresh, and message-list behavior
- stays the default in the first rollout

Responses strategy:

- uses the Responses API as the execution engine
- uses background mode where Prophet's request/poll flow benefits from it
- uses `store: true` in the first lane for pollable background responses
- orders stable instructions and reusable prompt prefixes first
- keeps prompt cache tuning package-local and optional

Conversations strategy:

- uses Conversations for durable remote thread identity
- pairs Conversations with Responses execution and polling
- maps `serviceId` to a real OpenAI conversation id
- keeps assistant persistence local, like the Responses lane

## Contract Boundary

Prophet core stays stable in this lane.

This package may change:

- internal class structure
- OpenAI request payload shape
- whether an assistant maps to a remote assistant object
- how thread state is reconstructed from OpenAI data

This package may not change in this lane:

- the `Platform` entrypoint contract
- required consumer persistence schema
- required consumer call-site behavior

## Domain Mapping

Assistant under Assistants strategy:

- persisted Prophet assistant plus remote OpenAI assistant asset

Assistant under Responses strategy:

- persisted Prophet assistant as local configuration/state record
- remote assistant object optional, not required

Assistant under Conversations strategy:

- persisted Prophet assistant as local configuration/state record
- remote assistant object optional, not required

Thread under Assistants strategy:

- OpenAI thread id in `serviceId`
- OpenAI run id in `runId`

Thread under Responses strategy:

- compatibility envelope around response execution state
- `serviceId` remains the stable local or conversation-compatible thread anchor
- `runId` carries the active OpenAI response id

Thread under Conversations strategy:

- OpenAI conversation id in `serviceId`
- active OpenAI response id in `runId`
- thread history loaded from conversation items instead of response-chain replay

Messages under Responses strategy:

- rebuilt from response input items and output items
- exposed as normal Prophet `MessageList`

Messages under Conversations strategy:

- rebuilt from conversation items
- exposed as normal Prophet `MessageList`

## Rollout Order

1. Inventors compatibility path on `assistants`
2. local proof and migration notes
3. later Acowtancy lane
