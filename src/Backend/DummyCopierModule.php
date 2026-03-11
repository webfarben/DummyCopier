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
        $this->Template->requestToken = \defined('REQUEST_TOKEN') ? REQUEST_TOKEN : '';
        $this->Template->pageChoices = $this->getPageChoices($connection);
        $this->Template->moduleChoices = $this->getModuleChoices($connection);
        $this->Template->contentChoices = $this->getContentChoices($connection);
        $this->Template->directoryChoices = $this->getDirectoryChoices();
        $this->Template->sourcePagesWidget = '';
        $this->Template->targetParentPageWidget = '';
        $this->Template->sourceDirectoriesWidget = '';

        $targetParentPageId = $this->parseSingleIdInput(Input::postRaw('targetParentPage'));

        $this->Template->selected = [
            'sourcePages' => $this->parseIdInput(Input::postRaw('sourcePages')),
            'sourceModules' => $this->parseIdInput(Input::postRaw('sourceModules')),
            'sourceContent' => $this->parseIdInput(Input::postRaw('sourceContent')),
            'sourceDirectories' => $this->parsePathInput(Input::postRaw('sourceDirectories')),
            'targetParentPage' => $targetParentPageId,
            'targetArticle' => (int) Input::post('targetArticle'),
            'targetDirectory' => trim((string) Input::post('targetDirectory')),
            'namePrefix' => trim((string) Input::post('namePrefix')),
        ];

        if (Input::post('FORM_SUBMIT') !== 'tl_dummy_copier') {
            return;
        }

        $options = new DummyCopyOptions(
            $this->parseIdInput(Input::postRaw('sourcePages')),
            $this->parseIdInput(Input::postRaw('sourceModules')),
            $this->parseIdInput(Input::postRaw('sourceContent')),
            $this->parsePathInput(Input::postRaw('sourceDirectories')),
            $targetParentPageId,
            (int) Input::post('targetArticle'),
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
                'Fertig. Seiten: %d, Module: %d, Content: %d, Verzeichnisse: %d',
                $result->copiedPages,
                $result->copiedModules,
                $result->copiedContent,
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
     * @return array<int,string>
     */
    private function getModuleChoices(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative('SELECT id, name, type FROM tl_module ORDER BY id');
        $choices = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? 'Modul ' . $id));
            $type = trim((string) ($row['type'] ?? ''));
            $label = $type !== '' ? sprintf('%s (%s)', $name, $type) : $name;
            $choices[$id] = sprintf('%s [ID %d]', $label, $id);
        }

        return $choices;
    }

    /**
     * @return array<int,string>
     */
    private function getContentChoices(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative('SELECT id, type, pid, headline FROM tl_content ORDER BY id');
        $choices = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $type = trim((string) ($row['type'] ?? 'content'));
            $pid = (int) ($row['pid'] ?? 0);
            $headline = $this->normalizeHeadline($row['headline'] ?? null);
            $label = $headline !== '' ? sprintf('%s: %s', $type, $headline) : $type;
            $choices[$id] = sprintf('%s [ID %d, Artikel %d]', $label, $id, $pid);
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
