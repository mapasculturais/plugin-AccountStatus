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

<div class="account-status-update-profile">
    <button v-if="!processing && showButton" @click="updateProfile" class="button button--icon button--sm" :class="[{'disabled' : verifyData}]">
        <mc-icon name="sync"></mc-icon>
        <?php i::_e("Atualizar cadastro") ?>
    </button>

    <mc-loading :condition="processing" class="col-12"> <?= i::__('Atualizando') ?></mc-loading>
</div>