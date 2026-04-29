<?php

/**
 * Prophet OpenAI
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Prophet\Platform\OpenAi;

use DecodeLabs\Exceptional;
use DecodeLabs\Prophet\Service\Medium;

class ModelPolicy
{
    public function supportsMedium(
        Medium $medium
    ): bool {
        return match ($medium) {
            Medium::Text,
            Medium::Json => true
        };
    }

    public function getDefaultModel(
        Medium $medium
    ): string {
        if (!$this->supportsMedium($medium)) {
            throw Exceptional::Runtime(
                message: 'Unsupported medium'
            );
        }

        return 'gpt-4o-mini';
    }
}
