<?php
/**
 * SEOMate for Craft CMS 3.x
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\seomate\assets\preview;

use Craft;
use craft\helpers\Json;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use vaersaagod\seomate\SEOMate;

use yii\web\View as ViewBase;

/**
 * @author    Værsågod
 * @since     1.0.0
 */
class PreviewAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@vaersaagod/seomate/assets/preview/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Preview.js',
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {


            if (!$this->shouldPreview()) {
                return;
            }

            $previewAction = Craft::$app->getSecurity()->hashData('seomate/preview');

            $settings = SEOMate::$plugin->getSettings();

            $config = [
                'previewAction' => $previewAction,
                'previewLabel' => $settings->previewLabel ?: Craft::t('seomate', 'SEO Preview'),
            ];
            $configJson = Json::encode($config, JSON_UNESCAPED_UNICODE);
            $js = sprintf('window.Craft.SEOMatePlugin = %s;', $configJson);
            $view->registerJs($js, ViewBase::POS_HEAD);
        }
    }

    /**
     * Checks if we should enable live seo preview
     *
     *
     */
    private function shouldPreview(): bool
    {

        $settings = SEOMate::$plugin->getSettings();
        $previewEnabled = $settings->previewEnabled;
        if (!$previewEnabled) {
            return false;
        }

        $segments = Craft::$app->getRequest()->getSegments();
        if (empty($segments)) {
            return false;
        }

        if ($segments[0] === 'commerce') {
            array_shift($segments);
        }

        $currentSourceHandle = in_array($segments[0], ['entries', 'categories', 'products']) ? $segments[1] ?? null : null;
        if (!$currentSourceHandle) {
            return false;
        }

        if (!is_bool($previewEnabled)) {
            if (!is_array($previewEnabled)) {
                $previewEnabled = \explode(',', preg_replace('/\s+/', '', $previewEnabled));
            }
            if (!in_array($currentSourceHandle, $previewEnabled, true)) {
                return false;
            }
        }

        return true;
    }
}
