<?php

declare(strict_types=1);

namespace Webfarben\DummyCopier\Service;

final class DummyCopyResult
{
    public int $copiedPages = 0;
    public int $copiedModules = 0;
    public int $copiedContent = 0;
    public int $copiedDirectories = 0;

    /** @var array<int,int> */
    public array $pageMap = [];

    /** @var array<int,int> */
    public array $moduleMap = [];

    /** @var array<string> */
    public array $notes = [];

    /** @var array<int> */
    public array $copiedContentIds = [];

    public function addNote(string $note): void
    {
        $this->notes[] = $note;
    }
}
