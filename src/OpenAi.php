<?php

/**
 * Prophet OpenAI
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Prophet\Platform;

use DecodeLabs\Exceptional;
use DecodeLabs\Prophet\Blueprint;
use DecodeLabs\Prophet\GenerationOptions;
use DecodeLabs\Prophet\GenerationResult;
use DecodeLabs\Prophet\Platform;
use DecodeLabs\Prophet\Platform\OpenAi\ModelPolicy;
use DecodeLabs\Prophet\Service\Medium;
use DecodeLabs\Prophet\Subject;
use DecodeLabs\Prophet\Usage;
use OpenAI\Client;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Responses\CreateResponse;
use ReflectionClass;

class OpenAi implements Platform
{
    protected ModelPolicy $modelPolicy;

    public function __construct(
        Client $client
    ) {
        $this->initialize($client);
    }

    public static function fromClientContract(
        ClientContract $client
    ): self {
        $output = (new ReflectionClass(self::class))
            ->newInstanceWithoutConstructor();

        $output->initialize($client);
        return $output;
    }

    protected ClientContract $client;

    protected function initialize(
        ClientContract $client
    ): void {
        $this->client = $client;
        $this->modelPolicy = new ModelPolicy();
    }

    public function getName(): string
    {
        return 'OpenAi';
    }

    public function supportsMedium(
        Medium $medium
    ): bool {
        return $this->modelPolicy->supportsMedium($medium);
    }

    public function getDefaultModel(
        Medium $medium
    ): string {
        return $this->modelPolicy->getDefaultModel($medium);
    }

    public function respond(
        Blueprint $blueprint,
        Subject $subject,
        GenerationOptions $options
    ): GenerationResult {
        $medium = $blueprint->getMedium();

        if (!$this->supportsMedium($medium)) {
            throw Exceptional::Runtime(
                message: 'Unsupported medium'
            );
        }

        $response = $this->client->responses()->create(
            $this->buildCreateParameters($blueprint, $subject, $options)
        );

        return $this->mapResponse(
            medium: $medium,
            model: $response->model,
            response: $response
        );
    }

    /**
     * @param Blueprint<Subject> $blueprint
     * @return array<string,mixed>
     */
    protected function buildCreateParameters(
        Blueprint $blueprint,
        Subject $subject,
        GenerationOptions $options
    ): array {
        $medium = $blueprint->getMedium();
        $output = [
            'model' => $options->model ?? $blueprint->getDefaultModel() ?? $this->getDefaultModel($medium),
            'instructions' => $instructions = trim($blueprint->getInstructions()),
            'input' => $this->normalizeInput($blueprint->generateInput($subject), $medium)
        ];

        if ($instructions === '') {
            unset($output['instructions']);
        }

        if ($options->temperature !== null) {
            $output['temperature'] = $options->temperature;
        }

        if ($options->maxOutputTokens !== null) {
            $output['max_output_tokens'] = $options->maxOutputTokens;
        }

        if ($options->user !== null) {
            $output['user'] = $options->user;
        }

        if ($medium === Medium::Json) {
            $output['text'] = [
                'format' => [
                    'type' => 'json_object'
                ]
            ];
        }

        return $output;
    }

    /**
     * @param string|array<string,mixed>|null $input
     * @return string|array<string,mixed>
     */
    protected function normalizeInput(
        string|array|null $input,
        Medium $medium
    ): string|array {
        if ($medium === Medium::Json) {
            return $this->normalizeJsonInput($input);
        }

        if ($input === null) {
            return '';
        }

        return $input;
    }

    /**
     * @param string|array<string,mixed>|null $input
     */
    protected function normalizeJsonInput(
        string|array|null $input
    ): string {
        if ($input === null) {
            return 'Return JSON.';
        }

        if (is_array($input)) {
            $input = json_encode($input, JSON_THROW_ON_ERROR);
        }

        if (stripos($input, 'json') !== false) {
            return $input;
        }

        return "Return JSON.\n\n" . $input;
    }

    protected function mapResponse(
        Medium $medium,
        string $model,
        CreateResponse $response
    ): GenerationResult {
        $text = $response->outputText;

        if ($text === null) {
            throw Exceptional::Runtime(
                message: 'OpenAI response did not contain textual output'
            );
        }

        return new GenerationResult(
            platformName: $this->getName(),
            model: $model,
            medium: $medium,
            text: $medium === Medium::Text ? $text : null,
            json: $medium === Medium::Json ? $this->decodeJsonOutput($text) : null,
            usage: $response->usage !== null ? new Usage(
                inputTokens: $response->usage->inputTokens,
                outputTokens: $response->usage->outputTokens,
                totalTokens: $response->usage->totalTokens,
                raw: $response->usage->toArray()
            ) : null,
            raw: $response
        );
    }

    /**
     * @return array<string,mixed>
     */
    protected function decodeJsonOutput(
        string $text
    ): array {
        $output = json_decode($text, true);

        if (!is_array($output)) {
            throw Exceptional::Runtime(
                message: 'OpenAI response was not valid JSON'
            );
        }

        /** @var array<string,mixed> $output */
        return $output;
    }
}
