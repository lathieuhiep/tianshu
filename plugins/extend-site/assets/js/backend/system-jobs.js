(function ($) {
    'use strict';

    if (typeof esSystemJobs === 'undefined') {
        return;
    }

    var $body = $('#es-system-jobs-body');
    if (!$body.length) {
        return;
    }
    var pollTimer = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderJobs(jobs) {
        if (!jobs || !jobs.length) {
            $body.html('<tr><td colspan="7">' + escapeHtml(esSystemJobs.i18n.empty) + '</td></tr>');
            return;
        }

        var rows = jobs.map(function (job) {
            var percent = Math.max(0, Math.min(100, parseInt(job.percent, 10) || 0));
            var activeClass = job.is_active ? ' is-active' : '';

            return [
                '<tr>',
                '<td><code>', escapeHtml(job.id), '</code></td>',
                '<td>', escapeHtml(job.type_label), '</td>',
                '<td>', escapeHtml(job.subject_label), '</td>',
                '<td>', escapeHtml(job.status_label), '</td>',
                '<td>',
                escapeHtml(job.progress_label),
                '<div class="es-job-progress" aria-hidden="true">',
                '<div class="es-job-progress__bar', activeClass, '" style="width: ', percent, '%"></div>',
                '</div>',
                '</td>',
                '<td>', escapeHtml(job.updated_at), '</td>',
                '<td>', escapeHtml(job.message), '</td>',
                '</tr>'
            ].join('');
        });

        $body.html(rows.join(''));
    }

    function poll() {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }

        $.post(esSystemJobs.ajax_url, {
            action: esSystemJobs.action,
            nonce: esSystemJobs.nonce
        }).done(function (response) {
            if (!response || !response.success || !response.data) {
                return;
            }

            renderJobs(response.data.jobs || []);

            if (response.data.has_active_jobs) {
                pollTimer = window.setTimeout(poll, esSystemJobs.poll_interval || 5000);
            }
        });
    }

    function setPageBusy(isBusy) {
        $('.wrap button, .wrap input, .wrap select').prop('disabled', isBusy);
    }

    function scrollToJobs() {
        var target = $('#es-system-jobs');
        if (!target.length) {
            return;
        }

        $('html, body').animate({ scrollTop: Math.max(0, target.offset().top - 40) }, 260);
    }

    $('#es-status-sync-form').on('submit', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $message = $('#es-status-sync-message');
        var storyId = $form.find('[name="sync_story_id"]').val();
        var statusMode = $form.find('[name="sync_status_mode"]:checked').val() || 'story';

        $message.text(esSystemJobs.i18n.creating || '');
        setPageBusy(true);

        $.post(esSystemJobs.ajax_url, {
            action: esSystemJobs.create_status_sync_action,
            nonce: esSystemJobs.nonce,
            story_id: storyId,
            status_mode: statusMode
        }).done(function (response) {
            if (!response || !response.success || !response.data) {
                $message.text(esSystemJobs.i18n.error || '');
                return;
            }

            $message.text(response.data.message || '');
            renderJobs(response.data.jobs || []);
            scrollToJobs();

            if (response.data.has_active_jobs) {
                poll();
            }
        }).fail(function (xhr) {
            var message = esSystemJobs.i18n.error || '';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            $message.text(message);
        }).always(function () {
            setPageBusy(false);
        });
    });

    poll();
})(jQuery);
