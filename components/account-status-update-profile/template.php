<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */
use MapasCulturais\i;

?>

<div class="account-status-update-profile">
    <button @click="updateProfile" class="button button--icon button--sm" :class="[{'disabled' : verifyData}]">
        <mc-icon name="sync"></mc-icon>
        <?php i::_e("Atualizar cadastro") ?>
    </button>
</div>