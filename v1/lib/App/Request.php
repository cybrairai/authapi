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
use App\Exception\InvalidMethodCall;
use App\Exception\UnexpectedValueException;

class Request
{
    use ErrorTrait;

    const LOGIN = 'login';

    public $isLinked = false;

    public $token = '';

    protected $db;

    protected $service;
    protected $request;

    const INVALID_SERVICE   = 'invalidService';
    const NEEDED_TOKEN      = 'neededToken';
    const NEEDED_REQUEST    = 'neededRequest';
    const UNKNOWN_REQUEST   = 'unknownRequest';

    protected $errorMessageArray = array(
        self::INVALID_SERVICE   => 'The provided service is unknown.',
        self::NEEDED_TOKEN      => 'A token is needed to authenticate yourself.',
        self::NEEDED_REQUEST    => 'A request is needed to perform authentication.',
        self::UNKNOWN_REQUEST   => 'This request %s is unknown',
    );

    private $authorizedServices = array('shifty');
    private $authorizedRequest = array('login', 'tokenGen');

    private $servicesArray = array(
        \App\Services\Shifty::SERVICE_ID => 'Shifty',
    );

    public function __construct($request)
    {
        $this->db = DatabaseFactory::getInstance()->getDb();

        if (isset($request['token']))
        {
            $serviceId = $this->getServiceIdByToken($request['token']);
            if ($serviceId === false)
                $this->setError(self::INVALID_SERVICE);
            else
            {
                $this->initializeService($this->servicesArray[$serviceId]);
                $this->publicToken = $request['token'];
            }
        } else
            $this->setError(self::NEEDED_TOKEN);

        if (isset($request['request']))
        {
            if (in_array($request['request'], $this->authorizedRequest))
                $this->request = $request['request'];
            else
                $this->setError(self::UNKNOWN_REQUEST, $request['request']);
        } else
            $this->setError(self::NEEDED_REQUEST);

    }

    protected function getServiceIdByToken($publicToken)
    {
        $sql = $this->db->prepare('SELECT serviceId FROM tokens WHERE publicToken = :token LIMIT 1');
        $sql->execute(array(
           ':token' => $publicToken,
        ));

        $result = $sql->fetch(\PDO::FETCH_ASSOC);
        if ($result !== false)
            return $result['serviceId'];

        return $result;
    }

    protected function initializeService($serviceName)
    {
        $className = '\App\Services\\' . $serviceName;
        $this->service = new $className();

        $this->isLinked = true;
    }

    protected function loginResponse($requestFields, User $user)
    {
        $sql = $this->db->prepare('SELECT p.level,u.username FROM permissions as p INNER JOIN users as u WHERE u.id = p.userId AND serviceId = :serviceId AND u.id = :userId LIMIT 1');
        $sql->execute(array(
            ':serviceId' => $this->service->getServiceId(),
            ':userId' => $user->getUserId(),
        ));

        $result = $sql->fetch(\PDO::FETCH_ASSOC);

        if ($result === false)
            throw new UnexpectedValueException("Identification request went wrong.");
        else
            return $result;
    }

    public function sendResponse($user = false)
    {
        if ($this->isLinked === false)
            throw new InvalidMethodCall("This method should not been called if there is no linked service.");

        $requestField = $this->service->requestField[$this->request];
        switch($this->request)
        {
            case 'login':
                $returnResult = $this->loginResponse($requestField, $user);
                break;

            default:
                throw new ArgumentException(sprintf("Unknown request : %s .", $this->request));
        }

        $this->updateTokens($returnResult);

        $this->service->sendResponse($this->publicToken);
    }

    protected function updateTokens($returnResult)
    {
        $sql = $this->db->prepare('UPDATE tokens SET username = :username, level = :level WHERE publicToken = :token ;');
        $result = $sql->execute(array(
            ':username' => $returnResult['username'],
            ':level' => $returnResult['level'],
            ':token' => $this->publicToken,
        ));

        if ($result === false)
            throw new UnexpectedValueException("Update token went wrong");

        return true;
    }

}