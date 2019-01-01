(function($) {

    /** global: Craft */
    /** global: Garnish */
    var SEOmatePreview = Garnish.Base.extend({

        $toggleBtn: null,
        preview: null,

        init: function () {
            // Create preview btn
            var $lpBtn = $('#header .livepreviewbtn');
            if (!$lpBtn.length) {
                return;
            }
            this.$toggleBtn = $lpBtn.clone();
            this.$toggleBtn.text(Craft.t('seomate', 'SEO Preview')).removeClass('livepreviewbtn').addClass('seopreviewbtn');
            this.$toggleBtn.on('click', $.proxy(this.onPreviewBtnClick, this));
            $lpBtn.after(this.$toggleBtn);
        },

        open: function () {

            if (this.preview) {
                this.preview.toggle();
                return;
            }

            if (!Craft.livePreview) {
                return;
            }

            this.preview = new Craft.LivePreview();

            this.preview.init($.extend(Craft.livePreview.settings, {
                fields: [].concat.apply([], Object.values(window.SEOMATE_FIELD_PROFILE || {})).map(function (handle) {
                    return '#fields-' + handle.split(':')[0] + '-field';
                }).join(','),
                previewAction: 'seomate/preview'
            }));

            this.preview.on('enter', $.proxy(function () {
                this.preview.$editor.find('.btn:first-child').text(Craft.t('seomate', 'Close SEO Preview'));
            }, this));

            this.preview.toggle();
        },

        onPreviewBtnClick: function (e) {
            e.preventDefault();
            e.stopPropagation();
            this.open();
        }

    });

    Garnish.$doc.ready(function () {
        new SEOmatePreview();
    });

}(jQuery));
