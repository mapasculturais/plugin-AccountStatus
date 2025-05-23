<?php

namespace AccountStatus\JobTypes;

use AccountStatus\Plugin;
use DateTime;
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
        
        if($seal = $app->repo('Seal')->find($plugin->config['updated_seal_id'])) {
            $users = $app->repo('User')->findAll();

            $app->disableAccessControl();
            foreach($users as $user) {
                $last_update = $user->profile->updateTimestamp;
                $update_period = new DateTime($plugin->config['last_update_period']);
                $has_seal = false;

                $seal_relations = $user->profile->getSealRelations();

                foreach($seal_relations as $seal_relation) {
                    if($seal_relation->seal->id == $seal->id) {
                        $has_seal = true;

                        if($last_update < $update_period) {
                            $user->profile->removeSealRelation($seal);
                        }
                    }
                }

                if($last_update >= $update_period && !$has_seal) {
                    $user->profile->createSealRelation($seal, agent: $user->profile);
                }
            }
            $app->enableAccessControl();
        }
    }
}
