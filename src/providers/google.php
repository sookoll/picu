<?php

use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Auth\OAuth2;
use Google\Photos\Library\V1\PhotosLibraryClient;

class GoogleProvider {

    function __construct($app, $conf) {
        $this->conf = $conf;
        $this->app = $app;
    }
    function isEnabled() {
        return $this->conf['enabled'];
    }
    function getSets() {
        $sets = [
            'photoset' => []
        ];
        if (is_file($this->conf['cache_sets'])) {
            $sets = json_decode(file_get_contents($this->conf['cache_sets']), true);
        } else {
            if (!$this->checkCredentials()) {
                return $this->app->redirect($this->app['request']->getUriForPath('/admin/googleToken'));
            }
            $photosLibraryClient = new PhotosLibraryClient(['credentials' => $this->app['session']->get('googleCredentials')]);
            try {
                $response = $photosLibraryClient->listAlbums();
                // By using iterateAllElements, pagination is handled for us.
                foreach ($response->iterateAllElements() as $album) {
                    $sets['photoset'][] = [
                        'id' => $album->getId(),
                        'title' => $album->getTitle(),
                        'coverPhotoBaseUrl' => $album->getCoverPhotoBaseUrl(),
                        'mediaItemsCount' => $album->getMediaItemsCount()
                    ];
                }

            } catch (\Google\ApiCore\ApiException $e) {
                $this->app['monolog']->addError("Error (google): " . json_encode($e));
            }
        }
        file_put_contents($this->conf['cache_sets'], json_encode($sets));
        return $sets['photoset'];
    }

    function checkCredentials() {
        if ($this->app['session']->get('googleCredentials')) {
            return true;
        }
        return false;
    }

    function auth() {
        $oauth2 = new OAuth2([
            'clientId' => $this->conf['client_id'],
            'clientSecret' => $this->conf['secret'],
            'authorizationUri' => $this->conf['authorization_url'],
            // Where to return the user to if they accept your request to access their account.
            // You must authorize this URI in the Google API Console.
            'redirectUri' => $this->conf['auth_redirect_url'],
            'tokenCredentialUri' => $this->conf['auth_token_url'],
            'scope' => [$this->conf['auth_scope_url']],
        ]);
        // The authorization URI will, upon redirecting, return a parameter called code.
        if (!isset($_GET['code'])) {
            $authenticationUrl = $oauth2->buildFullAuthorizationUri(['access_type' => 'offline']);
            var_dump($authenticationUrl);
            //header('Location: ' . $authenticationUrl);
            return $this->app->redirect($authenticationUrl);
        } else {
            // With the code returned by the OAuth flow, we can retrieve the refresh token.
            $oauth2->setCode($_GET['code']);
            $authToken = $oauth2->fetchAuthToken();
            $refreshToken = $authToken['access_token'];
            // The UserRefreshCredentials will use the refresh token to 'refresh' the credentials when
            // they expire.
            $this->app['session']->set('googleCredentials', new UserRefreshCredentials(
                [
                    $this->conf['auth_scope_url']
                ], [
                    'client_id' => $this->conf['client_id'],
                    'client_secret' => $this->conf['secret'],
                    'refresh_token' => $refreshToken
                ]
            ));
            // Return the user to the home page.
            return $this->app->redirect($this->app['request']->getUriForPath('/admin'));
        }
    }

    function clearSets($set = null) {
        if (!isset($set) && is_file($this->conf['cache_sets'])) {
            unlink($this->conf['cache_sets']);
        } else if (isset($set) && is_file($this->conf['cache_set'].$set.'.json')) {
            unlink($this->conf['cache_set'].$set.'.json');
        }
        return true;
    }
}
