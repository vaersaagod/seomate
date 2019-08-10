(function($) {

    /** global: Craft */
    /** global: Garnish */
    if (!Craft || !Craft.SEOMatePlugin || !Craft.SEOMatePlugin.previewAction) {
        return;
    }

    var SEOmatePreview = Garnish.Base.extend({

        $toggleBtn: null,
        preview: null,

        init: function () {
            // Create preview btn
            var $lpBtn = $('#header .livepreviewbtn');
            if (!$lpBtn.length) {
                return;
            }
            this.$toggleBtn = $('<div class="btn seopreviewbtn">' + (Craft.SEOMatePlugin.previewLabel || Craft.t('seomate', 'SEO Preview')) + '</div>');
            this.$toggleBtn.on('click', $.proxy(this.onPreviewBtnClick, this));
            $lpBtn.after(this.$toggleBtn);
        },

        open: function () {

            if (!Craft.livePreview) {
                return;
            }

            if (this.preview) {
                this.preview.toggle();
                return;
            }

            // Get fields to display
            var fields = [].concat.apply([], Object.values(window.SEOMATE_FIELD_PROFILE || {})).map(function (handle) {
                if (handle === 'title') {
                    return '#title-field';
                }
                return '#fields-' + handle.split(':')[0] + '-field';
            }).join(',');

            this.preview = new Craft.LivePreview();
            this.preview.init($.extend({}, Craft.livePreview.settings, {
                fields: fields,
                previewAction: Craft.SEOMatePlugin.previewAction
            }));

            this.preview.on('enter', $.proxy(function () {
                var closeBtn = this.preview.$editorContainer.find('header .btn').get(0);
                if (!closeBtn) {
                    return;
                }
                $(closeBtn).text(Craft.t('seomate', 'Close SEO Preview'));
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
