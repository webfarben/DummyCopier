<?php

declare(strict_types=1);

namespace Webfarben\DummyCopier\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

final class DummyCopier
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Filesystem $filesystem,
        private readonly string $projectDir
    ) {
    }

    public function execute(DummyCopyOptions $options): DummyCopyResult
    {
        if ($options->targetParentPageId < 1) {
            throw new \InvalidArgumentException('Eine gueltige Ziel-Elternseite ist erforderlich.');
        }

        $result = new DummyCopyResult();

        if ($options->dryRun) {
            $result->copiedPages = $this->countRows('tl_page', $options->sourcePageIds);
            $result->copiedModules = $options->copyModules ? $this->countRows('tl_module', $options->sourceModuleIds) : 0;
            $result->copiedContent = $this->estimateContentCount($options);
            $result->copiedDirectories = $options->copyDirectories ? \count($options->sourceDirectories) : 0;
            $result->addNote('Dry-Run aktiv: Es wurden keine Daten geschrieben.');

            return $result;
        }

        $this->connection->beginTransaction();

        try {
            if ($options->copyModules) {
                $result->moduleMap = $this->copyModules($options->sourceModuleIds, $options->namePrefix, $result);
            }

            foreach ($options->sourcePageIds as $sourcePageId) {
                $newPageId = $this->copyPageTree($sourcePageId, $options->targetParentPageId, $options->namePrefix, $result);

                if ($options->includeContent) {
                    $this->copyArticlesAndContent($sourcePageId, $newPageId, $result->moduleMap, $result);
                }
            }

            if ($options->targetArticleId > 0) {
                foreach ($options->sourceContentIds as $sourceContentId) {
                    $this->copySingleContent($sourceContentId, $options->targetArticleId, $result->moduleMap, $result);
                }
            }

            $this->rewriteReferences($result);

            if ($options->copyDirectories) {
                $this->copyDirectories($options, $result);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $result;
    }

    /**
     * @return array<int,int>
     */
    private function copyModules(array $sourceModuleIds, string $prefix, DummyCopyResult $result): array
    {
        $map = [];

        foreach ($sourceModuleIds as $sourceId) {
            $row = $this->fetchRow('tl_module', $sourceId);

            if ($row === null) {
                $result->addNote(sprintf('Modul %d nicht gefunden, wurde uebersprungen.', $sourceId));
                continue;
            }

            unset($row['id']);
            $row['tstamp'] = time();
            $row['name'] = $this->prefixed((string) ($row['name'] ?? ('module-' . $sourceId)), $prefix);

            $newId = $this->insertRow('tl_module', $row);
            $map[$sourceId] = $newId;
            $result->copiedModules++;
        }

        return $map;
    }

    private function copyPageTree(int $sourcePageId, int $newParentId, string $prefix, DummyCopyResult $result): int
    {
        $source = $this->fetchRow('tl_page', $sourcePageId);

        if ($source === null) {
            throw new \RuntimeException(sprintf('Seite %d wurde nicht gefunden.', $sourcePageId));
        }

        unset($source['id']);
        $source['pid'] = $newParentId;
        $source['tstamp'] = time();
        $source['title'] = $this->prefixed((string) ($source['title'] ?? ('page-' . $sourcePageId)), $prefix);
        $source['alias'] = $this->makeUniqueAlias($this->prefixed((string) ($source['alias'] ?? ('page-' . $sourcePageId)), $prefix));
        $source['sorting'] = $this->nextSorting('tl_page', 'pid', $newParentId);

        $newPageId = $this->insertRow('tl_page', $source);
        $result->pageMap[$sourcePageId] = $newPageId;
        $result->copiedPages++;

        $children = $this->connection->fetchFirstColumn('SELECT id FROM tl_page WHERE pid = ? ORDER BY sorting', [$sourcePageId]);

        foreach ($children as $childId) {
            $this->copyPageTree((int) $childId, $newPageId, $prefix, $result);
        }

        return $newPageId;
    }

    /**
     * Copies articles and their content from one page to another.
     *
     * @param array<int,int> $moduleMap
     */
    private function copyArticlesAndContent(int $sourcePageId, int $targetPageId, array $moduleMap, DummyCopyResult $result): void
    {
        $articleIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_article WHERE pid = ? ORDER BY sorting', [$sourcePageId]);

        foreach ($articleIds as $articleId) {
            $articleRow = $this->fetchRow('tl_article', (int) $articleId);

            if ($articleRow === null) {
                continue;
            }

            unset($articleRow['id']);
            $articleRow['pid'] = $targetPageId;
            $articleRow['tstamp'] = time();
            $articleRow['sorting'] = $this->nextSorting('tl_article', 'pid', $targetPageId);

            $newArticleId = $this->insertRow('tl_article', $articleRow);

            $contentIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_content WHERE ptable = ? AND pid = ? ORDER BY sorting', ['tl_article', (int) $articleId]);

            foreach ($contentIds as $contentId) {
                $this->copySingleContent((int) $contentId, $newArticleId, $moduleMap, $result);
            }
        }
    }

    /**
     * @param array<int,int> $moduleMap
     */
    private function copySingleContent(int $sourceContentId, int $targetArticleId, array $moduleMap, DummyCopyResult $result): void
    {
        $contentRow = $this->fetchRow('tl_content', $sourceContentId);

        if ($contentRow === null) {
            $result->addNote(sprintf('Content %d nicht gefunden, wurde uebersprungen.', $sourceContentId));
            return;
        }

        unset($contentRow['id']);
        $contentRow['pid'] = $targetArticleId;
        $contentRow['ptable'] = 'tl_article';
        $contentRow['tstamp'] = time();
        $contentRow['sorting'] = $this->nextSorting('tl_content', 'pid', $targetArticleId, 'ptable', 'tl_article');

        if (($contentRow['type'] ?? '') === 'module') {
            $oldModule = (int) ($contentRow['module'] ?? 0);

            if (isset($moduleMap[$oldModule])) {
                $contentRow['module'] = $moduleMap[$oldModule];
            }
        }

        $newContentId = $this->insertRow('tl_content', $contentRow);
        $result->copiedContent++;
        $result->copiedContentIds[] = $newContentId;

        if (isset($contentRow['jumpTo'], $result->pageMap[(int) $contentRow['jumpTo']])) {
            $this->connection->update('tl_content', ['jumpTo' => $result->pageMap[(int) $contentRow['jumpTo']]], ['id' => $newContentId]);
        }
    }

    private function rewriteReferences(DummyCopyResult $result): void
    {
        foreach ($result->pageMap as $oldPageId => $newPageId) {
            if (isset($result->pageMap[$oldPageId])) {
                $this->connection->update(
                    'tl_page',
                    ['jumpTo' => $result->pageMap[$oldPageId]],
                    ['id' => $newPageId, 'jumpTo' => $oldPageId]
                );
            }
        }

        foreach ($result->moduleMap as $oldModuleId => $newModuleId) {
            foreach ($result->pageMap as $oldPageId => $newPageId) {
                $this->connection->update(
                    'tl_module',
                    ['jumpTo' => $newPageId],
                    ['id' => $newModuleId, 'jumpTo' => $oldPageId]
                );
            }

            if ($result->copiedContentIds === []) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, \count($result->copiedContentIds), '?'));
            $params = array_merge([$newModuleId, 'module', $oldModuleId], $result->copiedContentIds);

            // Only copied content elements are switched to their cloned modules.
            $this->connection->executeStatement(
                sprintf('UPDATE tl_content SET module = ? WHERE type = ? AND module = ? AND id IN (%s)', $placeholders),
                $params
            );
        }
    }

    private function copyDirectories(DummyCopyOptions $options, DummyCopyResult $result): void
    {
        if ($options->targetDirectory === '') {
            $result->addNote('Verzeichnisse wurden nicht kopiert: targetDirectory ist leer.');
            return;
        }

        $targetBase = $this->projectDir . '/' . ltrim($options->targetDirectory, '/');

        foreach ($options->sourceDirectories as $relativePath) {
            $sourcePath = $this->projectDir . '/' . ltrim($relativePath, '/');

            if (!is_dir($sourcePath)) {
                $result->addNote(sprintf('Verzeichnis %s nicht gefunden, wurde uebersprungen.', $relativePath));
                continue;
            }

            $folderName = basename($sourcePath);
            $targetPath = rtrim($targetBase, '/') . '/' . $this->prefixed($folderName, $options->namePrefix);

            $this->filesystem->mkdir(dirname($targetPath));
            $this->filesystem->mirror($sourcePath, $targetPath, null, ['override' => true]);
            $result->copiedDirectories++;
        }

        $result->addNote('Hinweis: Nach Dateikopien ggf. DBAFS per contao:filesync synchronisieren.');
    }

    private function nextSorting(string $table, string $pidField, int $pidValue, ?string $extraField = null, ?string $extraValue = null): int
    {
        $sql = sprintf('SELECT COALESCE(MAX(sorting), 0) FROM %s WHERE %s = ?', $table, $pidField);
        $params = [$pidValue];

        if ($extraField !== null) {
            $sql .= sprintf(' AND %s = ?', $extraField);
            $params[] = $extraValue;
        }

        $max = (int) $this->connection->fetchOne($sql, $params);

        return $max + 128;
    }

    private function makeUniqueAlias(string $baseAlias): string
    {
        $alias = $this->slugify($baseAlias);
        $counter = 1;

        while ((int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_page WHERE alias = ?', [$alias]) > 0) {
            $alias = $this->slugify($baseAlias) . '-' . $counter;
            $counter++;
        }

        return $alias;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? 'page';

        return trim($value, '-') ?: 'page';
    }

    private function prefixed(string $value, string $prefix): string
    {
        if ($prefix === '') {
            return $value;
        }

        return $prefix . $value;
    }

    private function insertRow(string $table, array $row): int
    {
        $this->connection->insert($table, $row);

        return (int) $this->connection->lastInsertId();
    }

    private function fetchRow(string $table, int $id): ?array
    {
        $row = $this->connection->fetchAssociative(sprintf('SELECT * FROM %s WHERE id = ?', $table), [$id]);

        return $row === false ? null : $row;
    }

    private function countRows(string $table, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, \count($ids), '?'));

        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE id IN (%s)', $table, $placeholders), $ids);
    }

    private function estimateContentCount(DummyCopyOptions $options): int
    {
        $count = \count($options->sourceContentIds);

        if (!$options->includeContent || $options->sourcePageIds === []) {
            return $count;
        }

        $placeholders = implode(',', array_fill(0, \count($options->sourcePageIds), '?'));
        $articleIds = $this->connection->fetchFirstColumn(sprintf('SELECT id FROM tl_article WHERE pid IN (%s)', $placeholders), $options->sourcePageIds);

        if ($articleIds === []) {
            return $count;
        }

        $articlePlaceholders = implode(',', array_fill(0, \count($articleIds), '?'));

        return $count + (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM tl_content WHERE ptable = ? AND pid IN (%s)', $articlePlaceholders),
            array_merge(['tl_article'], array_map('intval', $articleIds))
        );
    }
}
