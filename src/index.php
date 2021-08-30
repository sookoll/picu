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

require_once __DIR__.'/curlDownload.php';

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
$providers = [
    'flickr' => new FlickrProvider($app, $app['conf']['providers']['flickr']),
    'google' => new GoogleProvider($app, $app['conf']['providers']['google'])
];

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
        $app['session']->set('user', array('username' => $user));
        return $app->redirect($app['request']->getUriForPath('/admin'));
    }
    return $app->redirect($app['request']->getUriForPath('/login'));
});

// Route - admin
$app->get('/admin', function () use ($app, $providers) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect($app['request']->getUriForPath('/login'));
    }
    // create dir
    if(!is_dir($app['conf']['cacheDir'])){
        mkdir($app['conf']['cacheDir']);
    }
    if(!is_dir($app['conf']['secretDir'])){
        mkdir($app['conf']['secretDir']);
    }
    // enabled service providers
    $sets = [
        'flickr' => [],
        'google' => []
    ];
    foreach ($providers as $key => $provider) {
        if ($provider->isEnabled()) {
            $sets[$key] = $provider->getSets();
        }
    }
    return $app['twig']->render('admin.html',array(
        'sets' => $sets,
        'user' => $user,
        'google' => [
            'enabled' => $providers['google']->isEnabled(),
            'auth' => $providers['google']->checkCredentials()
        ]
    ));
});

// Route - google auth
$app->get('/admin/googleToken', function () use ($app, $providers) {
    if (null === $app['session']->get('user')) {
        return $app->redirect($app['request']->getUriForPath('/login'));
    }
    // cache or google api
    if ($providers['google']->isEnabled()) {
        return $providers['google']->auth();
    } else {
        return $app->redirect($app['request']->getUriForPath('/admin'));
    }
});

// Route - logout
$app->get('/logout', function () use ($app, $providers) {
    $app['session']->set('user', null);
    if ($providers['google']->isEnabled()) {
        $providers['google']->revoke();
    }
    return $app->redirect($app['request']->getUriForPath('/admin'));
});

// Route - clear cache
$clear_cache = function($set = null) use ($app, $providers) {
    $status = 0;
    if (null === $user = $app['session']->get('user')) {
        return $app->abort(404);
    }
    // delete sets file
    if ($set === null) {
        foreach ($providers as $key => $provider) {
            $provider->clearSets();
        }
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
        foreach ($providers as $key => $provider) {
            $provider->clearSets($set);
        }
        $status = 1;
    }
    return json_encode(array(
        'status' => $status
    ));
};
$app->get('/clear-cache', $clear_cache);
$app->get('/clear-cache/{set}', $clear_cache);

// Route - change provider
$app->get('/admin/copyTo/{set}/{from}/{to}', function ($set, $from, $to) use ($app, $providers) {
    if (null === $app['session']->get('user') || !array_key_exists($from, $providers) || !array_key_exists($to, $providers)) {
        $app['monolog']->addError("Error: $set $from $to ");
        return $app->abort(404);
    }
    $set = $app->escape($set);
    $photoset = $providers[$from]->getMedia($set, null);
    $success = $providers[$to]->createSet($photoset);
    if ($success) {
        return $clear_cache('all');
    }
    return json_encode(array(
        'status' => 0
    ));
});

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
$album_view = function($set, $image = null) use ($app, $providers) {
    $set = $app->escape($set);
    $provider = null;
    foreach ($providers as $key => $pr) {
        if ($pr->setExists($set)) {
            $provider = $pr;
            break;
        }
    }
    if ($provider) {
        $photoset = $provider->getMedia($set, $image);
        if (!$photoset) {
            $app->abort(404);
        }
        return $app['twig']->render('set.html', array(
            'conf' => $app['conf'],
            'set' => $photoset
        ));
    }
    $app->abort(404);
};

// Route - album view
$app->get('/a/{set}/', $album_view);
$app->get('/a/{set}/{image}', $album_view);
//$app->get('/a/{set}/{photo}/fs', $album_view);

// Route - picture-only view
$app->get('/p/{set}/{photo}', function($set, $photo) use ($app, $providers) {
    $set = $app->escape($set);
    $photo = $app->escape($photo);
    $provider = null;
    foreach ($providers as $key => $pr) {
        if ($pr->setExists($set)) {
            $provider = $pr;
            break;
        }
    }
    if ($provider) {
        $photoset = $provider->getMedia($set, $image);
        $p = null;
        foreach($photoset['photo'] as $k => $v){
            if($v['id'] == $photo){
                $p = $v;
                $photoset['thumbnail'] = $v;
                break;
            }
        }
        if($p === null)
            $app->abort(404);
        return $app['twig']->render('photo.html',array('photo'=>$p, 'set' => $photoset));
    }
    $app->abort(404);
})->bind('photo');

// Route - photo download
$app->get('/d/{set}/{photo}', function($set, $photo) use ($app, $providers) {
    $set = $app->escape($set);
    $photo = $app->escape($photo);
    $provider = null;
    foreach ($providers as $key => $pr) {
        if ($pr->setExists($set)) {
            $provider = $pr;
            break;
        }
    }
    if ($provider) {
        $photoset = $provider->getMedia($set, $image);
        $src = null;
        foreach($photoset['photo'] as $k => $v){
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
                    return $app->abort(404);
            }
            $app['monolog']->addDebug("download : $filename $file_extension $ctype");
            $response = new Response($photo[0]);
            $response->headers->set('Content-type', $ctype);
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            $response->headers->set('Content-length', $photo[1]);
            return $response;
        }
    }
    return $app->abort(404);
});

$app->run();

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
