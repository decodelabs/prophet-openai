<?php

declare(strict_types=1);

namespace DecodeLabs\Prophet\Platform\Tests;

use DecodeLabs\Exceptional\Exception as ExceptionalException;
use DecodeLabs\Prophet\Blueprint;
use DecodeLabs\Prophet\BlueprintTrait;
use DecodeLabs\Prophet\GenerationOptions;
use DecodeLabs\Prophet\Platform\OpenAi;
use DecodeLabs\Prophet\Service\Medium;
use DecodeLabs\Prophet\Subject;
use OpenAI\Resources\Responses as ResponsesResource;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Testing\ClientFake;
use OpenAI\Testing\Enums\OverrideStrategy;
use PHPUnit\Framework\TestCase;

class OpenAiTest extends TestCase
{
    public function testSupportsExpectedMediums(): void
    {
        $platform = OpenAi::fromClientContract(new ClientFake([
            CreateResponse::fake()
        ]));

        self::assertTrue($platform->supportsMedium(Medium::Text));
        self::assertTrue($platform->supportsMedium(Medium::Json));
    }

    public function testDefaultModelUsesPlatformFallback(): void
    {
        $platform = OpenAi::fromClientContract(new ClientFake([
            CreateResponse::fake()
        ]));

        self::assertSame('gpt-4o-mini', $platform->getDefaultModel(Medium::Text));
    }

    public function testRespondUsesResponsesApiWithInstructionsAndInput(): void
    {
        $client = new ClientFake([
            CreateResponse::fake([
                'model' => 'gpt-4o-mini',
                'output' => [
                    [
                        'id' => 'msg_1',
                        'type' => 'message',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'Latest answer',
                                'annotations' => [],
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 11,
                    'input_tokens_details' => [
                        'cached_tokens' => 0,
                    ],
                    'output_tokens' => 7,
                    'output_tokens_details' => [
                        'reasoning_tokens' => 0,
                    ],
                    'total_tokens' => 18,
                ],
            ], strategy: OverrideStrategy::Replace)
        ]);

        $platform = OpenAi::fromClientContract($client);
        $result = $platform->respond(
            new TestTextBlueprint(),
            new TestSubject('subject-1'),
            new GenerationOptions(temperature: 0.4, maxOutputTokens: 500, user: 'user-1')
        );

        $client->assertSent(ResponsesResource::class, function (string $method, array $payload): bool {
            return $method === 'create' &&
                $payload['model'] === 'gpt-4o-mini' &&
                $payload['instructions'] === 'Base instructions' &&
                $payload['input'] === 'Subject=subject-1' &&
                $payload['temperature'] === 0.4 &&
                $payload['max_output_tokens'] === 500 &&
                $payload['user'] === 'user-1';
        });

        self::assertSame('Latest answer', $result->text);
        self::assertSame(18, $result->usage?->totalTokens);
    }

    public function testRespondHonorsExplicitModelOverride(): void
    {
        $client = new ClientFake([
            CreateResponse::fake([
                'model' => 'gpt-5-mini'
            ], strategy: OverrideStrategy::Replace)
        ]);

        $platform = OpenAi::fromClientContract($client);
        $result = $platform->respond(
            new TestTextBlueprint(),
            new TestSubject('subject-1'),
            new GenerationOptions(model: 'gpt-5-mini')
        );

        $client->assertSent(ResponsesResource::class, function (string $method, array $payload): bool {
            return $method === 'create' &&
                $payload['model'] === 'gpt-5-mini';
        });

        self::assertSame('gpt-5-mini', $result->model);
    }

    public function testJsonResponsesAreParsedAndRequestJsonMode(): void
    {
        $client = new ClientFake([
            CreateResponse::fake([
                'model' => 'gpt-4o-mini',
                'output' => [
                    [
                        'id' => 'msg_1',
                        'type' => 'message',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => '{"answer":"ok"}',
                                'annotations' => [],
                            ],
                        ],
                    ],
                ],
            ], strategy: OverrideStrategy::Replace)
        ]);

        $platform = OpenAi::fromClientContract($client);
        $result = $platform->respond(
            new TestJsonBlueprint(),
            new TestSubject('subject-1'),
            new GenerationOptions()
        );

        $client->assertSent(ResponsesResource::class, function (string $method, array $payload): bool {
            return $method === 'create' &&
                $payload['text']['format']['type'] === 'json_object' &&
                is_string($payload['input']) &&
                str_contains($payload['input'], 'JSON');
        });

        self::assertSame(['answer' => 'ok'], $result->json);
    }

    public function testStructuredInputPassesThrough(): void
    {
        $client = new ClientFake([
            CreateResponse::fake([
                'model' => 'gpt-4o-mini'
            ], strategy: OverrideStrategy::Replace)
        ]);

        $platform = OpenAi::fromClientContract($client);
        $platform->respond(
            new TestStructuredBlueprint(),
            new TestSubject('subject-1'),
            new GenerationOptions()
        );

        $client->assertSent(ResponsesResource::class, function (string $method, array $payload): bool {
            return $method === 'create' &&
                $payload['input'] === ['subject' => 'subject-1'];
        });
    }

    public function testInvalidJsonResponseFailsFast(): void
    {
        $client = new ClientFake([
            CreateResponse::fake([
                'model' => 'gpt-4o-mini',
                'output' => [
                    [
                        'id' => 'msg_1',
                        'type' => 'message',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'not json',
                                'annotations' => [],
                            ],
                        ],
                    ],
                ],
            ], strategy: OverrideStrategy::Replace)
        ]);

        $platform = OpenAi::fromClientContract($client);

        $this->expectException(ExceptionalException::class);
        $this->expectExceptionMessage('OpenAI response was not valid JSON');

        $platform->respond(
            new TestJsonBlueprint(),
            new TestSubject('subject-1'),
            new GenerationOptions()
        );
    }
}

class TestSubject implements Subject
{
    public function __construct(
        private string $id
    ) {
    }

    public function getSubjectType(): string
    {
        return 'demo';
    }

    public function getSubjectId(): ?string
    {
        return $this->id;
    }
}

/**
 * @implements Blueprint<Subject>
 */
class TestTextBlueprint implements Blueprint
{
    use BlueprintTrait;

    public function getInstructions(): string
    {
        return 'Base instructions';
    }

    public function generateInput(
        Subject $subject
    ): string|array|null {
        return 'Subject=' . ($subject->getSubjectId() ?? 'none');
    }
}

/**
 * @implements Blueprint<Subject>
 */
class TestJsonBlueprint implements Blueprint
{
    use BlueprintTrait;

    public function getInstructions(): string
    {
        return 'Return JSON';
    }

    public function getMedium(): Medium
    {
        return Medium::Json;
    }
}

/**
 * @implements Blueprint<Subject>
 */
class TestStructuredBlueprint implements Blueprint
{
    use BlueprintTrait;

    public function getInstructions(): string
    {
        return 'Base instructions';
    }

    public function generateInput(
        Subject $subject
    ): string|array|null {
        return [
            'subject' => $subject->getSubjectId()
        ];
    }
}
