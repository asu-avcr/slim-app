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
    protected const REQUIRED_CONTAINERS = ['log', 'cache'];

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

            // stage 1: verify login and password
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

                // verify login/password, get user data from LDAP
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

                $this->log->info("Login authorization for {$post->login}", ['login'=>$post->login]);

                // verify TOTP secret has been set in use data, has minimal length and the length is a power of 2 [pow of 2: (($n & ($n-1)) == 0)]
                $n = strlen($user['totp_secret']); 
                if (static::LOGIN_USE_2FA && ( ($n<16) || (($n & ($n-1)) > 0) )) {
                    $this->log->warning("Invalid 2FA secret for user {$post->login}", ['user'=>$user]);
                    // show error
                    return $twig->render($response, static::LOGIN_TEMPLATE_PASSWORD, [
                        'lang' => $post->lang,
                        'error' => 'invalid_tfa_secret',
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                }

                // login is ok, reset the invalid login counter
                $this->login_attempts_reset($_SERVER['REMOTE_ADDR']);

                // if 2FA is enabled, render phase-2 page, else redirect to the target page
                if (static::LOGIN_USE_2FA) {
                    return $twig->render($response, static::LOGIN_TEMPLATE_2FA_TOTP, [
                        'lang' => $post->lang,
                        'user' => $user,
                        // copy back the login/password contained in $post data to have it back with the TFA code (encrypted) 
                        'login_token' => json_encrypt((array)$post, $this->config->application->login_token_secret_key),
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
                $user_credentials = json_decrypt($post->login_token, $this->config->application->login_token_secret_key);

                // verify login/password 
                // the authentication should always work since the credentials are passed from the previou step,
                // if not, something is fishy
                $user = $this->user_authenticate($user_credentials['login'], $user_credentials['password']);
                if (!$user) {
                    $this->log->notice("Invalid login authentication in 2FA phase", ['login'=>$user_credentials['login'], 'post'=>$post]);
                    return $this->response_400_invalid($response, $twig, new \Exception('invalid input'));
                }

                // verify TOTP code
                $totp = $this->totp_verify($user['totp_secret'], $post);

                // on invalid code, render the 2FA page with an error
                if (!$totp) {
                    // protection for abuse login requests
                    if ($this->login_attempts_too_many_with_increment($_SERVER['REMOTE_ADDR'])) {
                        return $this->response_429_too_many($response, $twig);
                    }
                    // render page with error
                    return $twig->render($response, static::LOGIN_TEMPLATE_2FA_TOTP, [
                        ...(array)$post,  // pass back the original POST data
                        'user' => $user,
                        'error' => 'invalid_tfa_code',
                        'login_attempts_warning' => $this->login_attempts_warning($_SERVER['REMOTE_ADDR']),
                    ]);
                }

                // all OK, create new session, reset login counter
                $this->session_start($request, $user, $post, $totp);
                $this->login_attempts_reset($_SERVER['REMOTE_ADDR']);
                $this->log->info("Login successful for {$user_credentials['login']}", ['login'=>$user_credentials['login'], '2FA-mode'=>'TOTP/web']);

                // redirect to user-info page
                return $response
                    ->withHeader('Location', static::TARGET_ROUTE_PATH)
                    ->withStatus(302);  // 302-found
            }
            
        } catch (LDAPErrorException $e) {
            $this->log->error("LDAP error in login page processing", ['error'=>$e::class, 'message'=>$e->getMessage(), 'code'=>$e->getCode(), 'user'=>$user_credentials['login']??NULL]);
            return $twig->render($response, static::LOGIN_TEMPLATE_PASSWORD, [
                'lang' => $post->lang,
                'error' => 'ldap_unavailable',
            ]);
        } catch (\Throwable $e) {
            $post->password = '****';
            $this->log->critical("Error in login page processing", ['error'=>$e::class, 'message'=>$e->getMessage(), 'code'=>$e->getCode(), 'post'=>$post, 'user'=>$user_credentials['login']??NULL]);
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


    protected abstract function totp_verify(string $totp_secret, object $post_data): \OTPHP\TOTP|null;
    // Abstract function for TOTP code verification.
    // Returns:
    //   \OTPHP\TOTP object if if the verification is successful, NULL if not.


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
