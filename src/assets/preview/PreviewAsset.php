<?php
/**
 * NMR CP module for Craft CMS 3.x
 *
 * Control Panel tweaks for NMR
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\assets;

use Craft;
use craft\elements\User;
use craft\helpers\Json;
use craft\models\Section;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

use craft\web\View;

/**
 * @author    Værsågod
 * @package   NmrCpModule
 * @since     1.0.0
 */
class PreviewAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@vaersaagod/seomate/assets/preview/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Preview.js',
//            'js/NmrCpModule.js',
//            'js/EntryIndex.js',
        ];

        $this->css = [
            //'css/NmrCpModule.css',
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     */
//    public function registerAssetFiles($view)
//    {
//        parent::registerAssetFiles($view);
//
//        if ($view instanceof View) {
//
//            if (NmrCpModule::$instance->userIsLocal) {
//
//                // Define the Craft.NMR_CP object
//                $config = [
//                    'IS_LOCAL_EDITOR_USER' => true,
//                    'publishableSections' => $this->_publishableSections(NmrCpModule::$instance->user),
//                ];
//                $configJson = Json::encode($config, JSON_UNESCAPED_UNICODE);
//                $js = <<<JS
//                    window.Craft.NMR_CP = {$configJson};
//JS;
//                $view->registerJs($js, View::POS_HEAD);
//            };
//
//            // Fixes an issue where the CP would die due to Craft.getLocalStorage('BaseElementIndex.siteId') having invalid JSON because of reasons :(
//            $js = <<<JS
//                if (window.Craft && window.localStorage) {
//                    var key = 'Craft-' + Craft.systemUid + '.BaseElementIndex.siteId';
//                    if (localStorage[key] && !parseInt(localStorage[key], 10)) {
//                        localStorage.removeItem(key);
//                    }
//                }
//JS;
//            $view->registerJs($js, View::POS_HEAD);
//
//        }
//    }

    /**
     * @param Section $section
     * @return array
     */
//    private function _entryTypes(Section $section): array
//    {
//        $types = [];
//
//        foreach ($section->getEntryTypes() as $type) {
//            $types[] = [
//                'handle' => $type->handle,
//                'id' => (int)$type->id,
//                'name' => Craft::t('site', $type->name),
//            ];
//        }
//
//        return $types;
//    }

    /**
     * @param User $currentUser
     * @return array
     */
//    private function _publishableSections(User $currentUser): array
//    {
//        $sections = [];
//
//        foreach (Craft::$app->getSections()->getEditableSections() as $section) {
//            if ($section->type !== Section::TYPE_SINGLE && $currentUser->can('createEntries:' . $section->id)) {
//                $sections[] = [
//                    'entryTypes' => $this->_entryTypes($section),
//                    'handle' => $section->handle,
//                    'id' => (int)$section->id,
//                    'name' => Craft::t('site', $section->name),
//                    'sites' => $section->getSiteIds(),
//                    'type' => $section->type,
//                ];
//            }
//        }
//
//        return $sections;
//    }

}
