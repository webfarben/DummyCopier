<?php

declare(strict_types=1);

namespace Webfarben\DummyCopier\Service;

final class DummyCopyOptions
{
    public function __construct(
        public readonly array $sourcePageIds,
        public readonly array $sourceModuleIds,
        public readonly array $sourceContentIds,
        public readonly array $sourceDirectories,
        public readonly int $targetParentPageId,
        public readonly int $targetArticleId,
        public readonly string $targetDirectory,
        public readonly string $namePrefix,
        public readonly bool $includeContent,
        public readonly bool $copyModules,
        public readonly bool $copyDirectories,
        public readonly bool $dryRun
    ) {
    }
}
