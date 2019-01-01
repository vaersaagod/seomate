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
                previewAction: 'seomate/preview'
            }));
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
