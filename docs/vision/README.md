# Prophet OpenAI Vision

Status: active

## Role

`prophet-openai` is the OpenAI execution adapter for Prophet.

It owns:

- OpenAI model suggestion policy
- OpenAI assistant, thread, run, and response mapping
- OpenAI status normalization
- internal strategy selection
- migration and rollout policy for OpenAI-specific execution

It does not own:

- Prophet core public interface changes
- consumer repository schema changes in the first lane
- client app rollout implementation

## First-Lane Rule

The first executable batch must stay safe for existing consumers such as Little
Inventors.

That means:

- default strategy is `assistants`
- no required consumer code change
- no required consumer schema change
- OpenAI strategy selection stays package-local

## Next Task

Keep `assistants` as the safe default while proving `responses` for first-lane
consumers and `conversations` for durable thread semantics.
