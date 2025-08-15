<?php

namespace AccountStatus\JobTypes;

use AccountStatus\Plugin;
use DateTime;
use MapasCulturais\i;
use MapasCulturais\App;
use MapasCulturais\Entities\Job;
use MapasCulturais\Definitions\JobType;

class ProfileUpdateVerification extends JobType
{
    const SLUG = "ProfileUpdateVerification";

    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return "ProfileUpdateVerification";
    }

    protected function _execute(Job $job) {
        $app = App::i();

        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();
        $conn = $app->em->getConnection();
        
        if($user_ids = $conn->fetchFirstColumn("SELECT id FROM usr")) {
            $fields = $plugin->config['update_fields'];
            $update_period = new DateTime('-1 year');

            $app->disableAccessControl();
            foreach($user_ids as $user_id) {
                if(!$seal = $app->repo('Seal')->find($plugin->config['updated_seal_id'])) {
                    continue;
                }

                $user = $app->repo('User')->find($user_id);

                $has_seal = false;
                $need_update = false;

                foreach($fields as $field) {
                    $profile = $user->profile;
                    $conn = $app->em->getConnection();
                    $query = $conn->fetchAll("
                            SELECT er.object_id, er.create_timestamp, er.action, rd.timestamp, rd.key, rd.value
                            FROM entity_revision er
                            LEFT JOIN entity_revision_revision_data errd ON errd.revision_id = er.id
                            LEFT JOIN entity_revision_data rd ON rd.id = errd.revision_data_id
                            WHERE 
                                er.object_type = 'MapasCulturais\Entities\Agent'
                                AND er.object_id = :agent_id
                                AND rd.key = :key
                            ORDER BY er.create_timestamp DESC
                            LIMIT 1;
                        ", [
                        'agent_id' => $profile->id,
                        'key' => $field
                    ]);

                    if($revision_field = $query[0] ?? null) {
                        if($revision_field['value'] == null || $revision_field['value'] == '' || $revision_field['value'] == 'null') {
                            $need_update = true;
                            break;
                        }

                        $last_update = new DateTime($revision_field['create_timestamp']);

                        $seal_relations = $profile->getSealRelations();
                        
                        foreach($seal_relations as $seal_relation) {
                            if($seal_relation->seal->id == $seal->id) {
                                $has_seal = true;
    
                                if($last_update < $update_period) {
                                    $need_update = true;
                                }
                            }
                        }
                    }
                }

                // Se o usuário precisa atualizar, remove o selo
                if($need_update && $has_seal) {
                    $profile->removeSealRelation($seal);
                }

                // Se o usuário não tiver o selo e não precisa atualizar, sela o usuário
                if(!$need_update && !$has_seal) {
                    $profile->createSealRelation($seal, agent: $profile);
                }

                // Caso o usuário esteja passando o prazo de atualização de cadastro, envia um e-mail
                $valid_fields = $plugin->validateFields($profile->id);

                if(!$valid_fields) {
                    $this->sendMail($user);
                }

                $app->em->clear();
            }
            $app->enableAccessControl();
        }
    }

    function sendMail($user) {
        $app = App::i();

        $locale = i::get_locale();
        $template = "update-profile-{$locale}.html";
        
        $filename = $app->view->resolveFilename("views/emails", $template);
        $template = file_get_contents($filename);

        $message = sprintf(
            i::__('Falta um mês para o seu selo de atualização expirar. Para manter seu cadastro atualizado, vá no seu %s e atualize as informações obrigatórias e clique no botão atualizar.'),
            '<a href="' . $user->profile->singleUrl . '" target="_blank">' . i::__('perfil') . '</a>'
        );
        
        $params = [
            "siteName" => $app->siteName,
            "agentName" => $user->profile->name,
            "url" => $user->profile->singleUrl,
            "baseUrl" => $app->getBaseUrl(),
            "mailMessage" => $message
        ];

        $mustache = new \Mustache_Engine();
        $content = $mustache->render($template, $params);

        $app->createAndSendMailMessage([
            'from' => $app->config['mailer.from'],
            'to' => ($user->profile->emailPrivado ??
                $user->profile->emailPublico ?? 
                $user->email),
            'subject' => "[{$app->siteName}] " . i::__("Atualize seu cadastro"),
            'body' => $content,
        ]);
    }
}
