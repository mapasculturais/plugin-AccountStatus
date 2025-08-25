<?php

namespace AccountStatus\JobTypes;

use AccountStatus\Plugin;
use DateTime;
use MapasCulturais\App;
use MapasCulturais\Entities\Job;
use MapasCulturais\Definitions\JobType;

class StatusCheck extends JobType
{
    const SLUG = "StatusCheck";

    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return "StatusCheck";
    }

    protected function _execute(Job $job) {
        $app = App::i();

        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();
        
        $conn = $app->conn;
        if($user_ids = $conn->fetchFirstColumn("SELECT id FROM usr")) {
            $app->disableAccessControl();

            foreach($user_ids as $user_id) {
                if(!$seal = $app->repo('Seal')->find($plugin->config['inactive_seal_id'])) {
                    continue;
                }
                
                $user = $app->repo('User')->find($user_id);
                $last_login = $user->lastLoginTimestamp;
                $inactive_period = new DateTime($plugin->config['inactive_period']);
                $has_inactive_seal = false;

                $seal_relations = $user->profile->getSealRelations();

                foreach($seal_relations as $seal_relation) {
                    if($seal_relation->seal->id == $seal->id) {
                        $has_inactive_seal = true;
                    }
                }

                if($last_login < $inactive_period && !$has_inactive_seal) {
                    $user->profile->createSealRelation($seal, agent: $user->profile);
                }

                $app->em->clear();
            }
            $app->enableAccessControl();
        }
    }
}
