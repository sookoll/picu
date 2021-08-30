<?php

require_once __DIR__.'/../curlDownload.php';

use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Auth\OAuth2;
use Google\Photos\Library\V1\PhotosLibraryClient;
use Google\Photos\Library\V1\Album;
use Google\Rpc\Code;

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
        }
        return false;
    }
    function refreshCredentials($refreshToken = null) {
        // check if we have tokens stored
        if ($refreshToken === null && is_file($this->conf['tokenFile'])) {
            $token = json_decode(file_get_contents($this->conf['tokenFile']), true);
            //$refreshToken = $token['access_token'];
            $refreshToken = $token['refresh_token'];
        }
        if ($refreshToken) {
            $this->app['session']->set('googleCredentials', new UserRefreshCredentials(
                [$this->conf['auth_scope_url']],
                [
                    'client_id' => $this->conf['client_id'],
                    'client_secret' => $this->conf['secret'],
                    'refresh_token' => $refreshToken
                ]
            ));
            return true;
        }
        return false;
    }
    function auth() {
        if ($this->checkCredentials() || $this->refreshCredentials()) {
            return $this->app->redirect($this->app['request']->getUriForPath('/admin'));
        }
        $oauth2 = new OAuth2([
            'clientId' => $this->conf['client_id'],
            'clientSecret' => $this->conf['secret'],
            'authorizationUri' => $this->conf['authorization_url'],
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
            //$refreshToken = $authToken['access_token'];
            $refreshToken = $authToken['refresh_token'];
            // store token for permanent access
            file_put_contents($this->conf['tokenFile'], json_encode($authToken));
            $this->app['monolog']->addDebug("Store google auth token: " . json_encode($authToken));
            // The UserRefreshCredentials will use the refresh token to 'refresh' the credentials when
            // they expire.
            $this->refreshCredentials($refreshToken);
            // Return the user to the home page.
            return $this->app->redirect($this->app['request']->getUriForPath('/admin'));
        }
    }
    function revoke() {
        $this->app['session']->set('googleCredentials', null);

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
        if (!$this->checkCredentials() && !$this->refreshCredentials()) {
            return $sets['photoset'];
        }
        if (is_file($this->conf['cache_sets'])) {
            $sets = json_decode(file_get_contents($this->conf['cache_sets']), true);
        } else {
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
        if (!$this->checkCredentials() && !$this->refreshCredentials()) {
            return $this->app->abort(404);
        }
        if(is_file($this->conf['cache_set'].$set.'.json')){
            $photos = json_decode(file_get_contents($this->conf['cache_set'].$set.'.json'),true);
        } else {
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
    function createSet($photoset) {
        if (!$this->checkCredentials() && !$this->refreshCredentials()) {
            return $this->app->abort(404);
        }
        $photosLibraryClient = new PhotosLibraryClient(['credentials' => $this->app['session']->get('googleCredentials')]);
        $newAlbum = new Album();
        $newAlbum->setTitle($photoset['title']);
        try {
            $createdAlbum = $photosLibraryClient->createAlbum($newAlbum);
            $albumId = $createdAlbum->getId();
            $newMediaItems = [];
            for ($i = 0; $i < $photoset['total']; $i++) {
                $photo = curl_download($src);
                $ext = pathinfo($photoset['photo'][$i]['url_o'], PATHINFO_EXTENSION);
                $name = $photoset['photo'][$i]['title'].'.'.$ext;
                $uploadToken = $photosLibraryClient->upload($photo, $name);
                $newMediaItems[] = PhotosLibraryResourceFactory::newMediaItem($uploadToken);
            }
            $batchCreateResponse = $photosLibraryClient->batchCreateMediaItems($newMediaItems, ['albumId' => $albumId]);
        } catch (\Google\ApiCore\ApiException $e) {
            $this->app['monolog']->addError("Error (google): " . json_encode($e));
            return $this->app->abort(404);
        }
        // An OK status (i.e., an exception wasn't thrown above) isn't sufficient to say all the items
        // succeeded. You also need to check the status in each NewMediaItemResult.s
        $statuses = [];
        foreach ($batchCreateResponse->getNewMediaItemResults() as $itemResult) {
            $status = $itemResult->getStatus();
            if ($status->getCode() != Code::OK) {
                $statuses[] = $status;
            }
        }
        if (count($statuses) === 0) {
            return true;
        }
        $this->app['monolog']->addError("Error (google): " . json_encode($statuses));
        return false;
    }
}
