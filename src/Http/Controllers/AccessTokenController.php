<?php

namespace Dusterio\LumenPassport\Http\Controllers;

use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Laminas\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;
use Dusterio\LumenPassport\LumenPassport;

/**
 * Class AccessTokenController
 * @package Dusterio\LumenPassport\Http\Controllers
 */
class AccessTokenController extends \Laravel\Passport\Http\Controllers\AccessTokenController
{
    /**
     * Authorize a client to access the user's account.
     *
     * @param  ServerRequestInterface  $request
     * @return Response
     */
    public function issueToken(ServerRequestInterface $request)
    {

        $response = $this->withErrorHandling(function () use ($request) {
            $input = (array) $request->getParsedBody();
            $clientId = isset($input['client_id']) ? $input['client_id'] : null;

//             Overwrite password grant at the last minute to add support for customized TTLs
            $this->server->enableGrantType(
                $this->makePasswordGrant(), LumenPassport::tokensExpireIn(null, $clientId)
            );



            return $this->server->respondToAccessTokenRequest($request, new Psr7Response);
        });


        return $response;


        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            return $response;
        }

        $payload = json_decode($response->getBody()->__toString(), true);

        if (isset($payload['access_token'])) {


            /* @deprecated the jwt property will be removed in a future Laravel Passport release */
            $token = $this->jwt->parse($payload['access_token']);
            if (method_exists($token, 'getClaim')) {
                $tokenId = $token->getClaim('jti');
            } else if (method_exists($token, 'claims')) {
                $tokenId = $token->claims()->get('jti');
            } else {
                throw new \RuntimeException('This package is not compatible with the Laravel Passport version used');
            }

            $token = $this->tokens->find($tokenId);
            if (!$token instanceof Token) {
                return $response;
            }

            if ($token->client->firstParty() && LumenPassport::$allowMultipleTokens) {
                // We keep previous tokens for password clients
            } else {
                $this->revokeOrDeleteAccessTokens($token, $tokenId);
            }
        }

        return $response;
    }

    /**
     * Create and configure a Password grant instance.
     *
     * @return \League\OAuth2\Server\Grant\PasswordGrant
     */
    private function makePasswordGrant()
    {
        $grant = new \League\OAuth2\Server\Grant\PasswordGrant(
            app()->make(\Laravel\Passport\Bridge\UserRepository::class),
            app()->make(\Laravel\Passport\Bridge\RefreshTokenRepository::class)
        );

        $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());

        return $grant;
    }

    /**
     * Revoke the user's other access tokens for the client.
     *
     * @param  Token $token
     * @param  string $tokenId
     * @return void
     */
    protected function revokeOrDeleteAccessTokens(Token $token, $tokenId)
    {
        $query = Token::where('user_id', $token->user_id)->where('client_id', $token->client_id);

        if ($tokenId) {
            $query->where('id', '<>', $tokenId);
        }

        $query->update(['revoked' => true]);
    }
}
