<?php
/**
 * SEOMate for Craft CMS 3.x
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\assets\preview;

use Craft;
use craft\helpers\Json;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use yii\web\View as ViewBase;

use vaersaagod\seomate\SEOMate;

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

            $settings = SEOMate::$plugin->getSettings();
            $previewEnabled = $settings->previewEnabled;

            if (!$this->shouldPreview($previewEnabled)) {
                return;
            }

            $previewAction = Craft::$app->getSecurity()->hashData('seomate/preview');

            $config = [
                'previewAction' => $previewAction,
                'previewLabel' => $settings->previewLabel ?: Craft::t('seomate', 'SEO Preview'),
            ];
            $configJson = Json::encode($config, JSON_UNESCAPED_UNICODE);
            $js = <<<JS
                    window.Craft.SEOMatePlugin = {$configJson};
JS;
            $view->registerJs($js, ViewBase::POS_HEAD);
        }
    }

    /**
     * Checks if we should enable live seo preview
     *
     * @param bool|array|string $previewEnabled
     *
     * @return bool
     */
    private function shouldPreview(bool|array|string $previewEnabled): bool
    {
        if ($previewEnabled === false) {
            return false;
        }

        $segments = Craft::$app->getRequest()->getSegments();
        if (empty($segments)) {
            return false;
        }

        if ($segments[0] === 'commerce') {
            \array_shift($segments);
        }

        $currentSourceHandle = \in_array($segments[0], ['entries', 'categories', 'products']) ? $segments[1] ?? null : null;
        if (!$currentSourceHandle) {
            return false;
        }

        if ($previewEnabled !== true) {
            if (!\is_array($previewEnabled)) {
                $previewEnabled = \explode(',', $previewEnabled);
            }
            if (!\in_array($currentSourceHandle, $previewEnabled, true)) {
                return false;
            }
        }

        return true;
    }

}
