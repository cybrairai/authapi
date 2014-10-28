<?php
/**
 * Auth API
 * 
 * @author Alexis
 * @version 
 * @since 
 */

namespace App;


use App\Exception\ArgumentException;

class Config
{
    private static $instance = null;

    protected $dbh;

    protected $databaseInfo = array();
    protected $generalInfo = array();

    /**
     *
     */
    private function __construct()
    {

        $reader = new \Zend\Config\Reader\Ini();
        $data = $reader->fromFile(APP_PATH . '/config/global.ini');

        $this->generalInfo = $data['global'];
        $this->databaseInfo = $data[APP_ENV]['database'];

    }

    public function getConfig($configKey)
    {
        switch($configKey)
        {
            case 'database':
                return $this->databaseInfo;
                break;

            case 'facebook':
                return $this->generalInfo['facebook'];
                break;

            default:
                if (array_key_exists($configKey, $this->generalInfo))
                    return $this->generalInfo[$configKey];
                throw new ArgumentException("$configKey is not a valid config param");
                break;
        }
    }

    static function getInstance()
    {
        if (is_null(self::$instance))
            self::$instance = new Config();

        return self::$instance;
    }


} 