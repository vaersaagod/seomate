<?php

namespace vaersaagod\seomate\events;

use craft\events\CancelableEvent;

class MetaTemplateEvent extends CancelableEvent
{
    /**
     * @var string The name of the meta template being rendered
     */
    public $template;

    /**
     * @var array The variables that were passed to [[\vaersaagod\services\RenderService::renderMetaTemplate()]].
     */
    public $context;

    /**
     * @var string The rendering result of [[\vaersaagod\services\RenderService::renderMetaTemplate()]].
     */
    public $output;
}
