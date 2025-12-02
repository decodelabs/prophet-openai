# Prophet OpenAI — Package Specification

> **Cluster:** `logic`
> **Language:** `php`
> **Milestone:** `m6`
> **Repo:** `https://github.com/decodelabs/prophet-openai`
> **Role:** OpenAI API implementation

## Overview

### Purpose

Prophet OpenAI provides a Prophet `Platform` adapter for the OpenAI API, enabling AI assistant functionality using OpenAI's chat models. It enables:

- Integration with OpenAI's Assistants API
- Support for text, code, and JSON mediums
- Thread-based conversation management
- Message handling with multiple content types
- Language model selection (GPT-3.5-turbo, GPT-4o-mini, GPT-4o, DALL-E-3)
- Assistant lifecycle management (find, create, update, delete)
- Thread lifecycle management (start, refresh, delete)
- Message pagination and retrieval
- Run status tracking and normalization

Prophet OpenAI provides a seamless bridge between the Prophet assistant system and OpenAI's API.

### Non-Goals

- Prophet OpenAI does not provide authentication or API key management (delegates to OpenAI client)
- It does not handle HTTP communication (delegates to OpenAI client)
- It does not provide rate limiting or retry logic
- It does not handle cost tracking or billing
- It does not provide custom model training or fine-tuning
- It does not support all OpenAI features (functions, file attachments, etc.)
- It does not provide a user interface or chat UI

## Role in the Ecosystem

### Cluster & Positioning

Prophet OpenAI belongs to the **logic** cluster, providing OpenAI-specific implementation of the Prophet `Platform` interface. It bridges the abstract Prophet assistant system with OpenAI's concrete API.

### Usage Contexts

Prophet OpenAI is used for:

- Building AI-powered chatbots using OpenAI
- Creating code generation assistants
- Implementing structured JSON response workflows
- Managing conversation threads with OpenAI models
- Integrating OpenAI assistants into Prophet-based applications

## Public Surface

### Key Types

- **`Platform\OpenAi`** — Concrete implementation of Prophet `Platform` interface for OpenAI API. Handles assistant/thread management, message fetching, model suggestion, and run status normalization.

### Main Entry Points

- **`OpenAi::__construct(OpenAIClient $client)`** — Creates OpenAI platform instance. Requires configured OpenAI client.

- **`OpenAi::getName(): string`** — Returns "OpenAi".

- **`OpenAi::supportsMedium(Medium $medium): bool`** — Returns true for Text, Code, and Image mediums.

- **`OpenAi::supportsFeature(Medium $medium, Feature $feature): bool`** — Returns true for Chat and Thread features (Text/Code mediums). Returns true for CodeCompletion (Code medium only). Returns false for Function and file features.

- **`OpenAi::suggestModel(Medium $medium, LanguageModelLevel $level, array $features): string`** — Returns model name. Text/Json/Code: "gpt-4o-mini" (Basic/Standard) or "gpt-4o" (Advanced). Image: "dall-e-3". Throws Runtime exception for unsupported mediums.

- **`OpenAi::shouldUpdateModel(string $oldModel, string $newModel, Medium $medium, LanguageModelLevel $level, array $features): bool`** — Returns true for gpt-3.5-turbo (always update). Returns false for gpt-4o (already latest). Returns true for other models.

- **`OpenAi::findAssistant(Assistant $assistant): bool`** — Lists assistants from OpenAI (limit 50). Matches by action metadata and model. Updates assistant with service ID, name, instructions, description, and created timestamp if found. Returns true if found.

- **`OpenAi::createAssistant(Assistant $assistant): void`** — Creates assistant in OpenAI with name, instructions, description, and model. Sets response_format to json_object for Json medium. Stores action and model in metadata. Updates assistant with service ID and timestamps.

- **`OpenAi::updateAssistant(Assistant $assistant): bool`** — Returns false if service ID is null. Modifies assistant in OpenAI with updated name, instructions, description, and model. Updates model and timestamp. Returns true.

- **`OpenAi::deleteAssistant(Assistant $assistant): bool`** — Returns false if service ID is null. Deletes assistant from OpenAI. Returns true if assistant not found (already deleted). Returns deleted status from response.

- **`OpenAi::startThread(Assistant $assistant, Thread $thread, ?string $additionalInstructions): void`** — Creates and runs thread in OpenAI. Stores action in thread metadata. Updates thread with service ID, run ID, timestamps (created, updated, started, completed, expires), raw status, and normalized status.

- **`OpenAi::refreshThread(Thread $thread): void`** — Returns early if service ID or run ID is null. Retrieves run status from OpenAI. Updates thread with timestamps (updated, started, completed, expires), raw status, and normalized status.

- **`OpenAi::deleteThread(Thread $thread): bool`** — Returns false if service ID is null. Deletes thread from OpenAI. Returns true if thread not found (already deleted). Returns deleted status from response.

- **`OpenAi::fetchMessages(Thread $thread, int $limit = 20, string|int|null $after = null): MessageList`** — Returns empty MessageList if service ID is null. Lists messages from OpenAI with before (after) and limit parameters. Creates MessageList with hasMore and lastId. Reverses message order (OpenAI returns newest first). Creates Message instances via createMessage(). Returns MessageList.

- **`OpenAi::reply(Assistant $assistant, Thread $thread, string $message): Message`** — Throws InvalidArgument if service ID is null. Creates user message in thread. Creates and runs assistant in thread. Updates thread with run ID, timestamps, raw status, and normalized status. Returns user message (not assistant response).

## Dependencies

### Decode Labs

- **`exceptional`** — Used for exception handling throughout the package.

- **`prophet`** — Provides Platform interface and all model types.

### External

- **`openai-php/client`** — Official OpenAI PHP client library for API communication.

- **`nesbot/carbon`** — Used for date/time handling.

## Behaviour & Contracts

### Invariants

- Assistant finding lists up to 50 assistants from OpenAI
- Assistants are matched by action metadata and model name
- Response format is set to json_object for Json medium assistants
- Action and model are stored in OpenAI metadata
- Thread messages are reversed (OpenAI returns newest first, Prophet expects oldest first)
- Run status is normalized from OpenAI strings to RunStatus enum
- Operations requiring service ID return false or early return if null
- Delete operations return true if resource not found (idempotent)
- Reply creates user message and starts run before returning
- Refresh is no-op if service ID or run ID is null
- FallbackModel is gpt-3.5-turbo

### Input & Output Contracts

- **`OpenAi::supportsMedium(Medium $medium): bool`** — Returns true for Text, Code, Image. Returns false for Speech, Video, Audio.

- **`OpenAi::supportsFeature(Medium $medium, Feature $feature): bool`** — Returns false if medium not supported. Returns false for Image medium. Returns true for Chat and Thread features (Text/Code). Returns true for CodeCompletion (Code only). Returns false for Function feature. Returns false for all file features.

- **`OpenAi::suggestModel(Medium $medium, LanguageModelLevel $level, array $features): string`** — Returns "gpt-4o-mini" for Text/Json/Code with Basic/Standard level. Returns "gpt-4o" for Text/Json/Code with Advanced level. Returns "dall-e-3" for Image. Throws Runtime exception for other mediums.

- **`OpenAi::shouldUpdateModel(string $oldModel, string $newModel, Medium $medium, LanguageModelLevel $level, array $features): bool`** — Returns true for "gpt-3.5-turbo". Returns false for "gpt-4o". Returns true for other models.

- **`OpenAi::findAssistant(Assistant $assistant): bool`** — Calls assistants()->list(limit: 50). Matches by metadata.action === assistant.action AND (metadata.model OR model) === assistant.languageModelName. Updates assistant with name, instructions, description, service ID, and created timestamp. Returns true if found, false otherwise.

- **`OpenAi::createAssistant(Assistant $assistant): void`** — Calls assistants()->create() with name, instructions, description, model, response_format (json_object if medium is Json), and metadata (action, model). Updates assistant with service ID and current timestamps.

- **`OpenAi::updateAssistant(Assistant $assistant): bool`** — Returns false if service ID is null. Calls assistants()->modify() with name, instructions, description, model, and metadata. Updates assistant with model and updated timestamp. Returns true.

- **`OpenAi::deleteAssistant(Assistant $assistant): bool`** — Returns false if service ID is null. Calls assistants()->delete(). Catches OpenAIErrorException for "No assistant found" and returns true. Returns response.deleted.

- **`OpenAi::startThread(Assistant $assistant, Thread $thread, ?string $additionalInstructions): void`** — Calls threads()->createAndRun() with assistant_id, additional_instructions, and thread metadata (action). Updates thread with service ID, run ID, timestamps (created, updated, started, completed, expires), raw status, and normalized status.

- **`OpenAi::refreshThread(Thread $thread): void`** — Returns early if service ID or run ID is null. Calls threads()->runs()->retrieve(). Updates thread with timestamps (updated, started, completed, expires), raw status, and normalized status.

- **`OpenAi::deleteThread(Thread $thread): bool`** — Returns false if service ID is null. Calls threads()->delete(). Catches OpenAIErrorException for "No thread found" and returns true. Returns response.deleted.

- **`OpenAi::fetchMessages(Thread $thread, int $limit, string|int|null $after): MessageList`** — Returns empty MessageList if service ID is null. Calls threads()->messages()->list() with before (after) and limit. Creates MessageList with hasMore and lastId. Reverses messages (array_reverse). Creates Message instances via createMessage(). Returns MessageList.

- **`OpenAi::reply(Assistant $assistant, Thread $thread, string $message): Message`** — Throws InvalidArgument if service ID is null. Calls threads()->messages()->create() with user role and message. Calls threads()->runs()->create() with assistant_id. Updates thread with run ID, timestamps, raw status, and status. Returns user message (not assistant response).

- **`OpenAi::createMessage(ThreadMessageResponse $response, Medium $medium): Message`** — Creates Message with ID, timestamp, and role. Maps OpenAI role strings to Role enum. Creates Content\Json for Json medium, Content\Text for others. Maps content types: "text" to Text/Json, "image" to File with Image medium. Throws Runtime exception for unsupported roles or content types. Returns Message.

- **`OpenAi::normalizeStatus(?string $status): ?RunStatus`** — Maps OpenAI status strings to RunStatus enum cases. Returns null for unknown statuses.

## Error Handling

Prophet OpenAI uses the Exceptional pattern for error handling. Key exception types:

- **`Runtime`** — Thrown when unsupported medium is requested in `suggestModel()`, when unsupported role is encountered in message creation, or when unsupported content type is encountered.

- **`InvalidArgument`** — Thrown when `reply()` is called on uninitialized thread (service ID is null).

OpenAI client exceptions are propagated except for specific "not found" errors which are handled gracefully (returning true for delete operations).

## Configuration & Extensibility

### Extension Points

- **Custom Model Selection** — Override `suggestModel()` to customize model selection logic.

- **Custom Status Normalization** — Override `normalizeStatus()` to handle additional OpenAI status values.

- **Custom Message Creation** — Override `createMessage()` to handle additional content types.

- **Custom Client Configuration** — Provide configured OpenAI client with custom HTTP client, retry logic, timeout settings.

### Configuration

- **OpenAI Client Setup** — OpenAI client must be configured with API key before passing to constructor.

- **Model Selection** — Models are suggested based on medium and level. Basic/Standard → gpt-4o-mini, Advanced → gpt-4o.

- **Response Format** — JSON medium automatically sets response_format to json_object.

- **Message Pagination** — Fetch messages uses before parameter (maps to after in Prophet) with configurable limit.

- **Assistant Metadata** — Action and model are stored in OpenAI metadata for matching and tracking.

## Interactions with Other Packages

- **Prophet** — Implements `Platform` interface from Prophet package.

- **OpenAI PHP Client** — Uses official OpenAI PHP client for all API communication.

- **Carbon** — Uses Carbon for timestamp conversion and date/time handling.

- **Exceptional** — Uses Exceptional for error handling.

## Usage Examples

### Platform Setup

```php
use DecodeLabs\Prophet\Platform\OpenAi;
use OpenAI;

// Create OpenAI client
$client = OpenAI::client('your-api-key');

// Create platform
$platform = new OpenAi($client);

// Get platform name
echo $platform->getName(); // "OpenAi"
```

### Medium and Feature Support

```php
use DecodeLabs\Prophet\Service\Medium;
use DecodeLabs\Prophet\Service\Feature;

// Check medium support
$platform->supportsMedium(Medium::Text); // true
$platform->supportsMedium(Medium::Code); // true
$platform->supportsMedium(Medium::Image); // true
$platform->supportsMedium(Medium::Speech); // false

// Check feature support
$platform->supportsFeature(Medium::Text, Feature::Chat); // true
$platform->supportsFeature(Medium::Code, Feature::CodeCompletion); // true
$platform->supportsFeature(Medium::Text, Feature::Function); // false
```

### Model Selection

```php
use DecodeLabs\Prophet\Service\LanguageModelLevel;

// Suggest model
$model = $platform->suggestModel(
    medium: Medium::Text,
    level: LanguageModelLevel::Standard
);
// Returns: "gpt-4o-mini"

$model = $platform->suggestModel(
    medium: Medium::Code,
    level: LanguageModelLevel::Advanced
);
// Returns: "gpt-4o"

// Check if model should be updated
$shouldUpdate = $platform->shouldUpdateModel(
    oldModel: 'gpt-3.5-turbo',
    newModel: 'gpt-4o-mini',
    medium: Medium::Text,
    level: LanguageModelLevel::Standard
);
// Returns: true
```

### Using with Prophet

```php
use DecodeLabs\Prophet;
use DecodeLabs\Prophet\Subject\Generic;

$prophet = Monarch::getService(Prophet::class);

// Load assistant (uses OpenAI platform)
$assistant = $prophet->loadAssistant(
    blueprint: 'code-reviewer',
    serviceName: 'openai' // This triggers OpenAI platform loading
);

// Create thread
$subject = new Generic('code', 'file-123');
$thread = $prophet->loadThread('code-reviewer', $subject);

// Send message
$response = $prophet->reply($thread, 'Review this code.');

// Get response content
$text = $response->getTextContent();
```

### Direct Platform Usage

```php
// Find existing assistant
$found = $platform->findAssistant($assistant);
if ($found) {
    // Assistant service ID, name, and instructions updated
}

// Create assistant
$platform->createAssistant($assistant);
// Assistant service ID and timestamps updated

// Update assistant
$platform->updateAssistant($assistant);

// Delete assistant
$platform->deleteAssistant($assistant);
```

### Thread Management

```php
// Start thread
$platform->startThread(
    assistant: $assistant,
    thread: $thread,
    additionalInstructions: 'Focus on security issues.'
);

// Refresh thread status
$platform->refreshThread($thread);

// Check if ready
if ($thread->isReady()) {
    // Thread completed
}

// Delete thread
$platform->deleteThread($thread);
```

### Message Handling

```php
// Fetch messages
$messageList = $platform->fetchMessages(
    thread: $thread,
    limit: 20,
    after: 'msg-123'
);

// Pagination
if ($messageList->hasMore()) {
    $moreMessages = $platform->fetchMessages(
        thread: $thread,
        limit: 20,
        after: $messageList->getLast()
    );
}

// Reply to thread
$response = $platform->reply(
    assistant: $assistant,
    thread: $thread,
    message: 'Hello, assistant!'
);

// Get content
foreach ($response->getAllContent() as $content) {
    if ($content instanceof Content\Text) {
        echo $content->getContent();
    }
}
```

## Implementation Notes (for Contributors)

### Architecture

- **OpenAI Client Wrapper** — OpenAi wraps the OpenAI PHP client, translating Prophet abstractions to OpenAI API calls.

- **Model Mapping** — Language model levels are mapped to specific OpenAI models (gpt-4o-mini for Basic/Standard, gpt-4o for Advanced).

- **Status Normalization** — OpenAI status strings are normalized to Prophet RunStatus enum.

- **Message Reversal** — OpenAI returns newest messages first, which are reversed to oldest-first order.

- **Metadata Storage** — Action and model are stored in OpenAI metadata for assistant matching.

- **Response Format** — JSON medium assistants use json_object response format.

- **Content Type Mapping** — OpenAI content types ("text", "image") are mapped to Prophet Content implementations.

- **Role Mapping** — OpenAI role strings are mapped to Prophet Role enum.

- **Graceful Deletion** — Delete operations handle "not found" errors gracefully, returning true for idempotency.

### Performance Considerations

- Assistant finding is limited to 50 results
- Message fetching supports pagination via before parameter
- Lazy proxy instantiation defers assistant/thread creation
- Status refresh is lightweight (single API call)

### Design Decisions

- **Direct Client Dependency** — Requiring OpenAI client in constructor allows external configuration of HTTP client, timeout, retry logic.

- **Model Update Logic** — Automatic upgrade from gpt-3.5-turbo ensures users benefit from newer models.

- **Metadata Strategy** — Storing action and model in metadata enables assistant matching without additional Prophet-specific storage.

- **Message Reversal** — Reversing message order provides consistent oldest-first ordering across platforms.

- **JSON Response Format** — Automatically setting response_format for JSON medium enables structured output.

- **Graceful Deletion** — Handling "not found" errors ensures delete operations are idempotent.

- **User Message Return** — `reply()` returns the user message immediately, not the assistant response (which arrives asynchronously).

## Testing & Quality

**Code Quality:** 3/5 — Developing codebase with solid functionality and room for enhancement.

**README Quality:** 3/5 — Good documentation with clear description but minimal usage examples.

**Documentation:** 0/5 — No formal documentation beyond README.

**Tests:** 0/5 — No test suite currently.

See `composer.json` for supported PHP versions.

## Roadmap & Future Ideas

- Enhanced documentation and API reference
- Test suite implementation
- Usage examples in README
- Function calling support
- File attachment support (text, PDF, images)
- Vision model integration
- Streaming response support
- Error handling and retry logic
- Rate limiting and quota management
- Cost tracking integration
- Vector store and RAG support
- Custom response format handling
- Additional model support (GPT-4-turbo, etc.)
- Batch operations
- Fine-tuning integration

## References

- [Decode Labs Chorus](https://github.com/decodelabs/chorus)
- [Prophet OpenAI Repository](https://github.com/decodelabs/prophet-openai)
- [Prophet Repository](https://github.com/decodelabs/prophet)
- [OpenAI PHP Client](https://github.com/openai-php/client)
- [OpenAI API Documentation](https://platform.openai.com/docs/api-reference)

