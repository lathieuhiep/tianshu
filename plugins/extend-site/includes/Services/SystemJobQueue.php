<?php

namespace ExtendSite\Services;

use ExtendSite\DB\SystemJobTable;

defined('ABSPATH') || exit;

class SystemJobQueue
{
    private const CRON_HOOK = 'extend_site_process_system_job';

    public static function init(): void
    {
        add_action(self::CRON_HOOK, [__CLASS__, 'process_job']);
    }

    public static function create_job(string $type, array $payload, int $total = 0): string
    {
        $job_id = self::new_job_id();
        $now = current_time('mysql');

        SystemJobTable::insert([
            'id' => $job_id,
            'type' => sanitize_key($type),
            'status' => 'pending',
            'payload' => $payload,
            'last_item_id' => 0,
            'processed' => 0,
            'total' => max(0, $total),
            'message' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        self::schedule_job($job_id);

        return $job_id;
    }

    public static function get_jobs(int $limit = 10): array
    {
        return SystemJobTable::get_all($limit);
    }

    public static function get_job(string $job_id): ?array
    {
        return SystemJobTable::get_by_key($job_id);
    }

    public static function get_formatted_jobs(): array
    {
        $formatted = [];
        foreach (self::get_jobs(10) as $job) {
            if (!is_array($job)) {
                continue;
            }

            $processed = (int) ($job['processed'] ?? 0);
            $total = (int) ($job['total'] ?? 0);
            $status = (string) ($job['status'] ?? '');
            $type = (string) ($job['type'] ?? '');
            $percent = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 0;

            $formatted[] = [
                'id' => (string) ($job['id'] ?? ''),
                'type' => $type,
                'type_label' => self::type_label($type),
                'subject_label' => self::subject_label($type, is_array($job['payload'] ?? null) ? $job['payload'] : []),
                'status' => $status,
                'status_label' => self::status_label($status),
                'processed' => $processed,
                'total' => $total,
                'progress_label' => $total > 0 ? "{$processed}/{$total}" : (string) $processed,
                'percent' => $percent,
                'updated_at' => (string) ($job['updated_at'] ?? ''),
                'message' => (string) ($job['message'] ?? ''),
                'is_active' => in_array($status, ['pending', 'running'], true),
            ];
        }

        return $formatted;
    }

    public static function has_active_jobs(): bool
    {
        return SystemJobTable::has_active_jobs();
    }

    public static function update_job(string $job_id, array $updates): void
    {
        if (!SystemJobTable::get_by_key($job_id)) {
            return;
        }

        SystemJobTable::update_by_key($job_id, array_merge($updates, [
            'updated_at' => current_time('mysql'),
        ]));
    }

    public static function schedule_job(string $job_id, int $delay = 5): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK, [$job_id])) {
            wp_schedule_single_event(time() + max(1, $delay), self::CRON_HOOK, [$job_id]);
        }
    }

    public static function process_job(string $job_id): void
    {
        $job = self::get_job($job_id);
        if (!$job || ($job['status'] ?? '') !== 'pending') {
            return;
        }

        self::update_job($job_id, ['status' => 'running']);

        /**
         * Job handlers must update the job status/progress themselves.
         *
         * @param string $job_id
         * @param array  $job
         */
        do_action('extend_site_system_job_process_' . sanitize_key((string) $job['type']), $job_id, $job);
    }

    public static function process_pending(int $limit = 3): int
    {
        $processed = 0;
        foreach (SystemJobTable::get_pending($limit) as $job_id => $job) {
            if ($processed >= $limit) {
                break;
            }

            self::process_job((string) $job_id);
            $processed++;
        }

        return $processed;
    }

    private static function new_job_id(): string
    {
        return 'job_' . str_replace('.', '', uniqid('', true));
    }

    private static function type_label(string $type): string
    {
        $labels = [
            'clone_story_chapters' => __('Clone chương truyện', 'extend-site'),
            'sync_story_chapter_status' => __('Đồng bộ trạng thái chương', 'extend-site'),
        ];

        return $labels[$type] ?? $type;
    }

    private static function subject_label(string $type, array $payload): string
    {
        if ($type === 'sync_story_chapter_status') {
            $story_id = absint($payload['story_id'] ?? 0);

            return self::story_label($story_id);
        }

        if ($type === 'clone_story_chapters') {
            $target_story_id = absint($payload['target_story_id'] ?? 0);

            return self::story_label($target_story_id);
        }

        return '';
    }

    private static function story_label(int $story_id): string
    {
        if ($story_id <= 0) {
            return '';
        }

        $title = get_the_title($story_id);

        return $title !== '' ? $title : '#' . $story_id;
    }

    private static function status_label(string $status): string
    {
        $labels = [
            'pending' => __('Đang chờ', 'extend-site'),
            'running' => __('Đang chạy', 'extend-site'),
            'done' => __('Hoàn tất', 'extend-site'),
            'failed' => __('Lỗi', 'extend-site'),
            'cancelled' => __('Đã hủy', 'extend-site'),
        ];

        return $labels[$status] ?? $status;
    }
}
