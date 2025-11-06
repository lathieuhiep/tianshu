<?php
namespace ExtendSite\Widgets;

defined('ABSPATH') || exit;

final class Register
{
    public static function init(): void
    {
        add_action('widgets_init', function () {
            foreach ([
                RankingWidget::class,
                SearchStoryWidget::class,
            ] as $widget_class
            ) {
                register_widget($widget_class);
            }
        });
    }
}