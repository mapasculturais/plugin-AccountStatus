<?php

namespace AccountStatus;

use MapasCulturais\App;

use \AccountStatus\JobTypes\StatusCheck;

class Plugin extends \MapasCulturais\Plugin
{
    protected static $instance;

    function __construct(array $config = [])
    {
        $config += [
            'inactive_seal_id' => env('USR_STATUS_INACTIVE_SEAL_ID', 1),
            'inactive_period' => env('USR_STATUS_INACTIVE_PERIOD', '-1 year'),
            'updated_seal_id' =>  env('USR_STATUS_UPDATED_SEAL_ID', 1),
            'last_update_period' => env('USR_STATUS_LAST_UPDATE', '-1 year'),
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
    }

    function _init() {}

    static function getInstance()
    {
        return self::$instance;
    }
}
