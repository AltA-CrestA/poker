<?php


use sizeg\jwt\Jwt;
use yii\di\Instance;
use yii\filters\auth\AuthMethod;

/**
 * Class HttpSocketAuth
 */
class HttpSocketAuth extends AuthMethod
{
    public $jwt = 'jwt';
    public $realm = 'socket';
    public $auth;

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        $this->jwt = Instance::ensure($this->jwt, Jwt::class);
    }

    /**
     * Authenticates the current user.
     * @param \yii\web\User $user
     * @param \yii\web\Request $request
     * @param \yii\web\Response $response
     * @return \yii\web\IdentityInterface the authenticated user identity. If authentication information is not provided, null will be returned.
     * @throws \yii\web\UnauthorizedHttpException if authentication information is provided but is invalid.
     */
    public function authenticate($user, $request, $response)
    {
        // TODO: Implement authenticate() method.
    }

    /**
     * @param $token
     * @return mixed
     */
    public function loadToken($token)
    {
        return $this->jwt->loadToken($token);
    }
}
