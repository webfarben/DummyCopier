<?php

declare(strict_types=1);

namespace Webfarben\DummyCopier\Service;

final class DummyCopyResult
{
    public int $copiedPages = 0;
    public int $copiedModules = 0;
    public int $copiedContent = 0;
    public int $copiedDirectories = 0;
    public int $copiedNewsArchives = 0;
    public int $copiedNewsItems = 0;
    public int $copiedCalendars = 0;
    public int $copiedEvents = 0;

    /** @var array<int,int> */
    public array $pageMap = [];

    /** @var array<int,int> */
    public array $moduleMap = [];

    /** @var array<int,int> */
    public array $contentMap = [];

    /** @var array<int,int> */
    public array $newsArchiveMap = [];

    /** @var array<int,int> */
    public array $calendarMap = [];

    /** @var array<int,int> */
    public array $newsItemMap = [];

    /** @var array<int,int> */
    public array $eventMap = [];

    /** @var array<string> */
    public array $notes = [];

    /** @var array<int> */
    public array $copiedContentIds = [];

    public function addNote(string $note): void
    {
        $this->notes[] = $note;
    }
}
