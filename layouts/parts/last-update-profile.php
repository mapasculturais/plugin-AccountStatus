<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use AccountStatus\Plugin;
use MapasCulturais\i;

$this->import('
    account-status-last-update-profile
');
?>

<account-status-last-update-profile :entity="entity"></account-status-last-update-profile>