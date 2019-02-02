<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * -
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\controllers;

use Craft;
use craft\controllers\BaseEntriesController;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use craft\web\Response;
use craft\web\View;

use DateTime;

use vaersaagod\seomate\SEOMate;


/**
 * Sitemap Controller
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class PreviewController extends BaseEntriesController
{

    /**
     * Previews an element.
     *
     * @return Response
     * @throws NotFoundHttpException if the requested entry version cannot be found
     */
    public function actionIndex(): Response
    {

        $this->requirePostRequest();
        $entryId = Craft::$app->getRequest()->getParam('entryId');

        if ($entryId) {
            $entry = Craft::$app->getEntries()->getEntryById($entryId);
        } else {
            $entry = $this->_getEntryModel();
        }

        $this->enforceEditEntryPermissions($entry);

        // Set the language to the user's preferred language so DateFormatter returns the right format
        Craft::$app->updateTargetLanguage(true);

        $this->_populateEntryModel($entry);
        return $this->_showEntry($entry);
    }

    /**
     * Fetches or creates an Entry.
     *
     * @return Entry
     * @throws NotFoundHttpException if the requested entry cannot be found
     */
    private function _getEntryModel(): Entry
    {
        $request = Craft::$app->getRequest();
        $entryId = $request->getBodyParam('entryId');
        $siteId = $request->getBodyParam('siteId');
        if ($entryId) {
            $entry = Craft::$app->getEntries()->getEntryById($entryId, $siteId);
            if (!$entry) {
                throw new NotFoundHttpException('Entry not found');
            }
        } else {
            $entry = new Entry();
            $entry->sectionId = $request->getRequiredBodyParam('sectionId');
            if ($siteId) {
                $entry->siteId = $siteId;
            }
        }
        return $entry;
    }

    /**
     * Populates an Entry with post data.
     *
     * @param Entry $entry
     */
    private function _populateEntryModel(Entry $entry)
    {
        $request = Craft::$app->getRequest();
        // Set the entry attributes, defaulting to the existing values for whatever is missing from the post data
        $entry->typeId = $request->getBodyParam('typeId', $entry->typeId);
        $entry->slug = $request->getBodyParam('slug', $entry->slug);
        if (($postDate = $request->getBodyParam('postDate')) !== null) {
            $entry->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        if (($expiryDate = $request->getBodyParam('expiryDate')) !== null) {
            $entry->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }
        $entry->enabled = (bool)$request->getBodyParam('enabled', $entry->enabled);
        $entry->enabledForSite = $entry->getSection()->getHasMultiSiteEntries()
            ? (bool)$request->getBodyParam('enabledForSite', $entry->enabledForSite)
            : true;
        $entry->title = $request->getBodyParam('title', $entry->title);
        if (!$entry->typeId) {
            // Default to the section's first entry type
            $entry->typeId = $entry->getSection()->getEntryTypes()[0]->id;
        }
        // Prevent the last entry type's field layout from being used
        $entry->fieldLayoutId = null;
        $fieldsLocation = $request->getParam('fieldsLocation', 'fields');
        $entry->setFieldValuesFromRequest($fieldsLocation);
        // Author
        $authorId = $request->getBodyParam('author', ($entry->authorId ?: Craft::$app->getUser()->getIdentity()->id));
        if (is_array($authorId)) {
            $authorId = $authorId[0] ?? null;
        }
        $entry->authorId = $authorId;
        // Parent
        if (($parentId = $request->getBodyParam('parentId')) !== null) {
            if (is_array($parentId)) {
                $parentId = reset($parentId) ?: '';
            }
            $entry->newParentId = $parentId ?: '';
        }
        // Revision notes
        $entry->revisionNotes = $request->getBodyParam('revisionNotes');
    }

    /**
     * Displays an entry.
     *
     * @param Entry $entry
     * @return Response
     * @throws ServerErrorHttpException if the entry doesn't have a URL for the site it's configured with, or if the entry's site ID is invalid
     */
    private function _showEntry(Entry $entry): Response
    {

        $sectionSiteSettings = $entry->getSection()->getSiteSettings();

        if (!isset($sectionSiteSettings[$entry->siteId]) || !$sectionSiteSettings[$entry->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The entry ' . $entry->id . ' doesn’t have a URL for the site ' . $entry->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($entry->siteId);
        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $entry->siteId);
        }

        Craft::$app->getSites()->setCurrentSite($site);
        Craft::$app->language = $site->language;
        Craft::$app->set('locale', Craft::$app->getI18n()->getLocaleById($site->language));
        if (!$entry->postDate) {
            $entry->postDate = new DateTime();
        }

        // Have this entry override any freshly queried entries with the same ID/site ID
        Craft::$app->getElements()->setPlaceholderElement($entry);

        // Get meta
        $view = $this->getView();
        $view->getTwig()->disableStrictVariables();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $meta = SEOMate::$plugin->meta->getContextMeta(\array_merge($view->getTwig()->getGlobals(), [
            'seomate' => [
                'element' => $entry,
                'config' => [
                    'returnImageAsset' => true,
                    'cacheEnabled' => false,
                ],
            ],
        ]));

        // Render previews
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
        return $this->renderTemplate('seomate/preview', [
            'entry' => $entry,
            'meta' => $meta,
        ]);
    }
}
