# Prophet OpenAI — Package Specification

> **Cluster:** `logic`
> **Language:** `php`
> **Milestone:** `m6`
> **Repo:** `https://github.com/decodelabs/prophet-openai`
> **Role:** OpenAI Responses adapter

## Overview

### Purpose

Prophet OpenAI provides the OpenAI implementation of Prophet's stateless
platform contract. It enables:

- OpenAI Responses execution for text and JSON generation
- level-based default model suggestion
- raw runtime model overrides
- normalized Prophet `GenerationResult` output

### Non-goals

- assistants lifecycle support
- thread or conversation persistence
- polling or background runs
- transcript reconstruction

## Public Surface

### Key Types

- `DecodeLabs\Prophet\Platform\OpenAi` — stateless OpenAI adapter
- `DecodeLabs\Prophet\Platform\OpenAi\ModelPolicy` — medium and model defaults

### Main Entry Points

- `OpenAi::__construct(Client $client)`
- `OpenAi::fromClientContract(ClientContract $client): self`
- `OpenAi::supportsMedium(Medium $medium): bool`
- `OpenAi::getDefaultModel(Medium $medium): string`
- `OpenAi::respond(Blueprint $blueprint, Subject $subject, GenerationOptions $options): GenerationResult`

## Behavior

- the adapter makes exactly one `responses()->create()` call per Prophet request
- blueprint instructions map to the OpenAI `instructions` field
- blueprint input maps to the OpenAI `input` field
- JSON medium sets `text.format.type = json_object`
- runtime `model` wins over level-based defaults

## Dependencies

- `decodelabs/exceptional`
- `decodelabs/prophet`
- `openai-php/client`
