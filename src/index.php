<?php
/*
 * Picu
 * Sookolli Kaardid OÜ
 * 2014-09
 */

// Config
require_once __DIR__.'/config.php';

// Add autoloader
require_once __DIR__.'/vendor/autoload.php';

require_once __DIR__.'/providers/flickr.php';
require_once __DIR__.'/providers/google.php';

// Namespaces
use Silex\Provider\MonologServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Application\UrlGeneratorTrait;

// Init application
$app = new Silex\Application();

// config
$app['conf'] = $conf;
$app['basePath'] = __DIR__;
$app['debug'] = $app['conf']['log_level'] == 'DEBUG' ? true : false;


// Logging
$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/_logs/'.date("Y-m-d").'.log',
    'monolog.level' => $app['conf']['log_level']
));

// Session
$app->register(new SessionServiceProvider());

// Twig templates
$app->register(new TwigServiceProvider(), array(
    'twig.path' => array(
        __DIR__.'/ui'
    )
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Error handling
$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }
    switch ($code) {
        // 404 page
        case 404:
            $message = $app['twig']->render('404.html');
            break;
        // default
        default:
            $message = 'We are sorry, but something went terribly wrong.';
            $app['monolog']->addError("Error: $code " . json_encode($e));
    }
    return new Response($message);
});

// Providers
$flickr = new FlickrProvider($app, $app['conf']['providers']['flickr']);
$google = new GoogleProvider($app, $app['conf']['providers']['google']);

// Route - root
$app->get('/', function () use ($app) {
    $app->abort(404);
});

// Route - login
$app->get('/login', function () use ($app) {
    return $app['twig']->render('login.html');
});

// Route - login
$app->post('/login', function (Request $request) use ($app) {

    $user = $app->escape($request->get('user_id'));
    $pass = $app->escape($request->get('user_pwd'));

    if ($app['conf']['user'] === $user && $app['conf']['passwd'] === $pass) {
        $app['session']->set('user', array('username' => $username));
        return $app->redirect($app['request']->getUriForPath('/admin'));
    }
    return $app->redirect($app['request']->getUriForPath('/login'));
});

// Route - admin
$app->get('/admin', function () use ($app, $flickr, $google) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect($app['request']->getUriForPath('/login'));
    }
    // create dir
    if(!is_dir($app['conf']['cacheDir'])){
        mkdir($app['conf']['cacheDir']);
    }
    // enabled service providers
    $sets = [
        'flickr' => [],
        'google' => []
    ];
    // cache or flickr api
    if ($flickr->isEnabled()) {
        $sets['flickr'] = $flickr->getSets();
    }
    // cache or google api
    if ($google->isEnabled()) {
        $sets['google'] = $google->getSets();
    }
    return $app['twig']->render('admin.html',array('sets'=>$sets,'user'=>$user,'google'=>$google->isEnabled()));
});

// Route - google auth
$app->get('/admin/googleToken', function () use ($app, $google) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect($app['request']->getUriForPath('/login'));
    }
    // cache or google api
    if ($google->isEnabled()) {
        return $google->auth();
    } else {
        return $app->redirect($app['request']->getUriForPath('/admin'));
    }
});

// Route - logout
$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect($app['request']->getUriForPath('/admin'));
});

// Route - clear cache
$clear_cache = function($set = null) use ($app, $flickr, $google) {

    $status = 0;
    if (null === $user = $app['session']->get('user')) {
        $app->abort(404);
    }
    // delete sets file
    if ($set === null) {
        $flickr->clearSets();
        $google->clearSets();
        $status = 1;
    }
    // delete all
    else if ($set == 'all') {
        $files = glob($app['conf']['cacheDir'].'/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file) && unlink($file))
                $status = 1;
            else {
                $status = 0;
                break;
            }
        }
    }
    // delete set
    else {
        $flickr->clearSets($set);
        $google->clearSets($set);
        $status = 1;
    }
    return json_encode(array(
        'status' => $status
    ));
};
$app->get('/clear-cache', $clear_cache);
$app->get('/clear-cache/{set}', $clear_cache);

/**
 * Upload image
 * TODO: google + move to flickr provider class
 */
$app->post('/upload/{set}', function ($set) use ($app) {
    $status = 0;
    $i = 0;
    if ($app['conf']['upload']['anon'] === false && null === $user = $app['session']->get('user')) {
        $app->abort(404);
    }
    if (empty($_FILES['files']) || $_FILES['files']['error'][0] != UPLOAD_ERR_OK) {
        $app->abort(400);
    }
    $valid = validateUpload($_FILES['files']);
    if (!$valid) {
        return $app->json(array(
            'status' => $status,
            'msg' => 'Not valid'
        ));
    }
    require_once __DIR__.'/vendor/phpflickr/phpFlickr.php';
    $f = new phpFlickr($app['conf']['key'], $app['conf']['secret']);
    $f->setToken($app['conf']['token']);
    //change this to the permissions you will need
    $f->auth("write");

    // create dir
    if(!is_dir(__DIR__.'/_tmp')){
        mkdir(__DIR__.'/_tmp');
    }

    $files = $_FILES['files'];
    $photos = array();

    do {
        $name = basename($files['name'][$i]);
        $target_file = __DIR__.'/_tmp/' . $name;

        if (move_uploaded_file($files['tmp_name'][$i], $target_file)){
            $status = 1;
        }
        // send to flickr
        $uploaded = $f->sync_upload($target_file, $name);

        if (!empty($uploaded)) {
            // assign to album
            $result = $f->photosets_addPhoto($set, $uploaded);
            // delete cache file
            unlink($target_file);
            // image url
            $photo = array(
                $app['request']->getScheme() . '://',
                $app['request']->getHost(),
                $app['url_generator']->generate('photo', array(
                    'set' => $set,
                    'photo' => $uploaded
                ))
            );
            $photos[$i] = array(
                'name' => $name,
                'url' => implode('', $photo)
            );
            $status = 2;
        } else {
            $app['monolog']->addError("Upload error ($target_file): $uploaded");
        }

        $i++;

    } while ($i < count($files['name']));

    return $app->json(array(
        'status' => $status,
        'files' => $photos
    ));

})->bind('upload');

// Route - album view method
$album_view = function($set, $image = null) use ($app, $flickr, $google) {
    $id = $app->escape($set);
    // cache or flickr api
    $isFlickrSet = $flickr->isSet($id);
    $isGoogleSet = $google->isSet($id);
    $provider = $isFlickrSet ? $flickr : $google;
    $photoset = $provider->getMedia($id, $image);
    return $app['twig']->render('set.html', array(
        'conf' => $app['conf'],
        'set' => $photoset
    ));
};

// Route - album view
$app->get('/a/{set}/', $album_view);
$app->get('/a/{set}/{image}', $album_view);
//$app->get('/a/{set}/{photo}/fs', $album_view);

// Route - picture-only view
$app->get('/p/{set}/{photo}', function($set, $photo) use ($app) {
    $set = $app->escape($set);
    $photo = $app->escape($photo);
    $p = null;

    // read file
    if(!is_file(__DIR__.'/_cache/flickr_set_'.$set.'.json'))
       $app->abort(404);

    $photos = json_decode(file_get_contents(__DIR__.'/_cache/flickr_set_'.$set.'.json'),true);
    if(!isset($photos) || !isset($photos['photoset']) || !isset($photos['photoset']['photo'])) {
        $app->abort(404);
    }

    $photoset = $photos['photoset'];

    foreach($photoset['photo'] as $k => $v){
        if($v['id'] == $photo){
            $p = $v;
            $photoset['thumbnail'] = $v;
            break;
        }
    }

    if($p === null)
        $app->abort(404);

    return $app['twig']->render('photo.html',array('photo'=>$p, 'set_id' => $set, 'set' => $photoset));

})->bind('photo');

// Route - photo download
$app->get('/d/{set}/{photo}', function($set, $photo) use ($app) {
    $set = $app->escape($set);
    $photo = $app->escape($photo);

    // read file
    if(!is_file(__DIR__.'/_cache/flickr_set_'.$set.'.json'))
       $app->abort(404);

    $photos = json_decode(file_get_contents(__DIR__.'/_cache/flickr_set_'.$set.'.json'),true);
    if(!isset($photos) || !isset($photos['photoset']) || !isset($photos['photoset']['photo']))
        $app->abort(404);
    $src = null;
    foreach($photos['photoset']['photo'] as $k => $v){
        if($v['id'] == $photo && isset($v['url_o'])){
            $src = $v['url_o'];
            break;
        }
    }

    if($src !== null){
        $photo = curl_download($src);
        $filename = basename($src);
        $file_extension = strtolower(substr(strrchr($filename,"."),1));

        switch( $file_extension ) {
            case "gif": $ctype="image/gif"; break;
            case "png": $ctype="image/png"; break;
            case "jpeg":
            case "jpg": $ctype="image/jpg"; break;
            default:
        }

        header('Content-type: ' . $ctype);
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        return $photo;
    }

});

$app->run();

function curl_download($Url){
    // is cURL installed yet?
    if (!function_exists('curl_init')){
        die('Sorry cURL is not installed!');
    }
    // OK cool - then let's create a new cURL resource handle
    $ch = curl_init();
    // Now set some options (most are optional)
    // Set URL to download
    curl_setopt($ch, CURLOPT_URL, $Url);
    // Set a referer
    curl_setopt($ch, CURLOPT_REFERER, $conf['referer']);
    // User agent
    curl_setopt($ch, CURLOPT_USERAGENT, "MozillaXYZ/1.0");
    // Include header in result? (0 = yes, 1 = no)
    curl_setopt($ch, CURLOPT_HEADER, 0);
    // Should cURL return or print out the data? (true = return, false = print)
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Timeout in seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    // Download the given URL, and return output
    $output = curl_exec($ch);
    // Close the cURL resource, and free system resources
    curl_close($ch);
    return $output;
}

function validateUpload($files) {
    global $app;
    $i = 0;

    do {
        // file type
        if (!preg_match($app['conf']['upload']['accept_file_types'], $files['name'][$i])) {
            $app['monolog']->addError("Validate::accept_file_types[$i]: " . json_encode(preg_match($app['conf']['upload']['accept_file_types'], $files['name'][$i])));
            return false;
        }

        // file size
        $file_size = get_file_size($files['tmp_name'][$i]);

        if ($file_size > $app['conf']['upload']['max_file_size']) {
            $app['monolog']->addError("Validate::max_file_size: $file_size");
            return false;
        }

        $i++;

    } while ($i < count($files['tmp_name']));

    return true;
}

function get_file_size($file_path) {
    return filesize($file_path);
}
