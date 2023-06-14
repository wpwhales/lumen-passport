<?php

namespace Dusterio\LumenPassport;

class RouteRegistrar
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @var array
     */
    private $options;

    /**
     * Create a new route registrar instance.
     *
     * @param  $app
     * @param  array $options
     */
    public function __construct($app, array $options = [])
    {
        $this->app = $app;
        $this->options = $options;
    }

    /**
     * Register routes for transient tokens, clients, and personal access tokens.
     *
     * @return void
     */
    public function all()
    {
        $this->forAccessTokens();
        $this->forTransientTokens();
//        $this->forClients();
//        $this->forPersonalAccessTokens();
    }

    /**
     * @param string $path
     * @return string
     */
    private function prefix($path)
    {
        if (strstr($path, '\\') === false && isset($this->options['namespace'])) {
            return $this->options['namespace'] . '\\' . $path;
        }

        return $path;
    }

    /**
     * Register the routes for retrieving and issuing access tokens.
     *
     * @return void
     */
    public function forAccessTokens()
    {
        $this->app->post('/token',

            ["uses"=>$this->prefix('\Dusterio\LumenPassport\Http\Controllers\AccessTokenController@issueToken'),"withoutMiddleware"=>[\Laravel\Lumen\Middlewares\VerifyCsrfToken::class]]
            );

        $this->app->group(['middleware' => ['auth']], function () {
            $this->app->get('/tokens', $this->prefix('AuthorizedAccessTokenController@forUser'));
            $this->app->delete(

                '/tokens/{tokenId}',
                ["uses"=>$this->prefix('AuthorizedAccessTokenController@destroy'),"withoutMiddleware"=>[\Laravel\Lumen\Middlewares\VerifyCsrfToken::class]]
                );
        });
    }

    /**
     * Register the routes needed for refreshing transient tokens.
     *
     * @return void
     */
    public function forTransientTokens()
    {
        $this->app->post('/token/refresh', [
            'middleware' => ['auth'],
            'uses' => $this->prefix('TransientTokenController@refresh'),
            "withoutMiddleware"=>[\Laravel\Lumen\Middlewares\VerifyCsrfToken::class]
        ]);
    }

    /**
     * Register the routes needed for managing clients.
     *
     * @return void
     */
    public function forClients()
    {
        $this->app->group(['middleware' => ['auth']], function () {
            $this->app->get('/clients', $this->prefix('ClientController@forUser'));
            $this->app->post('/clients', $this->prefix('ClientController@store'));
            $this->app->put('/clients/{clientId}', $this->prefix('ClientController@update'));
            $this->app->delete('/clients/{clientId}', $this->prefix('ClientController@destroy'));
        });
    }

    /**
     * Register the routes needed for managing personal access tokens.
     *
     * @return void
     */
    public function forPersonalAccessTokens()
    {
        $this->app->group(['middleware' => ['auth']], function () {

            $this->app->get('/scopes', $this->prefix('ScopeController@all'));
            $this->app->get('/personal-access-tokens', $this->prefix('PersonalAccessTokenController@forUser'));
            $this->app->post('/personal-access-tokens', ["uses"=>$this->prefix('PersonalAccessTokenController@store'),"withoutMiddleware"=>[\Laravel\Lumen\Middlewares\VerifyCsrfToken::class]]);
            $this->app->delete('/personal-access-tokens/{tokenId}', $this->prefix('PersonalAccessTokenController@destroy'));
        });
    }
}
