# Prophet OpenAI

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/prophet-openai?style=flat)](https://packagist.org/packages/decodelabs/prophet-openai)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/prophet-openai.svg?style=flat)](https://packagist.org/packages/decodelabs/prophet-openai)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/prophet-openai.svg?style=flat)](https://packagist.org/packages/decodelabs/prophet-openai)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/decodelabs/prophet-openai/integrate.yml?branch=develop)](https://github.com/decodelabs/prophet-openai/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/prophet-openai?style=flat)](https://packagist.org/packages/decodelabs/prophet-openai)

### OpenAI Responses adapter for DecodeLabs Prophet

This package provides the OpenAI implementation of Prophet's stateless response
contract.

---

## Installation

This package requires PHP 8.4 or higher.

Install via Composer:

```bash
composer require decodelabs/prophet-openai
```

## Usage

`DecodeLabs\Prophet\Platform\OpenAi` accepts a configured OpenAI PHP client and
executes one Responses API request per Prophet call.

The adapter supports:

- text output
- JSON output
- default model suggestion by language-model level
- raw runtime model overrides through `GenerationOptions`

Package-local docs and specs live under [docs/](./docs/README.md).

## Licensing

Prophet OpenAI is licensed under the MIT License. See [LICENSE](./LICENSE) for
the full license text.
