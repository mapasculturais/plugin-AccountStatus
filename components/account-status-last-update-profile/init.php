<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use AccountStatus\Plugin;

$instance = Plugin::getInstance();

$agent = $this->controller->requestedEntity;
$last_update = $instance->getMostRecentUpdateField($agent->id);

$app->view->jsObject['config']['accountStatusLastUpdateProfile'] = [
    'lastUpdate' => $last_update
];