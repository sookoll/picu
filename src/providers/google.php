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
    function checkCredentials() {
        if ($this->app['session']->get('googleCredentials')) {
            return true;
        } else {
            $refreshToken = null;
            if (is_file($this->conf['tokenFile'])) {
                $refreshToken = json_decode(file_get_contents($this->conf['tokenFile']), true);
            }
            if ($refreshToken) {
                $this->refreshCredentials($refreshToken['token']);
                if ($this->app['session']->get('googleCredentials')) {
                    return true;
                }
            }
        }
        return false;
    }
    function refreshCredentials($refreshToken) {
        $this->app['session']->set('googleCredentials', new UserRefreshCredentials(
            [
                $this->conf['auth_scope_url']
            ], [
                'client_id' => $this->conf['client_id'],
                'client_secret' => $this->conf['secret'],
                'refresh_token' => $refreshToken
            ]
        ));
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
            return $this->app->redirect('' . $authenticationUrl);// for some reason it won't redirect correctly without
        } else {
            // With the code returned by the OAuth flow, we can retrieve the refresh token.
            $oauth2->setCode($_GET['code']);
            $authToken = $oauth2->fetchAuthToken();
            $refreshToken = $authToken['access_token'];
            // store token for permanent access
            file_put_contents($this->conf['tokenFile'], json_encode(['token' => $refreshToken]));
            // The UserRefreshCredentials will use the refresh token to 'refresh' the credentials when
            // they expire.
            $this->refreshCredentials($refreshToken);
            // Return the user to the home page.
            return $this->app->redirect($this->app['request']->getUriForPath('/admin'));
        }
    }
    function setExists($set) {
        if (is_file($this->conf['cache_set'].$set.'.json')) {
            return true;
        }
        if (is_file($this->conf['cache_sets'])) {
            $sets = json_decode(file_get_contents($this->conf['cache_sets']), true);
            if (is_array($sets)) {
                $idset = array_map(function ($set) { return $set['id']; }, $sets['photoset']);
                return in_array($set, $idset);
            }
        }
        return false;
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
                        'total' => $album->getMediaItemsCount()
                    ];
                }
                file_put_contents($this->conf['cache_sets'], json_encode($sets));
            } catch (\Google\ApiCore\ApiException $e) {
                $this->app['monolog']->addError("Error (google): " . json_encode($e));
            }
        }
        return $sets['photoset'];
    }
    function clearSets($set = null) {
        if (!isset($set) && is_file($this->conf['cache_sets'])) {
            unlink($this->conf['cache_sets']);
        } else if (isset($set) && is_file($this->conf['cache_set'].$set.'.json')) {
            unlink($this->conf['cache_set'].$set.'.json');
        }
        return true;
    }
    function getMedia($set, $image) {
        $photos = [
            'photoset' => [
                'id' => $set,
                'photo' => [],
                'thumbnail' => null
            ]
        ];
        if(is_file($this->conf['cache_set'].$set.'.json')){
            $photos = json_decode(file_get_contents($this->conf['cache_set'].$set.'.json'),true);
        } else {
            if (!$this->checkCredentials()) {
                return $this->app->redirect($this->app['request']->getUriForPath('/admin/googleToken'));
            }
            $photosLibraryClient = new PhotosLibraryClient(['credentials' => $this->app['session']->get('googleCredentials')]);
            try {
                $album = $photosLibraryClient->getAlbum($set);
                $photos['photoset']['title'] = $album->getTitle();
                $photos['photoset']['total'] = $album->getTitle();
                $response = $photosLibraryClient->searchMediaItems(['albumId' => $album->getId()]);
                // By using iterateAllElements, pagination is handled for us.
                foreach ($response->iterateAllElements() as $item) {
                    if ($item->getMimeType() !== 'image/jpeg') {
                        continue;
                    }
                    $width_o = (int) $item->getMediaMetadata()->getWidth();
                    $height_o = (int) $item->getMediaMetadata()->getHeight();
                    $portrait = ($height_o > $width_o);
                    $photo = [
                        'id' => $item->getId(),
                        'title' => $item->getFilename(),
                        'datetaken' => $item->getMediaMetadata()->getCreationTime(),
                        'url_o' => $item->getBaseUrl() . '=w' . $width_o,
                        'height_o' => $height_o,
                        'width_o' => $width_o,
                        'width_c' => round((800 * $width_o) / $height_o),
                        'height_c' => 800
                    ];
                    // landscape
                    if (!$portrait){
                        $photo['th_h'] = $this->conf['th_size'];
                        $photo['th_w'] = round(($this->conf['th_size'] * $width_o) / $height_o);
                        $photo['th_mt'] = 0;
                        $photo['th_ml'] = -round(($photo['th_w'] - $this->conf['th_size'])/2);

                    }
                    // portrait
                    else {
                        $photo['th_w'] = $this->conf['th_size'];
                        if ($width_o > $height_o) {
                            $photo['th_h'] = round(($this->conf['th_size'] * $width_o) / $height_o);
                        } else {
                            $photo['th_h'] = round(($this->conf['th_size'] * $height_o) / $width_o);
                        }
                        $photo['th_ml'] = 0;
                        $photo['th_mt'] = -round(($photo['th_h'] - $this->conf['th_size'])/2);
                    }
                    // fallbacks
                    $photo['url_vb'] = $item->getBaseUrl() . '=w' . $this->conf['vb_size'];
                    $photo['url_z'] = $item->getBaseUrl() . '=w' . $photo['width_c'];
                    $photo['url_c'] = $item->getBaseUrl() . '=w' . $photo['width_c'];
                    $photo['width_vb'] = $width_o;
                    $photo['height_vb'] = $height_o;
                    $photos['photoset']['photo'][] = $photo;
                    // thumbnail
                    if ($photo['id'] === $album->getCoverPhotoMediaItemId()) {
                        $photos['photoset']['thumbnail'] = $photo;
                    }
                }
                if(count($photos['photoset']['photo']) === 0) {
                    $this->app->abort(404);
                }
                file_put_contents($this->conf['cache_set'].$set.'.json', json_encode($photos));
            } catch (\Google\ApiCore\ApiException $e) {
                $this->app['monolog']->addError("Error (google): " . json_encode($e));
            }
        }
        if ($image !== null) {
            foreach($photos['photoset']['photo'] as $k => $photo){
                // thumbnail
                if($photo['id'] == $image) {
                    $photos['photoset']['thumbnail'] = $photo;
                    break;
                }
            }
        }
        return $photos['photoset'];
    }
}
