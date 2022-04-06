<?php

class FlickrProvider {

    function __construct($app, $conf) {
        $this->app = $app;
        $this->conf = $conf;
    }
    function isEnabled() {
        return $this->conf['enabled'];
    }
    function setExists($set) {
        if (is_file($this->conf['cache_set'].$set.'.json')) {
            return true;
        }
        if (is_file($this->conf['cache_sets'])) {
            $sets = json_decode(file_get_contents($this->conf['cache_sets']), true);
            $idset = array_map(function ($set) { return $set['id']; }, $sets['photoset']);
            return in_array($set, $idset);
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
    function getMedia($set, $image) {
        $photos = [
            'photoset' => []
        ];
        if(is_file($this->conf['cache_set'].$set.'.json')){
            $photos = json_decode(file_get_contents($this->conf['cache_set'].$set.'.json'),true);
        } else {
            require_once $this->app['basePath'].'/lib/phpflickr/phpFlickr.php';
            $f = new phpFlickr($this->conf['key'], $this->conf['secret']);
            $f->setToken($this->conf['token']);
            //change this to the permissions you will need
            $f->auth('read');

            $photos = $f->photosets_getPhotos($set, 'date_taken, geo, tags, url_o, url_'.$this->conf['vb_size'].', url_z, url_c');
            if(!isset($photos) || !isset($photos['photoset']) || !isset($photos['photoset']['photo'])) {
                $this->app->abort(404);
            }

            // description
            $setInfo = array_filter($this->getSets(), function($s) use($set) {
                return $s['id'] == $set;
            });
            if (count($setInfo) && $setInfo[0]['description'] && !empty($setInfo[0]['description']['_content'])) {
                $photos['photoset']['description'] = $setInfo[0]['description']['_content'];
            }

            // calculate thumb parameters, originals are wrong in portrait
            foreach ($photos['photoset']['photo'] as $k => $photo) {
                $width_o = (int) $photo['width_o'];
                $height_o = (int) $photo['height_o'];
                $portrait = ((isset($photo['height_z']) && (int) $photo['height_z'] > (int) $photo['width_z']) || ($height_o > $width_o));
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
                $photo['url_vb'] = isset($photo['url_'.$this->conf['vb_size']]) ? $photo['url_'.$this->conf['vb_size']] : $photo['url_o'];
                $photo['url_z'] = isset($photo['url_z']) ? $photo['url_z'] : $photo['url_o'];
                $photo['width_vb'] = isset($photo['width_'.$this->conf['vb_size']]) ? $photo['width_'.$this->conf['vb_size']] : $width_o;
                $photo['height_vb'] = isset($photo['height_'.$this->conf['vb_size']]) ? $photo['height_'.$this->conf['vb_size']] : $height_o;
                $photos['photoset']['photo'][$k] = $photo;
                // thumbnail
                if ($photo['id'] == $photos['photoset']['primary']) {
                    $photos['photoset']['thumbnail'] = $photo;
                }
            }
            file_put_contents($this->conf['cache_set'].$set.'.json', json_encode($photos));
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
