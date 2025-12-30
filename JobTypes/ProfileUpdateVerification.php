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
                $user = $app->repo('User')->find($user_id);
                $profile = $user->profile;
                
                $is_inactive = $this->checkInactiveStatus($user, $plugin);
                if($is_inactive) {
                    if($profile->statusRegistered != 'inactive') {
                        $locale = i::get_locale();
                        $template = "inactive-user-{$locale}.html";
                        $subject = "[{$app->siteName}] " . i::__("Seu cadastro no sistema foi definido com status de inativo");
                        
                        $this->sendMail($user, 'inactive', $template, $subject);
                        $profile->statusRegistered = 'inactive';
                        $profile->save(true);
                    }

                    $app->em->clear();
                    continue;
                }
                
                if(!$seal = $app->repo('Seal')->find($plugin->config['updated_seal_id'])) {
                    $app->em->clear();
                    continue;
                }

                $has_seal = false;
                $need_update = false;

                foreach($fields as $field) {
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
                    $profile->checkUpdateExpiration = 'updated';
                    $profile->save(true);
                }

                // Caso o usuário esteja passando o prazo de atualização de cadastro, envia um e-mail
                $check_expiration = $this->checkExpiration($profile->id);
                $status_expiration = ['expired', 'expires_today', '7days', '15days', '30days'];

                if($check_expiration && in_array($check_expiration, $status_expiration) && $profile->checkUpdateExpiration != $check_expiration) {
                    $this->sendMail($user, $check_expiration);
                    $profile->checkUpdateExpiration = $check_expiration;
                    $profile->save(true);
                }

                $app->em->clear();
            }
            $app->enableAccessControl();
        }
    }

    function sendMail($user, string $expiration_status, ?string $_template = null, ?string $_subject = null) {
        $app = App::i();

        $locale = i::get_locale();
        $template = $_template ? $_template : "update-profile-{$locale}.html";
        $subject = $_subject ? $_subject : "[{$app->siteName}] " . i::__("Atualize seu cadastro");
        
        $filename = $app->view->resolveFilename("views/emails", $template);
        $template = file_get_contents($filename);

        $message = $this->getMessageMail($user->profile->singleUrl, $expiration_status);
        
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
            'subject' => $subject,
            'body' => $content,
        ]);
    }

    function checkExpiration($agent_id) {
        $app = App::i();

        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();
        $fields = $plugin->config['update_fields'];

        foreach($fields as $field) {
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
                'agent_id' => $agent_id,
                'key' => $field
            ]);

            if($revision_field = $query[0] ?? null) {
                if(empty($revision_field['value']) || $revision_field['value'] === 'null') {
                    return 'expired';
                }

                $last_update = new DateTime($revision_field['create_timestamp']);
                $expiration_date = (clone $last_update)->modify($plugin->config['update_expiration_period']); 
                $now = new DateTime();

                $diff = (int)$now->diff($expiration_date)->format('%r%a'); 

                if($diff < 0) return 'expired';
                if($diff == 0) return 'expires_today';
                if($diff == 7) return '7days';
                if($diff == 15) return '15days';
                if($diff == 30) return '30days';
            }
        }

        return 'updated';
    }

    function getMessageMail(string $url, string $expiration_status): string {
        $app = App::i();
        $link = '<a href="' . $url . '" target="_blank">' . i::__('perfil') . '</a>';

        switch ($expiration_status) {
            case 'expired':
                return sprintf(
                    i::__('Seu selo de atualização está expirado. Para manter seu cadastro atualizado, vá no seu %s e atualize as informações obrigatórias e clique no botão atualizar.'),
                    $link
                );
            case 'expires_today':
                return sprintf(
                    i::__('Seu selo de atualização irá expirar hoje. Para manter seu cadastro atualizado, vá no seu %s e atualize as informações obrigatórias e clique no botão atualizar.'),
                    $link
                );
            case '7days':
                return sprintf(
                    i::__('Faltam 7 dias para o seu selo de atualização expirar. Para manter seu cadastro atualizado, vá no seu %s e atualize as informações obrigatórias e clique no botão atualizar.'),
                    $link
                );
            case '15days':
                return sprintf(
                    i::__('Faltam 15 dias para o seu selo de atualização expirar. Para manter seu cadastro atualizado, vá no seu %s e atualize as informações obrigatórias e clique no botão atualizar.'),
                    $link
                );
            case '30days':
                return sprintf(
                    i::__('Falta um mês para o seu selo de atualização expirar. Para manter seu cadastro atualizado, vá no seu %s e atualize as informações obrigatórias e clique no botão atualizar.'),
                    $link
                );
            case 'inactive':
                return sprintf(
                    i::__('Seu cadastro no sistema %s encontra-se atualmente com status inativo. Para manter seu cadastro ativo, acesse seu perfil no link %s, atualize os dados necessários e salve o cadastro ao final do processo. Caso todas as informações já estejam corretas, basta acessar o perfil e salvá-lo novamente para regularizar o status'),
                    $app->siteName,
                    $link
                );
            default:
                return '';
        }
    }

    private function checkInactiveStatus($user, $plugin) {
        $app = App::i();
        $last_login = $user->lastLoginTimestamp;
        $inactive_period = new DateTime($plugin->config['inactive_period']);
        $inactive_seal = $app->repo('Seal')->find($plugin->config['inactive_seal_id']);
        $updated_seal = $app->repo('Seal')->find($plugin->config['updated_seal_id']);
        
        if(!$inactive_seal) {
            return false;
        }
        
        $profile = $user->profile;
        $seal_relations = $profile->getSealRelations();
        
        $has_inactive_seal = false;
        $has_updated_seal = false;
        
        foreach($seal_relations as $seal_relation) {
            if($seal_relation->seal->id == $inactive_seal->id) {
                $has_inactive_seal = true;
            }
            if($updated_seal && $seal_relation->seal->id == $updated_seal->id) {
                $has_updated_seal = true;
            }
        }
        
        $is_inactive = ($last_login < $inactive_period);
        
        if($is_inactive) {
            if(!$has_inactive_seal) {
                $profile->createSealRelation($inactive_seal, agent: $profile);
            }
            
            if($has_updated_seal && $updated_seal) {
                $profile->removeSealRelation($updated_seal);
            }
            
            return true;
        } else {
            if($has_inactive_seal) {
                $profile->removeSealRelation($inactive_seal);
            }
            
            return false;
        }
    }

}
