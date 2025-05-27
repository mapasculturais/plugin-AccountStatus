<?php

namespace AccountStatus;

use MapasCulturais\App;
use MapasCulturais\i;
use \AccountStatus\JobTypes\ProfileUpdateVerification;
use \AccountStatus\JobTypes\StatusCheck;
use DateTime;

class Plugin extends \MapasCulturais\Plugin
{
    protected static $instance;

    function __construct(array $config = [])
    {
        $config += [
            'inactive_seal_id' => env('USR_STATUS_INACTIVE_SEAL_ID', 5),
            'inactive_period' => env('USR_STATUS_INACTIVE_PERIOD', '-1 year'),
            'updated_seal_id' =>  env('USR_STATUS_UPDATED_SEAL_ID', 4),
            'last_update_period' => env('USR_STATUS_LAST_UPDATE', '-1 year'),
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
        
        $app->registerJobType(new StatusCheck(StatusCheck::SLUG));
        $app->registerJobType(new ProfileUpdateVerification(ProfileUpdateVerification::SLUG));
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

        $app->hook('POST(site.atualizar-dados)', function() use($app, $self) {
            $this->requireAuthentication();

            $fields = $self->config['update_fields'];

            if($agent = $app->repo('Agent')->find($this->data['agent_id'])) {
                foreach($fields as $field) {
                    $app->disableAccessControl();
                    $agent->_newModifiedRevision(sprintf(i::__('campo "%s" modificado'), $field));
                    $app->enableAccessControl();
                }

                $this->json($agent);
            }

            $this->errorJson(false);
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
                        AND er.action = 'modified'
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
                $update_period = new DateTime($this->config['last_update_period']);
                $almost_expired = (clone $update_period)->modify('+1 month');
                
                if($last_update <= $update_period || $last_update >= $almost_expired) {
                    $valid = false;
                    break;
                }
            }
        }

        return $valid;
    }
}
