<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\services;

use craft\base\Component;

use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\ListItem;

/**
 * SchemaService Service
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SchemaService extends Component
{
    /**
     * Creates a BreadcrumbList schema object based on a dumb array in the form of:
     *
     * [
     *   { url: 'https://domain.com/some/url', name: 'Name of breadcrumb item' },
     *   { url: 'https://domain.com/some/other/url', name: 'Name of breadcrumb item' },
     * ]
     *
     * It is assumed that the root level of the breadcrumb (ie, frontpage) is the first item,
     * and the leaf (ie, current page) is the last.
     *
     * @param array $listItems
     * @return BreadcrumbList
     */
    public function breadcrumb(array $listItems): BreadcrumbList
    {
        $breadcrumbList = new BreadcrumbList();
        $i = 1;
        $elements = [];

        foreach ($listItems as $listItem) {
            $breadcrumbListItem = new ListItem();
            $breadcrumbListItem->position($i++);
            $breadcrumbListItem->item([
                '@id' => $listItem['url'],
                'name' => $listItem['name'],
            ]);

            $elements[] = $breadcrumbListItem;
        }

        $breadcrumbList->itemListElement($elements);

        return $breadcrumbList;
    }
}
