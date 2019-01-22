<?php

class FlickrProvider {

    function __construct($app, $conf) {
        $this->app = $app;
        $this->conf = $conf;
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
            require_once $this->app['basePath'].'/lib/phpflickr/phpFlickr.php';
            $f = new phpFlickr($this->conf['key'], $this->conf['secret']);
            $f->setToken($this->conf['token']);
            //change this to the permissions you will need
            $f->auth('read');
            $sets = $f->photosets_getList();
            file_put_contents($this->conf['cache_sets'], json_encode($sets));
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
}
