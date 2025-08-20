<?php

/**
 * @package Songsprout API
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Prophet\Platform;

use Carbon\Carbon;
use DecodeLabs\Exceptional;
use DecodeLabs\Prophet\Model\Assistant;
use DecodeLabs\Prophet\Model\Content;
use DecodeLabs\Prophet\Model\Message;
use DecodeLabs\Prophet\Model\MessageList;
use DecodeLabs\Prophet\Model\Role;
use DecodeLabs\Prophet\Model\RunStatus;
use DecodeLabs\Prophet\Model\Thread;
use DecodeLabs\Prophet\Platform;
use DecodeLabs\Prophet\Service\Feature;
use DecodeLabs\Prophet\Service\LanguageModelLevel;
use DecodeLabs\Prophet\Service\Medium;
use OpenAI\Client as OpenAIClient;
use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Responses\Threads\Messages\ThreadMessageResponse;

class OpenAi implements Platform
{
    protected const FallbackModel = 'gpt-3.5-turbo';

    protected OpenAIClient $client;

    public function __construct(
        OpenAIClient $client
    ) {
        $this->client = $client;
    }

    public function getName(): string
    {
        return 'OpenAi';
    }

    public function supportsMedium(
        Medium $medium
    ): bool {
        return match ($medium) {
            Medium::Text,
            Medium::Code,
            Medium::Image => true,
            default => false
        };
    }

    public function supportsFeature(
        Medium $medium,
        Feature $feature
    ): bool {
        if (!$this->supportsMedium($medium)) {
            return false;
        }

        if ($medium === Medium::Image) {
            return false;
        }

        return match ($feature) {
            Feature::CodeCompletion => $medium === Medium::Code,

            Feature::Chat,
            Feature::Thread => true,

            // Todo
            Feature::Function => false,

            Feature::TextFile,
            Feature::PdfFile,
            Feature::ImageFile,
            Feature::VideoFile,
            Feature::AudioFile => false
        };
    }


    public function suggestModel(
        Medium $medium,
        LanguageModelLevel $level = LanguageModelLevel::Standard,
        array $features = []
    ): string {
        return match ($medium) {
            Medium::Text,
            Medium::Json,
            Medium::Code => match ($level) {
                LanguageModelLevel::Basic,
                LanguageModelLevel::Standard => 'gpt-4o-mini',
                LanguageModelLevel::Advanced => 'gpt-4o'
            },

            Medium::Image => 'dall-e-3',

            default => throw Exceptional::Runtime(
                message: 'Unsupported medium'
            )
        };
    }

    public function shouldUpdateModel(
        string $oldModel,
        string $newModel,
        Medium $medium,
        LanguageModelLevel $level = LanguageModelLevel::Standard,
        array $features = []
    ): bool {
        return match ($oldModel) {
            'gpt-3.5-turbo' => true,
            //'gpt-4' => $newModel === 'gpt-4o',
            'gpt-4o' => false,
            default => true
        };
    }

    public function findAssistant(
        Assistant $assistant
    ): bool {
        $response = $this->client->assistants()->list([
            'limit' => 50
        ]);

        $action = $assistant->getAction();

        foreach ($response->data as $result) {
            if (
                ($result->metadata['action'] ?? null) === $action &&
                ($result->metadata['model'] ?? $result->model) === ($assistant->getLanguageModelName() ?? self::FallbackModel)
            ) {
                if ($result->name !== null) {
                    $assistant->setName($result->name);
                }

                if ($result->instructions !== null) {
                    $assistant->setInstructions($result->instructions);
                }

                $assistant->setServiceId($result->id);
                $assistant->setDescription($result->description);
                $assistant->setCreatedAt(Carbon::createFromTimestamp($result->createdAt));
                return true;
            }
        }

        return false;
    }

    public function createAssistant(
        Assistant $assistant
    ): void {
        $isJson = $assistant->getMedium() === Medium::Json;

        $response = $this->client->assistants()->create([
            'name' => $assistant->getName(),
            'instructions' => $assistant->getInstructions(),
            'description' => $assistant->getDescription(),
            'model' => $model = $assistant->getLanguageModelName() ?? self::FallbackModel,
            'response_format' => $isJson ?
                ['type' => 'json_object'] :
                'auto',
            'metadata' => [
                'action' => $assistant->getAction(),
                'model' => $model
            ]
        ]);

        $assistant->setServiceId($response->id);
        $assistant->setCreatedAt(Carbon::now());
        $assistant->setUpdatedAt(Carbon::now());
    }

    public function updateAssistant(
        Assistant $assistant
    ): bool {
        if (null === ($serviceId = $assistant->getServiceId())) {
            return false;
        }

        $response = $this->client->assistants()->modify($serviceId, [
            'name' => $assistant->getName(),
            'instructions' => $assistant->getInstructions(),
            'description' => $assistant->getDescription() ?? '',
            'model' => $assistant->getLanguageModelName() ?? self::FallbackModel,
            'metadata' => [
                'action' => $assistant->getAction(),
                'model' => $assistant->getLanguageModelName()
            ]
        ]);

        $assistant->setLanguageModelName($response->model);
        $assistant->setUpdatedAt(Carbon::now());
        return true;
    }

    public function deleteAssistant(
        Assistant $assistant
    ): bool {
        if (null === ($serviceId = $assistant->getServiceId())) {
            return false;
        }

        try {
            $response = $this->client->assistants()->delete($serviceId);
        } catch (OpenAIErrorException $e) {
            if (str_starts_with($e->getMessage(), 'No assistant found with')) {
                return true;
            }

            throw $e;
        }

        return $response->deleted;
    }

    public function startThread(
        Assistant $assistant,
        Thread $thread,
        ?string $additionalInstructions = null
    ): void {
        $response = $this->client->threads()->createAndRun([
            'assistant_id' => $assistant->getServiceId(),
            'additional_instructions' => $additionalInstructions,
            'thread' => [
                'metadata' => [
                    'action' => $thread->getAction(),
                ]
            ]
        ]);

        $thread->setServiceId($response->threadId);
        $thread->setCreatedAt(Carbon::now());
        $thread->setUpdatedAt(Carbon::now());

        $thread->setStartedAt(
            $response->startedAt ?
                Carbon::createFromTimestamp($response->startedAt) :
                null
        );

        $thread->setCompletedAt(
            $response->completedAt ?
                Carbon::createFromTimestamp($response->completedAt) :
                null
        );

        $thread->setExpiresAt(
            $response->expiresAt ?
                Carbon::createFromTimestamp($response->expiresAt) :
                null
        );

        $thread->setRunId($response->id);
        $thread->setRawStatus($response->status);
        $thread->setStatus($this->normalizeStatus($response->status));
    }

    public function refreshThread(
        Thread $thread
    ): void {
        $serviceId = $thread->getServiceId();
        $runId = $thread->getRunId();

        if (
            $serviceId === null ||
            $runId === null
        ) {
            return;
        }

        $response = $this->client->threads()->runs()->retrieve(
            $serviceId,
            $runId
        );

        $thread->setUpdatedAt(Carbon::now());

        $thread->setStartedAt(
            $response->startedAt ?
                Carbon::createFromTimestamp($response->startedAt) :
                null
        );

        $thread->setCompletedAt(
            $response->completedAt ?
                Carbon::createFromTimestamp($response->completedAt) :
                null
        );

        $thread->setExpiresAt(
            $response->expiresAt ?
                Carbon::createFromTimestamp($response->expiresAt) :
                null
        );

        $thread->setRawStatus($response->status);
        $thread->setStatus($this->normalizeStatus($response->status));
    }

    public function deleteThread(
        Thread $thread
    ): bool {
        if (null === ($serviceId = $thread->getServiceId())) {
            return false;
        }

        try {
            $response = $this->client->threads()->delete($serviceId);
        } catch (OpenAIErrorException $e) {
            if (str_starts_with($e->getMessage(), 'No thread found with')) {
                return true;
            }

            throw $e;
        }
        return $response->deleted;
    }


    public function fetchMessages(
        Thread $thread,
        int $limit = 20,
        string|int|null $after = null
    ): MessageList {
        if (null === ($serviceId = $thread->getServiceId())) {
            return new MessageList();
        }

        $response = $this->client->threads()->messages()->list($serviceId, [
            'before' => $after,
            'limit' => $limit
        ]);

        $messageList = new MessageList(
            $response->hasMore,
            $response->lastId
        );

        $medium = $thread->getMedium();

        foreach (array_reverse($response->data) as $messageData) {
            $messageList->addMessage($this->createMessage($messageData, $medium));
        }

        return $messageList;
    }

    public function reply(
        Assistant $assistant,
        Thread $thread,
        string $message
    ): Message {
        if (null === ($serviceId = $thread->getServiceId())) {
            throw Exceptional::InvalidArgument(
                message: 'Thread has not been started'
            );
        }

        $messageResponse = $this->client->threads()->messages()->create($serviceId, [
            'role' => 'user',
            'content' => $message
        ]);

        $runResponse = $this->client->threads()->runs()->create($thread->getServiceId(), [
            'assistant_id' => $assistant->getServiceId()
        ]);

        $thread->setRunId($runResponse->id);
        $thread->setUpdatedAt(Carbon::now());
        $thread->setCompletedAt(
            $runResponse->completedAt ?
                Carbon::createFromTimestamp($runResponse->completedAt) :
                null
        );
        $thread->setRawStatus($runResponse->status);
        $thread->setStatus($this->normalizeStatus($runResponse->status));

        return $this->createMessage($messageResponse, $assistant->getMedium());
    }

    protected function createMessage(
        ThreadMessageResponse $response,
        Medium $medium
    ): Message {
        $message = new Message(
            $response->id,
            Carbon::createFromTimestamp($response->createdAt),
            match ($response->role) {
                'assistant' => Role::Assistant,
                'system' => Role::System,
                'user' => Role::User,
                default => throw Exceptional::Runtime(
                    message: 'Unsupported role'
                )
            }
        );

        $textClass = $medium === Medium::Json ?
            Content\Json::class :
            Content\Text::class;

        foreach ($response->content as $contentData) {
            $content = match ($contentData->type) {
                'text' => new $textClass(
                    /** @phpstan-ignore-next-line */
                    $contentData->text->value
                ),
                'image' => new Content\File(
                    /** @phpstan-ignore-next-line */
                    $contentData->imageFile->fileId,
                    Medium::Image
                ),
                default => throw Exceptional::Runtime(
                    message: 'Unsupported content type'
                )
            };

            $message->addContent($content);
        }

        return $message;
    }

    protected function normalizeStatus(
        ?string $status
    ): ?RunStatus {
        return match ($status) {
            'queued' => RunStatus::Queued,
            'in_progress' => RunStatus::InProgress,
            'requires_action' => RunStatus::RequiresAction,
            'cancelling' => RunStatus::Cancelling,
            'cancelled' => RunStatus::Cancelled,
            'failed' => RunStatus::Failed,
            'completed' => RunStatus::Completed,
            'expired' => RunStatus::Expired,
            default => null
        };
    }
}
