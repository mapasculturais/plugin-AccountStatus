<?php

use AccountStatus\Plugin;
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

 $plugin = Plugin::getInstance();
 $fields = $plugin->config['update_fields'];

$agent_id = $app->user->profile->id;
$agent = $app->repo('Agent')->find($agent_id);

$result = [];
if($fields) {
    foreach($fields as $field) {
        $result[$field] = $agent->$field;
    }
}

$app->view->jsObject['config']['popupMessageUpdateProfile'] = [
    'fields' => $fields,
    'agentData' => $result
];