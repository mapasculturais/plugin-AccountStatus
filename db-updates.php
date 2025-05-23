<?php

use MapasCulturais\App;
use AccountStatus\JobTypes\ProfileUpdateVerification;
use AccountStatus\JobTypes\StatusCheck;

return [
    'Faz enfileiramento de job responsavel por checar a inatividade do usuario' => function () {
        $app = App::i();
        $start_string = date('Y-m-d 00:00:00', strtotime('tomorrow'));
        $interval_string = '1 day';
        $iterations = 60*24*365*10;

        $job = $app->enqueueOrReplaceJob(StatusCheck::SLUG, [], $start_string, $interval_string, $iterations);
        $job->save(true);

        return false;
    },

    'Faz enfileiramento de job responsavel por verificar se o cadastro do usuário está atualizado' => function () {
        $app = App::i();
        $start_string = date('Y-m-d 00:00:00', strtotime('tomorrow'));
        $interval_string = '1 day';
        $iterations = 60*24*365*10;

        $job = $app->enqueueOrReplaceJob(ProfileUpdateVerification::SLUG, [], $start_string, $interval_string, $iterations);
        $job->save(true);

        return false;
    },
];
