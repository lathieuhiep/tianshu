(function ($) {
    'use strict';

    function activateTab($container, $btn) {
        var target = $btn.data('tab-target');
        if (!target || target.charAt(0) !== '#') return;

        var $panel = $container.find(target);
        if (!$panel.length) return;

        // Toggle active buttons
        $container.find('.story-tab-btn').removeClass('is-active').attr('aria-selected', 'false');
        $btn.addClass('is-active').attr('aria-selected', 'true');

        // Toggle panels
        $container.find('.story-tab-panel').removeClass('is-active').attr('hidden', 'hidden');
        $panel.addClass('is-active').removeAttr('hidden');

        // Save session per story
        var storyId = $container.data('story-id');
        if (storyId) {
            try {
                sessionStorage.setItem('storyTabs:' + storyId, target);
            } catch (e) {}
        }
    }

    function initTabs($container) {
        // init state
        $container.find('.story-tab-panel').each(function () {
            var $p = $(this);
            if ($p.hasClass('is-active')) $p.removeAttr('hidden');
            else $p.attr('hidden', 'hidden');
        });

        // click
        $container.on('click', '.story-tab-btn', function (e) {
            e.preventDefault();
            activateTab($container, $(this));
        });

        // restore last tab
        var storyId = $container.data('story-id');
        var stored = null;
        if (storyId) {
            try {
                stored = sessionStorage.getItem('storyTabs:' + storyId);
            } catch (e) {}
        }
        if (stored) {
            var $btn = $container.find('.story-tab-btn[data-tab-target="' + stored + '"]');
            if ($btn.length) activateTab($container, $btn.first());
        }
    }

    $(function () {
        $('.story-tabs').each(function () {
            initTabs($(this));
        });
    });
})(jQuery);