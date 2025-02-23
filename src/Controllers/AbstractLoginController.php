<?php

namespace SlimApp\Controllers;


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

use function SlimApp\Utils\{json_encrypt, json_decrypt};


abstract class AbstractLoginController extends AbstractController
// Abstract controller for loggin in.
// Supports login/password and an optional two-factor TOTP code verification.
{
    const LOGIN_ATTEMPTS_TRACKING = FALSE;                          // should track failed attemps (for abuse protection) 
    const LOGIN_ATTEMPTS_INTERVAL = 900;                            // failed attemps tracking interval (reset timeout)
    const LOGIN_ATTEMPTS_MAX = 4;                                   // no. of attempts allowed
    const LOGIN_TEMPLATE_PASSWORD = 'login-password.twig.html';     // template for password page
    const LOGIN_TEMPLATE_2FA_TOTP = 'login-2fa-totp.twig.html';     // template for TOTP page
    const LOGIN_USE_2FA = FALSE;                                    // should 2FA verification be used
    const TARGET_ROUTE_PATH = '/home';                              // target path to redirect to after successful authentication


    public static function routes(\Slim\App $app)
    // add routes handled by this controller
    {
        // login page
        $app->get('/login', [static::class, 'login_page']);

        // login page - post response
        $app->post('/login', [static::class, 'login_action']); 

    }


    public function login_page(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    // Render login page on initial GET request.
    {
        $twig = Twig::fromRequest($request);

        // protection for abuse login requests
        if ($this->login_attempts_too_many($_SERVER['REMOTE_ADDR'])) {
            return $this->response_429_too_many($response, $twig);
        }

        // detect language, use browser Accept-Language header info as a default
        $params = $request->getQueryParams();
        $lang = $params['lang'] ?? $this->browser_prefered_language(['cs','en'], $_SERVER["HTTP_ACCEPT_LANGUAGE"], 'en', ['cs'=>'cz']);

        // render page
        $twig = Twig::fromRequest($request);
        return $twig->render($response, 'login-password.twig.html', [
            'lang' => $lang,
            'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
        ]);
    }


    public function login_action(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig = Twig::fromRequest($request);

        // protection for abuse login requests
        if ($this->login_attempts_too_many($_SERVER['REMOTE_ADDR'])) {
            return $this->response_429_too_many($response, $twig);
        }

        try {
            $post = (object) $request->getParsedBody();

            // stage 1: login and password
            if (isset($post->login) && isset($post->password)) {
                // validate the form input
                if (!$this->validate_input($post)) {
                    // increment failed requests and ban if too many
                    if ($this->login_attempts_too_many_with_increment($_SERVER['REMOTE_ADDR'])) {
                        return $this->response_429_too_many($response, $twig);
                    }
                    return $twig->render($response, static::LOGIN_TEMPLATE_PASSWORD, [
                        'lang' => $post->lang,
                        'error' => 'invalid_input',
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                }

                // verify login/password in LDAP service
                $user = $this->user_authenticate($post->login, $post->password);

                // on invalid login, render the login page with an error
                if (!$user) {
                    // increment failed requests and ban if too many
                    if ($this->login_attempts_too_many_with_increment($_SERVER['REMOTE_ADDR'])) {
                        return $this->response_429_too_many($response, $twig);
                    }
            
                    return $twig->render($response, static::LOGIN_TEMPLATE_PASSWORD, [
                        'lang' => $post->lang,
                        'error' => 'invalid_login',
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                }

                $this->log->info("Login authorization for {$post->login}", ['login'=>$post->login, 'ip'=>$_SERVER['REMOTE_ADDR']]);

                // // generate a new random TOTP secret for stage-2
                // $post->totp_secret = TOTP::generate()->getSecret();

                // verify 2FA TOTP secret is set
                if (static::LOGIN_USE_2FA && (strlen($user['totp_secret']) < $this->config->tfa->length)) {
                    $this->log->warning("Invalid 2FA secret for user {$post->login}", ['user_dn'=>$user['dn']]);
                    // show error
                    return $twig->render($response, static::LOGIN_TEMPLATE_PASSWORD, [
                        'lang' => $post->lang,
                        'error' => 'invalid_tfa_secret',
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                }

                // login is ok, render phase-2 page with 2FA code
                $this->login_attempts_reset($_SERVER['REMOTE_ADDR']);
                if (static::LOGIN_USE_2FA) {
                    return $twig->render($response, static::LOGIN_TEMPLATE_2FA_TOTP, [
                        'lang' => $post->lang,
                        // copy back the login/password contained in $post data to have it back with the TFA code (encrypted) 
                        'login_token' => json_encrypt((array)$post, $this->config->application->login_token_secret_key),
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                } else {
                    // redirect to the loggen-in home page
                    return $response
                        ->withHeader('Location', static::TARGET_ROUTE_PATH)
                        ->withStatus(302);  // 302-found
                }
            }

            // stage 2: 2FA TOTP code
            if (static::LOGIN_USE_2FA && isset($post->tfa_totp_code) && isset($post->login_token)) {
                // recover previously given login+password from login_token
                $post1 = json_decrypt($post->login_token, $this->config->application->login_token_secret_key);
                $post->login = $post1['login'];
                $post->password = $post1['password'];

                // verify login/password in LDAP service
                // the authentication should always work since the credentials are passed from the previou step,
                // if not, something is fishy
                $user = $this->user_authenticate($post->login, $post->password);
                if (!$user) {
                    $post->password = '****';
                    $this->log->notice("Invalid login authentication in 2FA phase", ['post'=>$post, 'ip'=>$_SERVER['REMOTE_ADDR']]);
                    return $this->response_400_invalid($response, $twig, new \Exception('invalid input'));
                }

                // verify TOTP secrete has been set
                if (strlen($user['totp_secret']) < $this->config->tfa->length) {
                    return $twig->render($response, static::LOGIN_TEMPLATE_PASSWORD, [
                        'lang' => $post->lang,
                        'error' => 'invalid_tfa_secret',
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                }

                // verify 2FA-TOTP code is valid
                $totp = \OTPHP\TOTP::create(
                    substr($user['totp_secret'], 0, $this->config->tfa->length),
                    period: $this->config->tfa->period,
                    digest: $this->config->tfa->digest,
                    digits: $this->config->tfa->digits,
                );
                if (!$totp->verify($post->tfa_totp_code)) $user = NULL;

                // on invalid login, render the login page with an error
                if (!$user) {
                    // protection for abuse login requests
                    if ($this->login_attempts_too_many_with_increment($_SERVER['REMOTE_ADDR'])) {
                        return $this->response_429_too_many($response, $twig);
                    }
                    // render page with error
                    return $twig->render($response, static::LOGIN_TEMPLATE_2FA_TOTP, [
                        'lang' => $post->lang,
                        'login_token' => $post->login_token,
                        'error' => 'invalid_tfa_code',
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                }

                // all OK, create new session, reset login counter
                $this->session_start($request, $user, $post, $totp);
                $this->login_attempts_reset($_SERVER['REMOTE_ADDR']);
                $this->log->info("Login successful for {$post->login}", ['login'=>$post->login, '2FA-mode'=>'TOTP/web', 'ip'=>$_SERVER['REMOTE_ADDR']]);

                // redirect to user-info page
                return $response
                    ->withHeader('Location', static::TARGET_ROUTE_PATH)
                    ->withStatus(302);  // 302-found
            }
            
        } catch (LDAPErrorException $e) {
            $this->log->error("LDAP error in login page processing", ['error'=>$e::class, 'message'=>$e->getMessage(), 'code'=>$e->getCode(), 'user'=>$post->login, 'ip'=>$_SERVER['REMOTE_ADDR']]);
            return $twig->render($response, static::LOGIN_TEMPLATE_PASSWORD, [
                'lang' => $post->lang,
                'error' => 'ldap_unavailable',
            ]);
        } catch (\Throwable $e) {
            $post->password = '****';
            $this->log->critical("Error in login page processing", ['error'=>$e::class, 'message'=>$e->getMessage(), 'code'=>$e->getCode(), 'post'=>$post, 'ip'=>$_SERVER['REMOTE_ADDR']]);
            return $this->response_500_server_error($response, $twig, $e);
        }

        // this should not be reached and if it is, the request has been malformed
        return $this->response_400_invalid($response, $twig, new \Exception('invalid input'));
    }



    //----------------------------------------
    // private section
    //----------------------------------------

    protected abstract function validate_input(object $post): bool;
    // Abstract function for user input validation.


    protected abstract function user_authenticate(string $login, #[\SensitiveParameter] string $password): bool|array;
    // Abstract function for user authentication.
    // Returns:
    //   FALSE if the authentication has failed.
    //   assoc. array with user information
    //   note: for 2FA-TOTP to work, the array shall contain 'totp_secret' field


    protected abstract function session_start(ServerRequestInterface $request, array $user, object $post, \OTPHP\TOTP $totp);
    // Abstract function for starting the session.
    // Typically, you want to the the following:
    //   $session = $request->getAttribute('session');
    //   $session->new([some session data]);


    protected function login_attempts_too_many(string $ip_address): bool
    // Abuse login protection: check if the client should be banned based on the number of invalid requests.
    {
        if (!static::LOGIN_ATTEMPTS_TRACKING) return FALSE;

        $this->cache->get("login-attempts/$ip_address", $login_attempts);
        return ($login_attempts >= self::LOGIN_ATTEMPTS_MAX);
    }


    protected function login_attempts_too_many_with_increment(string $ip_address): bool
    // Abuse login protection: check if the client should be banned based on the number of invalid requests.
    // Make an increment and eval the result.
    {
        if (!static::LOGIN_ATTEMPTS_TRACKING) return FALSE;

        $this->cache->get("login-attempts/$ip_address", $login_attempts);
        $this->cache->set("login-attempts/$ip_address", $login_attempts+1, 900);
        return (($login_attempts+1) >= self::LOGIN_ATTEMPTS_MAX);
    }


    protected function login_attempts_warning(string $ip_address): bool
    // Abuse login protection: give a ban warning.
    {
        if (!static::LOGIN_ATTEMPTS_TRACKING) return FALSE;

        $this->cache->get("login-attempts/$ip_address", $login_attempts);
        return ($login_attempts >= self::LOGIN_ATTEMPTS_MAX-1);
    }


    protected function login_attempts_reset(string $ip_address)
    // Abuse login protection: reset counter.
    {
        if (!static::LOGIN_ATTEMPTS_TRACKING) return;

        $this->cache->set("login-attempts/$ip_address", 0, 900);
    }

}
