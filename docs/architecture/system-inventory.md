# Prophet OpenAI System Inventory

Status: active

## Public Surface

`DecodeLabs\Prophet\Platform\OpenAi`

- public Prophet `Platform` implementation
- stable entrypoint for consumer wiring

## Internal Components Planned For This Lane

Coordinator:

- selects configured strategy
- owns shared helpers and package-level defaults

`AssistantsExecutionStrategy`

- current OpenAI Assistants API behavior

`ResponsesExecutionStrategy`

- new OpenAI Responses API behavior

Shared mappers:

- status normalizer
- message mapper
- assistant metadata builder
- thread state mapper
- response input mapper

Config surface:

- default strategy
- Responses defaults such as `background`, `store`, and prompt cache policy
- current public config object: `DecodeLabs\Prophet\Platform\OpenAiConfig`

## External Dependencies

`openai-php/client`

- current API client surface for OpenAI calls

`decodelabs/prophet`

- owned upstream contract boundary

## Inventory Notes

The current package concentrates most of this behavior in one `OpenAi` class.
That is implementation debt, not package contract. The next code batch splits
it without changing the public entrypoint.
