<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;

/**
 * Such-Logik für Zotero-Items (LIKE-basierte Volltextsuche).
 *
 * Liegt im Service-Verzeichnis; wird von ZoteroListController bei Suchmodus genutzt.
 * Implementiert die 3-stufige Suche: exakte Phrase → Einzelbegriff → Token-Suche.
 */
final class ZoteroSearchService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ZoteroStopwordService $stopwordService,
    ) {
    }

    /**
     * @param list<int>    $libraryIds
     * @param list<string>|null $itemTypes null = alle; [] = keine Treffer; [x,y] = nur diese Typen
     * @param list<string> $searchFields Reihenfolge = Priorität (z. B. ['title', 'tags', 'abstract'])
     *
     * @return list<array<string, mixed>>
     */
    public function search(
        array $libraryIds,
        string $keywords,
        ?int $authorMemberId,
        ?int $yearFrom,
        ?int $yearTo,
        ?array $itemTypes,
        array $searchFields,
        string $tokenMode,
        int $maxTokens,
        int $maxResults,
        string $locale,
        int $offset = 0,
        bool $requireCiteContent = false
    ): array {
        if ($libraryIds === [] || $itemTypes === []) {
            return [];
        }

        $keywords = trim($keywords);
        $stopwords = $this->stopwordService->getStopwordsForLocale($locale);

        $qb = $this->connection->createQueryBuilder();
        $qb->select('i.id', 'i.pid', 'i.alias', 'i.title', 'i.year', 'i.date', 'i.publication_title', 'i.item_type', 'i.cite_content', 'i.json_data', 'i.tags')
            ->from('tl_zotero_item', 'i')
            ->where($qb->expr()->in('i.pid', ':pids'))
            ->andWhere('i.published = :published')
            ->setParameter('pids', $libraryIds, ArrayParameterType::INTEGER)
            ->setParameter('published', '1');

        if ($requireCiteContent) {
            $qb->andWhere('i.cite_content IS NOT NULL')
                ->andWhere("i.cite_content != ''");
        }

        if ($authorMemberId !== null) {
            $subQb = $this->connection->createQueryBuilder();
            $subQb->select('ic.item_id')
                ->from('tl_zotero_item_creator', 'ic')
                ->innerJoin('ic', 'tl_zotero_creator_map', 'cm', 'cm.id = ic.creator_map_id')
                ->where('cm.member_id = :author_member_id');
            $qb->andWhere($qb->expr()->in('i.id', $subQb->getSQL()))
                ->setParameter('author_member_id', $authorMemberId);
        }

        if ($yearFrom !== null || $yearTo !== null) {
            // Nur Items mit gültigem Jahr (4 Ziffern); leeres Jahr wird nicht als 0 eingestuft
            $qb->andWhere("i.year REGEXP '^(19|20)[0-9]{2}$'");
            if ($yearFrom !== null) {
                $qb->andWhere($qb->expr()->gte('CAST(i.year AS SIGNED)', ':year_from'))
                    ->setParameter('year_from', $yearFrom);
            }
            if ($yearTo !== null) {
                $qb->andWhere($qb->expr()->lte('CAST(i.year AS SIGNED)', ':year_to'))
                    ->setParameter('year_to', $yearTo);
            }
        }

        if ($itemTypes !== null && $itemTypes !== []) {
            $qb->andWhere($qb->expr()->in('i.item_type', ':item_types'))
                ->setParameter('item_types', $itemTypes, ArrayParameterType::STRING);
        }

        if ($keywords === '') {
            $qb->orderBy('i.title', 'ASC');
            if ($maxResults > 0) {
                $qb->setFirstResult($offset)->setMaxResults($maxResults);
            } elseif ($offset > 0) {
                $qb->setFirstResult($offset);
            }
            $rows = $qb->executeQuery()->fetchAllAssociative();
            return $this->formatItems($rows);
        }

        $keywordsLower = mb_strtolower($keywords);
        $hasSpaces = str_contains($keywords, ' ');

        if ($hasSpaces) {
            $tokens = $this->tokenize($keywords, $stopwords, $maxTokens);
        }

        if ($hasSpaces && \count($tokens) > 1) {
            $this->applyTokenSearch($qb, $tokens, $searchFields, $tokenMode);
        } elseif ($hasSpaces) {
            $this->applyPhraseOrSingleSearch($qb, $keywordsLower, $searchFields);
        } else {
            $this->applyPhraseOrSingleSearch($qb, $keywordsLower, $searchFields);
        }

        $qb->orderBy('score', 'DESC')->addOrderBy('i.title', 'ASC');

        if ($maxResults > 0) {
            $qb->setFirstResult($offset)->setMaxResults($maxResults);
        } elseif ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->formatItems($rows);
    }

    /**
     * Item-Typen, für die mindestens ein publiziertes Item existiert.
     *
     * @return list<string>
     */
    public function getItemTypesWithPublishedItems(): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT item_type FROM tl_zotero_item WHERE published = ? AND item_type != ? ORDER BY item_type',
            ['1', '']
        );

        return \is_array($rows) ? array_values(array_filter($rows, static fn ($v) => \is_string($v) && $v !== '')) : [];
    }

    /**
     * @return list<array{id: int, username: string|null, label: string}>
     */
    public function getMembersWithCreatorMapping(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT m.id, m.username, m.firstname, m.lastname
             FROM tl_member m
             INNER JOIN tl_zotero_creator_map cm ON cm.member_id = m.id
             WHERE cm.member_id > 0
             ORDER BY m.lastname, m.firstname'
        );

        $result = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $label = trim(($row['lastname'] ?? '') . ', ' . ($row['firstname'] ?? ''));
            if ($label === ', ') {
                $label = $row['username'] ?? 'ID ' . $id;
            }
            $result[] = [
                'id' => $id,
                'username' => $row['username'] ?? null,
                'label' => $label,
            ];
        }

        return $result;
    }


    /**
     * @return list<string>
     */
    private function tokenize(string $input, array $stopwords, int $maxTokens): array
    {
        $parts = preg_split('/\s+/u', mb_strtolower(trim($input)), -1, \PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }
        $tokens = [];
        foreach ($parts as $p) {
            $p = trim($p, ".,;:!?\"'");
            if ($p === '' || \in_array($p, $stopwords, true)) {
                continue;
            }
            $tokens[] = $p;
            if ($maxTokens > 0 && \count($tokens) >= $maxTokens) {
                break;
            }
        }

        return $tokens;
    }

    private function applyPhraseOrSingleSearch(
        \Doctrine\DBAL\Query\QueryBuilder $qb,
        string $keyword,
        array $searchFields
    ): void {
        $likeArg = '%' . addcslashes($keyword, '%_\\') . '%';
        $scoreParts = [];
        $condParts = [];

        foreach ($searchFields as $i => $field) {
            $weight = \count($searchFields) - $i;
            $col = $this->getColumnExpression($qb, $field);
            $scoreParts[] = "(CASE WHEN LOWER($col) LIKE :like_$i THEN $weight ELSE 0 END)";
            $condParts[] = "LOWER($col) LIKE :like_$i";
        }

        $qb->addSelect('(' . implode(' + ', $scoreParts) . ') AS score');
        $qb->andWhere('(' . implode(' OR ', $condParts) . ')');
        foreach ($searchFields as $i => $field) {
            $qb->setParameter("like_$i", $likeArg);
        }
    }

    /**
     * @param list<string> $tokens
     */
    private function applyTokenSearch(
        \Doctrine\DBAL\Query\QueryBuilder $qb,
        array $tokens,
        array $searchFields,
        string $tokenMode
    ): void {
        $scoreParts = [];
        $conditions = [];

        foreach ($tokens as $ti => $token) {
            $likeArg = '%' . addcslashes($token, '%_\\') . '%';
            $tokenScoreParts = [];
            $tokenCondParts = [];

            foreach ($searchFields as $fi => $field) {
                $weight = \count($searchFields) - $fi;
                $col = $this->getColumnExpression($qb, $field);
                $paramName = "t{$ti}_f{$fi}";
                $tokenScoreParts[] = "(CASE WHEN LOWER($col) LIKE :$paramName THEN $weight ELSE 0 END)";
                $tokenCondParts[] = "LOWER($col) LIKE :$paramName";
                $qb->setParameter($paramName, $likeArg);
            }

            $scoreParts[] = '(' . implode(' + ', $tokenScoreParts) . ')';
            $groupCond = '(' . implode(' OR ', $tokenCondParts) . ')';

            if ($tokenMode === 'and') {
                $conditions[] = $groupCond;
            } else {
                $conditions[] = $groupCond;
            }
        }

        $qb->addSelect('(' . implode(' + ', $scoreParts) . ') AS score');

        if ($tokenMode === 'and') {
            $qb->andWhere(implode(' AND ', $conditions));
        } else {
            $qb->andWhere(implode(' OR ', $conditions));
        }
    }

    private function getColumnExpression(\Doctrine\DBAL\Query\QueryBuilder $qb, string $field): string
    {
        return match ($field) {
            'title' => 'i.title',
            'tags' => 'i.tags',
            'abstract' => "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(i.json_data, '$.abstractNote')), '')",
            default => 'i.title',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function formatItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $jsonData = $row['json_data'] ?? '{}';
            $data = json_decode($jsonData, true);
            $items[] = [
                'id' => (int) $row['id'],
                'pid' => (int) $row['pid'],
                'alias' => $row['alias'] ?? '',
                'title' => $row['title'] ?? '',
                'year' => $row['year'] ?? '',
                'date' => $row['date'] ?? '',
                'publication_title' => $row['publication_title'] ?? '',
                'item_type' => $row['item_type'] ?? '',
                'cite_content' => $row['cite_content'] ?? '',
                'data' => \is_array($data) ? $data : [],
            ];
        }

        return $items;
    }
}
