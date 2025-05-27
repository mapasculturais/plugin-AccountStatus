<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use AccountStatus\Plugin;
use MapasCulturais\i;

$instance = Plugin::getInstance();

$fields = $instance->config['update_fields'] ?? [];

$this->import('
    account-status-update-profile
');

?>

<account-status-update-profile :entity="entity" :fields='<?php echo json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>'></account-status-update-profile>