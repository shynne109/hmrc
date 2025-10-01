<?php

namespace HMRC\Oauth2;

use HMRC\Exceptions\InvalidVariableTypeException;
use HMRC\Exceptions\MissingAccessTokenException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Illuminate\Support\Facades\Session;

class AccessToken
{
    const SESSION_KEY = 'hmrc_access_token';

    public static function exists(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /**
     * @return AccessTokenInterface|null
     */
    public static function get()
    {
        $token = Session::get(self::SESSION_KEY);
        return $token ? unserialize($token) : null;
    }

    /**
     * @param $accessToken
     *
     * @throws InvalidVariableTypeException
     */
    public static function set($accessToken)
    {
        if ($accessToken instanceof AccessTokenInterface) {
            $accessToken = serialize($accessToken);
        }

        if (gettype($accessToken) !== 'string') {
            throw new InvalidVariableTypeException('Access token must be string or implement AccessTokenInterface.');
        }

        Session::put(self::SESSION_KEY, $accessToken);
    }

    /**
     * @throws MissingAccessTokenException
     *
     * @return bool
     */
    public static function hasExpired(): bool
    {
        /** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
        $accessToken = self::get();

        if (is_null($accessToken)) {
            throw new MissingAccessTokenException("Access token doesn't exists.");
        }

        return $accessToken->hasExpired();
    }

    /**
     * Remove the access token from the session
     */
    public static function remove(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Clear all session data
     */
    public static function clearAll(): void
    {
        Session::flush();
    }

    /**
     * Get the raw token string from session
     * 
     * @return string|null
     */
    public static function getRaw(): ?string
    {
        return Session::get(self::SESSION_KEY);
    }

    /**
     * Check if token exists and is not expired
     * 
     * @return bool
     */
    public static function isValid(): bool
    {
        if (!self::exists()) {
            return false;
        }

        try {
            return !self::hasExpired();
        } catch (MissingAccessTokenException $e) {
            return false;
        }
    }
}
