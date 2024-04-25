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
use DecodeLabs\Prophet\Model\RunStatus;
use DecodeLabs\Prophet\Model\Thread;
use DecodeLabs\Prophet\Platform;
use DecodeLabs\Prophet\Service\Feature;
use DecodeLabs\Prophet\Service\LanguageModelLevel;
use DecodeLabs\Prophet\Service\Medium;
use OpenAI\Client as OpenAIClient;

class OpenAi implements Platform
{
    protected OpenAIClient $client;

    public function __construct(
        OpenAIClient $client
    ) {
        $this->client = $client;
    }

    /**
     * Get platform name
     */
    public function getName(): string
    {
        return 'OpenAi';
    }

    /**
     * Check if medium is supported
     */
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

    /**
     * Check if feature is supported by medium
     */
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


    /**
     * Suggest language model based on medium and level
     */
    public function suggestModel(
        Medium $medium,
        LanguageModelLevel $level = LanguageModelLevel::Standard,
        array $features = []
    ): string {
        return match ($medium) {
            Medium::Text,
            Medium::Code => match ($level) {
                LanguageModelLevel::Basic,
                LanguageModelLevel::Standard => 'gpt-3.5-turbo',
                LanguageModelLevel::Advanced => 'gpt-4.0-turbo'
            },

            Medium::Image => 'dall-e-3',

            default => throw Exceptional::Runtime('Unsupported medium')
        };
    }


    /**
     * Create new assistant structure
     */
    public function createAssistant(
        Assistant $assistant
    ): void {
        $response = $this->client->assistants()->create([
            'name' => $assistant->getName(),
            'instructions' => $assistant->getInstructions(),
            'description' => $assistant->getDescription(),
            'model' => $assistant->getLanguageModelName() ?? 'gpt-3.5-turbo',
            'metadata' => [
                'action' => $assistant->getAction()
            ]
        ]);

        $assistant->setServiceId($response->id);
    }

    /**
     * Lookup existing assistant against action in metadata
     */
    public function findAssistant(
        Assistant $assistant
    ): bool {
        $response = $this->client->assistants()->list([
            'limit' => 50
        ]);

        $action = $assistant->getAction();

        foreach ($response->data as $result) {
            if (($result->metadata['action'] ?? null) === $action) {
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

    /**
     * Begin new thread
     */
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

        $thread->setExpiresAt(
            $response->expiresAt ?
                Carbon::createFromTimestamp($response->expiresAt) :
                null
        );

        $thread->setCompletedAt(
            $response->completedAt ?
                Carbon::createFromTimestamp($response->completedAt) :
                null
        );

        $thread->setRunId($response->id);
        $thread->setRawStatus($response->status);
        $thread->setStatus($this->normalizeStatus($response->status));
    }

    /**
     * Check to see if run has completed
     */
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

        $thread->setCompletedAt(
            $response->completedAt ?
                Carbon::createFromTimestamp($response->completedAt) :
                null
        );

        $thread->setRawStatus($response->status);
        $thread->setStatus($this->normalizeStatus($response->status));
    }

    /**
     * Fetch existing message on thread
     */
    public function fetchMessages(
        Thread $thread,
        ?string $afterId = null,
        int $limit = 20
    ): array {
        if (null === ($serviceId = $thread->getServiceId())) {
            return [];
        }

        $response = $this->client->threads()->messages()->list($serviceId, [
            'before' => $afterId,
            'limit' => $limit
        ]);

        $output = [
            'hasMore' => $response->hasMore,
            'lastMessage' => $response->lastId,
            'messages' => []
        ];

        foreach (array_reverse($response->data) as $message) {
            $output['messages'][] = [
                'id' => $message->id,
                'createdAt' => $message->createdAt,
                'role' => $message->role,
                'content' => $message->toArray()['content']
            ];
        }

        return $output;
    }

    /**
     * Send user reply message and create run
     */
    public function reply(
        Assistant $assistant,
        Thread $thread,
        string $message
    ): array {
        if (null === ($serviceId = $thread->getServiceId())) {
            throw Exceptional::InvalidArgument(
                'Thread has not been started'
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


        return [
            'id' => $messageResponse->id,
            'createdAt' => $messageResponse->createdAt,
            'role' => $messageResponse->role,
            'content' => $messageResponse->toArray()['content']
        ];
    }

    /**
     * Convert text status to RunStats
     */
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
