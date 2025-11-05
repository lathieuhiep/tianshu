<?php

namespace ExtendSite\Ajax;

use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;
use ExtendSite\Repositories\StoryRankingRepository;
use ExtendSite\Views\ViewTracker;
use WP_Post;

defined('ABSPATH') || exit;

final class LoadRanking
{
    public const ACTION = 'es_get_ranking';

    /**
     * Khởi tạo hook AJAX
     */
    public static function init(): void
    {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Xử lý AJAX request
     */
    public static function handle(): void
    {
        check_ajax_referer(EXTEND_SITE_NONCE_ACTION, 'security');

        // Nhận tham số từ JS
        $ranking_type = sanitize_key($_POST['ranking_type'] ?? StoryPostType::SLUG);
        $period = sanitize_text_field($_POST['period'] ?? 'day');
        $limit  = (int) ($_POST['limit'] ?? 10);

        // Lấy dữ liệu bảng xếp hạng
        $items = StoryRankingRepository::get_ranking_items($ranking_type, $period, $limit);

        // Kiểm tra dữ liệu
        if (empty($items)) {
            wp_send_json_error(['message' => esc_html__('Không có dữ liệu hiển thị.', 'extend-site')]);
        }

        // Trả về kết quả
        wp_send_json_success([
            'html'  => self::render_view($items, $ranking_type),
            'count' => count($items),
            'period'=> $period,
        ]);
    }

    /**
     * Render HTML (có thể tái sử dụng trong template part)
     */
    public static function render_view(array $items = [], string $type = 'story'): string
    {
        if ( empty( $items ) ) {
            return esc_html__('Chưa có dữ liệu.', 'extend-site');
        }

        ob_start();
            foreach ( $items as $index => $item ) :
                $latest_chapter = ChapterRepository::get_latest_chapter( $item['id'] );
        ?>
            <div class="ranking-item es-flex es-gap-3">
                <div class="stt">
                    <strong><?php echo esc_html( $index + 1 ); ?></strong>
                </div>

                <div class="thumbnail">
                    <a class="thumbnail__link" href="<?php echo esc_url( $item['url'] ); ?>">
                        <?php
                        if ( $item['image'] ) :
                            echo wp_get_attachment_image( $item['image'], 'medium' );
                        else:
                            ?>
                            <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                 alt="<?php echo esc_html( $item['title'] ); ?>">
                        <?php endif; ?>
                    </a>
                </div>

                <div class="detail es-flex es-flex-column gap-2">
                    <h4 class="detail__title">
                        <a href="<?php echo esc_url( $item['url'] ); ?>" class="ranking-item-title">
                            <?php echo esc_html( $item['title'] ); ?>
                        </a>
                    </h4>

                    <div class="detail__meta es-flex es-flex-justify-space-between es-flex-align-center">
                        <?php if ( $type === 'story' && !empty( $latest_chapter ) ): ?>
                            <div class="item-meta"
                                 itemprop="hasPart"
                                 itemscope
                                 itemtype="https://schema.org/Chapter"
                            >
                                <a class="item-meta-link es-flex es-flex-align-center es-gap-1"
                                   href="<?php echo esc_url( $latest_chapter['url'] ); ?>"
                                   title="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title( $item['id'] ) ) ); ?>"
                                   aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title( $item['id'] ) ) ); ?>"
                                   itemprop="url"
                                   rel="bookmark"
                                >
                                    <i class="es-ic-mask es-ic-mask-book"></i>
                                    <span itemprop="name">
                                        <?php
                                        printf(
                                            esc_html__( 'Chương %d', 'extend-site' ),
                                            intval( $latest_chapter['number'] )
                                        );
                                        ?>
                                    </span>
                                </a>
                                <meta itemprop="position" content="<?php echo intval( $latest_chapter['number'] ); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="item-meta es-flex es-flex-align-center es-gap-1">
                            <i class="es-ic-mask es-ic-mask-eye"></i>
                            <span class="view"><?php echo esc_html( ViewTracker::format_short( $item['views'] ) ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            endforeach;
        return ob_get_clean();
    }
}