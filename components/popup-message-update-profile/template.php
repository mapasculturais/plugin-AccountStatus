<?php

/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-modal
    entity-field
    mc-alert
');
?>

<mc-modal modal-state title="Atualização de cadastro necessária">
    <template #default>
        <mc-alert type="warning">
            <?php i::_e("Seu cadastro está desatualizado. Por favor, atualize seus dados e mantenha o selo de cadastro atualizado.") ?>
        </mc-alert>
        <br>
        <entity-field v-for="field in fields" :key="field" :prop="field" :entity="agent"></entity-field>
    </template>

    <template #actions="modal">
        <button class="button button--primary" :disabled="saving" @click="saveProfile(modal)">
            <span v-if="saving">Salvando...</span>
            <span v-else>Atualizar cadastro</span>
        </button>
    </template>
</mc-modal>