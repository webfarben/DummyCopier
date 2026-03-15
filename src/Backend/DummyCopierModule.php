<?php

declare(strict_types=1);

namespace Webfarben\DummyCopier\Backend;

use Webfarben\DummyCopier\Service\DummyCopier;
use Webfarben\DummyCopier\Service\DummyCopyOptions;
use Contao\BackendModule;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

class DummyCopierModule extends BackendModule
{
    protected $strTemplate = 'be_dummy_copier';

    protected function compile(): void
    {
        /** @var Connection $connection */
        $connection = System::getContainer()->get('database_connection');

        $this->Template->action = Environment::get('request');
        $this->Template->requestToken = $this->getCsrfToken();
        $this->Template->pageChoices = $this->getPageChoices($connection);
        $this->Template->pageTreeNodes = $this->getPageTreeNodes($connection);
        $this->Template->moduleChoices = $this->getModuleChoices($connection);
        $this->Template->newsArchiveChoices = $this->getNewsArchiveChoices($connection);
        $this->Template->calendarChoices = $this->getCalendarChoices($connection);
        $this->Template->directoryChoices = $this->getDirectoryChoices();

        $targetParentPageId = $this->parseSingleIdInput(Input::postRaw('targetParentPage'));
        $isPost = Input::post('FORM_SUBMIT') === 'tl_dummy_copier';

        $this->Template->selected = [
            'sourcePages' => $this->parseIdInput(Input::postRaw('sourcePages')),
            'sourceModules' => $this->parseIdInput(Input::postRaw('sourceModules')),
            'sourceNewsArchives' => $this->parseIdInput(Input::postRaw('sourceNewsArchives')),
            'sourceCalendars' => $this->parseIdInput(Input::postRaw('sourceCalendars')),
            'sourceDirectories' => $this->parsePathInput(Input::postRaw('sourceDirectories')),
            'targetParentPage' => $targetParentPageId,
            'targetDirectory' => trim((string) Input::post('targetDirectory')),
            'namePrefix' => trim((string) Input::post('namePrefix')),
            'includeContent'    => !$isPost || (bool) Input::post('includeContent'),
            'copyModules'       => !$isPost || (bool) Input::post('copyModules'),
            'copyDirectories'   => $isPost && (bool) Input::post('copyDirectories'),
            'dryRun'            => $isPost && (bool) Input::post('dryRun'),
        ];

        if (!$isPost) {
            return;
        }

        $options = new DummyCopyOptions(
            $this->parseIdInput(Input::postRaw('sourcePages')),
            $this->parseIdInput(Input::postRaw('sourceModules')),
            $this->parseIdInput(Input::postRaw('sourceNewsArchives')),
            $this->parseIdInput(Input::postRaw('sourceCalendars')),
            [],
            $this->parsePathInput(Input::postRaw('sourceDirectories')),
            $targetParentPageId,
            0,
            trim((string) Input::post('targetDirectory')),
            trim((string) Input::post('namePrefix')),
            (bool) Input::post('includeContent'),
            (bool) Input::post('copyModules'),
            (bool) Input::post('copyDirectories'),
            (bool) Input::post('dryRun')
        );

        try {
            $copier = new DummyCopier(
                $connection,
                new Filesystem(),
                (string) System::getContainer()->getParameter('kernel.project_dir')
            );
            $result = $copier->execute($options);

            Message::addConfirmation(sprintf(
                'Fertig. Seiten: %d, Module: %d, Content: %d, Newsarchive: %d, Newsbeitraege: %d, Kalender: %d, Events: %d, Verzeichnisse: %d',
                $result->copiedPages,
                $result->copiedModules,
                $result->copiedContent,
                $result->copiedNewsArchives,
                $result->copiedNewsItems,
                $result->copiedCalendars,
                $result->copiedEvents,
                $result->copiedDirectories
            ));

            $this->Template->result = $result;
        } catch (\Throwable $exception) {
            Message::addError($exception->getMessage());
        }
    }

    private function parseIdInput($input): array
    {
        if (\is_array($input)) {
            return array_values(array_filter(array_map('intval', $input), static fn (int $id): bool => $id > 0));
        }

        $csv = trim((string) $input);

        if ($csv === '') {
            return [];
        }

        $deserialized = StringUtil::deserialize($csv, true);

        if ($deserialized !== [] && $deserialized !== [$csv]) {
            return array_values(array_filter(array_map('intval', $deserialized), static fn (int $id): bool => $id > 0));
        }

        $parts = array_filter(array_map('trim', explode(',', $csv)), static fn (string $value): bool => $value !== '');

        return array_values(array_filter(array_map('intval', $parts), static fn (int $id): bool => $id > 0));
    }

    private function parsePathInput($input): array
    {
        if (\is_array($input)) {
            return array_values(array_filter(array_map('trim', $input), static fn (string $value): bool => $value !== ''));
        }

        $csv = trim((string) $input);

        if ($csv === '') {
            return [];
        }

        $deserialized = StringUtil::deserialize($csv, true);

        if ($deserialized !== [] && $deserialized !== [$csv]) {
            return array_values(array_filter(array_map('trim', $deserialized), static fn (string $value): bool => $value !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $value): bool => $value !== ''));
    }

    private function parseSingleIdInput($input): int
    {
        $ids = $this->parseIdInput($input);

        return $ids[0] ?? 0;
    }

    private function getCsrfToken(): string
    {
        $container = System::getContainer();

        // Contao 5: use Symfony CSRF token manager
        if ($container->has('contao.csrf.token_manager')) {
            return $container
                ->get('contao.csrf.token_manager')
                ->getToken((string) $container->getParameter('contao.csrf_token_name'))
                ->getValue();
        }

        // Contao 4 fallback
        if (\defined('REQUEST_TOKEN')) {
            return REQUEST_TOKEN;
        }

        return '';
    }

    /**
     * @return array<int,string>
     */
    private function getPageChoices(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative('SELECT id, pid, title, alias FROM tl_page ORDER BY sorting, id');
        $rowsByParent = [];

        foreach ($rows as $row) {
            $pid = (int) ($row['pid'] ?? 0);
            $rowsByParent[$pid][] = $row;
        }

        $choices = [];

        $build = function (int $pid, int $depth) use (&$build, &$choices, $rowsByParent): void {
            foreach ($rowsByParent[$pid] ?? [] as $row) {
                $id = (int) ($row['id'] ?? 0);

                if ($id < 1) {
                    continue;
                }

                $title = trim((string) ($row['title'] ?? ''));
                $alias = trim((string) ($row['alias'] ?? ''));
                $label = $title !== '' ? $title : 'Seite ' . $id;

                if ($alias !== '') {
                    $label .= ' (' . $alias . ')';
                }

                $indent = str_repeat('  ', max(0, $depth));
                $choices[$id] = sprintf('%s%s [ID %d]', $indent, $label, $id);
                $build($id, $depth + 1);
            }
        };

        $build(0, 0);

        // Fallback for non-rooted records that were not visited from pid=0.
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id < 1 || isset($choices[$id])) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $alias = trim((string) ($row['alias'] ?? ''));
            $label = $title !== '' ? $title : 'Seite ' . $id;

            if ($alias !== '') {
                $label .= ' (' . $alias . ')';
            }

            $choices[$id] = sprintf('%s [ID %d]', $label, $id);
        }

        return $choices;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getPageTreeNodes(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative('SELECT id, pid, title, alias FROM tl_page ORDER BY sorting, id');
        $rowsByParent = [];

        foreach ($rows as $row) {
            $pid = (int) ($row['pid'] ?? 0);
            $rowsByParent[$pid][] = $row;
        }

        $build = function (int $pid) use (&$build, $rowsByParent): array {
            $nodes = [];

            foreach ($rowsByParent[$pid] ?? [] as $row) {
                $id = (int) ($row['id'] ?? 0);

                if ($id < 1) {
                    continue;
                }

                $title = trim((string) ($row['title'] ?? ''));
                $alias = trim((string) ($row['alias'] ?? ''));
                $label = $title !== '' ? $title : ('Seite ' . $id);

                if ($alias !== '') {
                    $label .= ' (' . $alias . ')';
                }

                $nodes[] = [
                    'id' => $id,
                    'label' => $label,
                    'children' => $build($id),
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    /**
     * @return array<int,string>
     */
    private function getModuleChoices(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT m.id, m.name, m.type, t.name AS theme_name
             FROM tl_module m
             LEFT JOIN tl_theme t ON t.id = m.pid
             ORDER BY t.name, m.type, m.name'
        );
        $choices = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? 'Modul ' . $id));
            $type = trim((string) ($row['type'] ?? ''));
            $theme = trim((string) ($row['theme_name'] ?? ''));
            $label = $theme !== '' ? sprintf('[%s] %s (%s)', $theme, $name, $type) : sprintf('%s (%s)', $name, $type);
            $choices[$id] = sprintf('%s [ID %d]', $label, $id);
        }

        return $choices;
    }

    /**
     * @return array<string,string>
     */
    private function getDirectoryChoices(): array
    {
        $projectDir = (string) System::getContainer()->getParameter('kernel.project_dir');
        $filesDir = $projectDir . '/files';

        if (!is_dir($filesDir)) {
            return [];
        }

        $choices = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($filesDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isDir()) {
                continue;
            }

            $fullPath = $item->getPathname();
            $relative = str_replace($projectDir . '/', '', $fullPath);
            $trimmed = trim((string) str_replace('files/', '', $relative), '/');
            $depth = $trimmed === '' ? 0 : substr_count($trimmed, '/');
            $indent = str_repeat('  ', max(0, $depth));
            $choices[$relative] = $indent . $relative;
        }

        ksort($choices);

        return $choices;
    }

    /**
     * @return array<int,string>
     */
    private function getNewsArchiveChoices(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative('SELECT id, title FROM tl_news_archive ORDER BY title');
        $choices = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $choices[$id] = sprintf('%s [ID %d]', $title !== '' ? $title : ('Newsarchiv ' . $id), $id);
        }

        return $choices;
    }

    /**
     * @return array<int,string>
     */
    private function getCalendarChoices(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative('SELECT id, title FROM tl_calendar ORDER BY title');
        $choices = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $choices[$id] = sprintf('%s [ID %d]', $title !== '' ? $title : ('Kalender ' . $id), $id);
        }

        return $choices;
    }

    private function normalizeHeadline($headline): string
    {
        if (\is_string($headline)) {
            return trim($headline);
        }

        if (!\is_array($headline) || !isset($headline['value']) || !\is_string($headline['value'])) {
            return '';
        }

        return trim($headline['value']);
    }
}
