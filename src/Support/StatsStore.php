<?php

namespace NotificationManagerKit\Support;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads and writes the daily counters in nmNotificationStats. Writes are a single atomic upsert per notification
 * (MySQL/MariaDB), so concurrent page views add up correctly with no read-modify-write and no locking.
 */
final class StatsStore
{
    private const TABLE = 'nmNotificationStats';

    public function __construct(private readonly Connection $db) {}

    /**
     * The first day inside a window of $days days that ends today.
     */
    private static function since(\DateTimeInterface $now, int $days): string
    {
        return \DateTimeImmutable::createFromInterface($now)->modify('-' . (max(1, $days) - 1) . ' days')->format('Y-m-d');
    }

    public static function make(): self
    {
        return new self(app(EntityManagerInterface::class)->getConnection());
    }

    /**
     * Adds to today's counters.
     *
     * @param array<string, array{impressions: int, clicks: int, dismissals: int}> $counts notification id => counts to add
     */
    public function record(array $counts, string $language, ?\DateTimeInterface $now = null): void
    {
        $day = ($now ?? new \DateTimeImmutable())->format('Y-m-d');

        foreach ($counts as $id => $add) {
            $this->db->executeStatement(
                'INSERT INTO ' . self::TABLE . ' (notificationId, statDate, language, impressions, clicks, dismissals)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    impressions = impressions + VALUES(impressions),
                    clicks = clicks + VALUES(clicks),
                    dismissals = dismissals + VALUES(dismissals)',
                [$id, $day, $language, (int) $add['impressions'], (int) $add['clicks'], (int) $add['dismissals']],
            );
        }
    }

    /**
     * One notification's figures for the last $days days (today included), every language added together.
     */
    public function summary(string $notificationId, int $days = 30, ?\DateTimeInterface $now = null): StatsSummary
    {
        $now ??= new \DateTimeImmutable();

        $rows = $this->db->fetchAllAssociative(
            'SELECT statDate, SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(dismissals) AS dismissals
             FROM ' . self::TABLE . '
             WHERE notificationId = ? AND statDate >= ?
             GROUP BY statDate',
            [$notificationId, self::since($now, $days)],
        );

        return StatsSummary::fromRows($rows, $now, $days);
    }

    /**
     * Impressions and clicks for several notifications at once (one query), for the list.
     *
     * @param string[] $ids
     *
     * @return array<string, array{impressions: int, clicks: int}>
     */
    public function totals(array $ids, int $days = 30, ?\DateTimeInterface $now = null): array
    {
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));

        if ($ids === []) {
            return [];
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT notificationId, SUM(impressions) AS impressions, SUM(clicks) AS clicks
             FROM ' . self::TABLE . '
             WHERE statDate >= ? AND notificationId IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             GROUP BY notificationId',
            [self::since($now ?? new \DateTimeImmutable(), $days), ...$ids],
        );

        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row['notificationId']] = ['impressions' => (int) $row['impressions'], 'clicks' => (int) $row['clicks']];
        }

        return $totals;
    }

    /**
     * Deletes rows older than the retention period. Returns how many were removed.
     */
    public function prune(int $keepDays, ?\DateTimeInterface $now = null): int
    {
        return (int) $this->db->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE statDate < ?',
            [self::since($now ?? new \DateTimeImmutable(), $keepDays)],
        );
    }
}
