<?php

namespace AccountStatus;

use MapasCulturais\App;
use MapasCulturais\i;
use \AccountStatus\JobTypes\ProfileUpdateVerification;
use DateTime;

class Plugin extends \MapasCulturais\Plugin
{
    protected static $instance;

    function __construct(array $config = [])
    {
        $config += [
            'inactive_seal_id' => env('USR_STATUS_INACTIVE_SEAL_ID'),
            'inactive_period' => env('USR_STATUS_INACTIVE_PERIOD', '-1 year'),
            'updated_seal_id' =>  env('USR_STATUS_UPDATED_SEAL_ID'),
            'update_expiration_period' => env('USR_STATUS_LAST_UPDATE', '+1 year'),
            'update_fields' => [
                'name',
                'shortDescription',
            ],
        ];

        parent::__construct($config);

        self::$instance = $this;
    }

    /**W
     * @return void
     */
    function register()
    {
        $app = App::i();
        
        $app->registerJobType(new ProfileUpdateVerification(ProfileUpdateVerification::SLUG));

        $this->registerMetadata('MapasCulturais\Entities\Agent', 'checkUpdateExpiration', [
            'label' => 'Status de expiração da atualização do usuário',
            'type' => 'string',
        ]);

        $this->registerMetadata('MapasCulturais\Entities\Agent', 'statusRegistered', [
            'label' => 'Define se o usuário está inativo',
            'type' => 'string',
        ]);
    }

    function _init() 
    {
        $app = App::i();
        $self = $this;

        $app->hook('template(agent.edit.entity-actions--primary):end', function () use($self) {
            /** @var Theme $this */
            $agent = $this->controller->requestedEntity;
            $valid_fields = $self->validateFields($agent->id);

            if(!$valid_fields) {
                $this->part('update-profile');
            }
        });
        
        $app->hook('template(agent.edit.tab-entity-info):after', function () use($self) {
            /** @var Theme $this */
            
            $this->part('last-update-profile');
        });

        $app->hook('POST(site.atualizar-dados)', function() use($app, $self) {
            $this->requireAuthentication();

            $fields = $self->config['update_fields'];

            if($agent = $app->repo('Agent')->find($this->data['agent_id'])) {
                foreach($fields as $field) {
                    $app->disableAccessControl();
                    $agent->updateTimestamp = new DateTime();
                    $agent->_newModifiedRevision(sprintf(i::__('campo "%s" modificado'), $field));
                    $agent->save(true);
                    $app->enableAccessControl();
                }

                $this->json($agent);
            }

            $this->errorJson(false);
        });


        $app->hook("entity(Agent).save:after", function() use($app, $self) {
            /** @var Agent $this */
            $seal = $app->repo('Seal')->find($self->config['updated_seal_id']);
            $self->checkUpdate($this, $seal);
        });

        // Se o usuário tiver o selo de inativo, é removido ao se logar
        $app->hook('auth.successful', function() use($app, $self) {
            $agent = $app->user->profile;
            $seal_relations = $agent->getSealRelations();

            $conn = $app->em->getConnection();

            $app->disableAccessControl();
            
            foreach($seal_relations as $seal_relation) {
                if($seal_relation->seal->id == $self->config['inactive_seal_id']) {
                    $conn->executeQuery("UPDATE agent_meta set value = 'active' where object_id = {$agent->id} and key = 'statusRegistered'");
                    $agent->removeSealRelation($seal_relation->seal);
                    break;
                }
            }
            $app->enableAccessControl();
        });
    }

    static function getInstance()
    {
        return self::$instance;
    }

    function validateFields($agent_id)
    {
        $app = App::i();
        $fields = $this->config['update_fields'];
        $valid = true;

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
                if($revision_field['value'] == null || $revision_field['value'] == '' || $revision_field['value'] == 'null') {
                    $valid = false;
                    break;
                }

                $last_update = new DateTime($revision_field['create_timestamp']);
                $now = new DateTime();
                $expiration_date = (clone $last_update)->modify($this->config['update_expiration_period']);
                $almost_expired = (clone $expiration_date)->modify('-1 month');
                
                if ($now >= $expiration_date || $now >= $almost_expired) {
                    $valid = false;
                    break;
                }
            }
        }

        return $valid;
    }

    function getMostRecentUpdateField($agent_id)
    {
        $app = App::i();
        $fields = $this->config['update_fields'];
        $most_recent_date = null;

        foreach ($fields as $field) {
            $conn = $app->em->getConnection();
            $query = $conn->fetchAll("
                SELECT er.create_timestamp, rd.value
                FROM entity_revision er
                LEFT JOIN entity_revision_revision_data errd ON errd.revision_id = er.id
                LEFT JOIN entity_revision_data rd ON rd.id = errd.revision_data_id
                WHERE 
                    er.object_type = 'MapasCulturais\\Entities\\Agent' 
                    AND er.object_id = :agent_id
                    AND rd.key = :key
                ORDER BY er.create_timestamp DESC
                LIMIT 1;
            ", [
                'agent_id' => $agent_id,
                'key' => $field
            ]);

            if ($revision_field = $query[0] ?? null) {
                $value = $revision_field['value'];
                
                if ($value !== null && $value !== '' && $value !== 'null') {
                    $field_date = new DateTime($revision_field['create_timestamp']);

                    if (is_null($most_recent_date) || $field_date > $most_recent_date) {
                        $most_recent_date = $field_date;
                    }
                }
            }
        }

        return $most_recent_date;
    }

    function checkUpdate($profile, $seal)
    {
        $app = App::i();
        $has_seal = false;
        $need_update = false;
        $fields = $this->config['update_fields'];
        $update_period = new DateTime('-1 year');
     
        $app->disableAccessControl();
        foreach ($fields as $field) {
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

            if ($revision_field = $query[0] ?? null) {
                if ($revision_field['value'] == null || $revision_field['value'] == '' || $revision_field['value'] == 'null') {
                    $need_update = true;
                    break;
                }

                $last_update = new DateTime($revision_field['create_timestamp']);

                $seal_relations = $profile->getSealRelations();

                foreach ($seal_relations as $seal_relation) {
                    if ($seal_relation->seal->id == $seal->id) {
                        $has_seal = true;

                        if ($last_update < $update_period) {
                            $need_update = true;
                        }
                    }
                }
            }
        }

        // Se o usuário precisa atualizar, remove o selo
        if ($need_update && $has_seal) {
            $profile->removeSealRelation($seal);
        }

        // Se o usuário não tiver o selo e não precisa atualizar, sela o usuário
        if (!$need_update && !$has_seal) {
            $profile->createSealRelation($seal, agent: $profile);
            $profile->checkUpdateExpiration = 'updated';
            $profile->save(true);
        }
        $app->enableAccessControl();
    }
}
