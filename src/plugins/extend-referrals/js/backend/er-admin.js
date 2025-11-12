(function ($) {
    'use strict';

    // Quản lý quảng cáo liên kết trong trang admin
    const AffiliateAdsAdmin = {
        init() {
            this.list = $('#affiliate-ads-list');
            this.addBtn = $('.add-ad');
            this.toggleAllBtn = $('.toggle-all-ads');
            this.template = $('#affiliate-ad-template').html();
            this.textEnable = $('#text-enable-all').val();
            this.textDisable = $('#text-disable-all').val();

            this.bindEvents();
            this.updateToggleAllVisibility();
        },

        bindEvents() {
            const self = this;

            self.addBtn.on('click', () => {
                self.addAd();
                self.updateToggleAllVisibility();
            });

            self.list.on('click', '.remove-ad', function () {
                self.removeAd($(this));
            });

            self.toggleAllBtn.on('click', () => self.toggleAll());

            // Khi bật/tắt từng quảng cáo
            self.list.on('change', '.ad-toggle input', function () {
                self.toggleAdInputs($(this));
            });
        },

        addAd() {
            const index = this.list.find('.ad-item').length;
            const newItem = this.template.replace(/__INDEX__/g, index);
            this.list.append(newItem);
            this.updateToggleAllVisibility();
        },

        removeAd(button) {
            const ad = button.closest('.ad-item');
            ad.fadeOut(200, () => {
                ad.remove();
                this.updateToggleAllVisibility();
            });
        },

        toggleAll() {
            const checkboxes = this.list.find('.ad-toggle input[type="checkbox"]');
            const anyActive = checkboxes.filter(':checked').length > 0;
            const newState = !anyActive;
            checkboxes.prop('checked', newState);

            // Đồng bộ disable input cho từng item
            checkboxes.each((_, el) => this.toggleAdInputs($(el)));

            this.toggleAllBtn.text(newState ? this.textDisable : this.textEnable);
        },

        toggleAdInputs(checkbox) {
            const adItem = checkbox.closest('.ad-item');
            const inputs = adItem.find('.ad-fields input');
            const isActive = checkbox.is(':checked');

            // Với các input text, url, number: chuyển sang readonly
            inputs.each(function () {
                const type = $(this).attr('type');
                if (['text', 'url', 'number', 'hidden'].includes(type)) {
                    $(this).prop('readonly', !isActive);
                }
            });

            // Hiệu ứng mờ toàn khối
            adItem.toggleClass('ad-item--inactive', !isActive);
        },

        updateToggleAllVisibility() {
            const count = this.list.find('.ad-item').length;
            if (count === 0) {
                this.toggleAllBtn.hide();
            } else {
                this.toggleAllBtn.show();
            }
        },
    };

    $(document).ready(() => AffiliateAdsAdmin.init());

    // --- Mở khung chọn ảnh ---
    const openMediaFrame = (e) => {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            alert('Media library không khả dụng.');
            return;
        }

        const $button   = $(e.currentTarget);
        const $field    = $button.closest('.ad-image-field');
        const $input    = $field.find('.ad-image-url');
        const $inputId  = $field.find('.ad-image-id');
        const $preview  = $field.find('.ad-image-preview');

        const frame = wp.media({
            frame: 'select',
            state: 'library',
            title: 'Chọn ảnh quảng cáo',
            library: { type: 'image' },
            button: { text: 'Sử dụng ảnh này' },
            multiple: false
        });

        // Focus lại ảnh cũ
        frame.on('open', () => {
            const id = parseInt($inputId.val(), 10);
            if (!id) return;
            const selection = frame.state().get('selection');
            const attachment = wp.media.attachment(id);
            if (attachment) {
                attachment.fetch();
                selection.add(attachment);
            }
        });

        // Khi người dùng chọn ảnh
        frame.on('select', () => {
            const selection = frame.state().get('selection');
            if (!selection) return;
            const attachment = selection.first().toJSON();

            $input.val(attachment.url).trigger('change');
            $inputId.val(attachment.id);
            $preview.html(`<img src="${attachment.url}" alt="" style="max-height:60px;border:1px solid #ccc;border-radius:4px;">`);
        });

        frame.open();
    };

    // --- Gắn sự kiện chọn ảnh ---
    $(document).on('click', '.select-ad-image', openMediaFrame);

    // --- Cập nhật preview khi paste URL thủ công ---
    $(document).on('change', '.ad-image-url', function () {
        const $input   = $(this);
        const $field   = $input.closest('.ad-image-field');
        const $inputId = $field.find('.ad-image-id');
        const $preview = $field.find('.ad-image-preview');
        const url      = $input.val().trim();

        if (url) {
            $preview.html(`<img src="${url}" alt="" style="max-height:60px;border:1px solid #ccc;border-radius:4px;">`);
            if (typeof ExtendReferrals !== 'undefined' && ExtendReferrals.uploadsBaseUrl) {
                if (!url.includes(ExtendReferrals.uploadsBaseUrl)) {
                    $inputId.val('');
                }
            }
        } else {
            $preview.empty();
            $inputId.val('');
        }
    });

    // --- Form quy tắc hiển thị ---
    const initDisplayRulesForm = () => {
        const $form = $('.er-display-rules-form');
        if (!$form.length) return;

        const $checkboxes = $form.find('input[type="checkbox"]');
        const $selectAllBtn = $form.find('.er-select-all');
        const $unselectAllBtn = $form.find('.er-unselect-all');

        $selectAllBtn.on('click', (e) => {
            e.preventDefault();
            $checkboxes.prop('checked', true);
        });

        $unselectAllBtn.on('click', (e) => {
            e.preventDefault();
            $checkboxes.prop('checked', false);
        });
    };
    initDisplayRulesForm();

})(jQuery);
