<?php
namespace ExtendSite\Widgets;

defined('ABSPATH') || exit;

final class Register
{
    public static function init(): void
    {
        add_action('widgets_init', function () {
            register_widget(RankingWidget::class);
        });
    }
}