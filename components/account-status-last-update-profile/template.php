<?php

/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-loading
');
?>

<div class="account-status-last-update-profile col-12">
    <p><?php i::_e('Última atualização') ?>:</p>
    
    <p>
        {{lastUpdate.date('numeric year')}}
        <?= i::__('às') ?>
        {{lastUpdate.time('numeric')}}
    </p>
</div>