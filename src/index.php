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

// Namespaces
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\SessionServiceProvider;

// Init application
$app = new Silex\Application();

// config
$app['debug'] = false;
$app['conf'] = $conf;

// Session
$app->register(new SessionServiceProvider());

// Twig templates
$app->register(new TwigServiceProvider(), array(
	'twig.path' => array(
		__DIR__.'/ui'
	)
));

// Error handling
$app->error(function (\Exception $e, $code) use ($app) {
	if ($app['debug'])
		return;
	
	switch ($code) {
        // 404 page
        case 404:
            $message = $app['twig']->render('404.html');
			break;
        // default
        default:
            $message = 'We are sorry, but something went terribly wrong.';
    }
    return new Response($message);
});

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
        return $app->redirect($app['request']->getUriForPath('/').'admin');
    }
	return $app->redirect($app['request']->getUriForPath('/').'login');
});

// Route - admin
$app->get('/admin', function () use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect($app['request']->getUriForPath('/').'login');
    }
	
	// cache or flickr api
	if(is_file($app['conf']['cache_sets'])){
		$sets = json_decode(file_get_contents($app['conf']['cache_sets']),true);
	} else {
		require_once __DIR__.'/vendor/phpflickr/phpFlickr.php';
		$f = new phpFlickr($app['conf']['key'], $app['conf']['secret']);
		$f->setToken($app['conf']['token']);

		//change this to the permissions you will need
		$f->auth("read");

		$sets = $f->photosets_getList();
		file_put_contents($app['conf']['cache_sets'],json_encode($sets));
	}
	
    return $app['twig']->render('admin.html',array('sets'=>$sets['photoset'],'user'=>$user));
});

// Route - logout
$app->get('/logout', function () use ($app) {
	$app['session']->set('user', null);
	return $app->redirect($app['request']->getUriForPath('/').'admin');
});

// Route - clear cache
$clear_cache = function($set = null) use ($app) {
	
    $status = 0;
    if (null === $user = $app['session']->get('user')) {
        $app->abort(404);
    }
    // delete sets file
    if($set === null && is_file($app['conf']['cache_sets']) && unlink($app['conf']['cache_sets']))
        $status = 1;
    // delete all
    else if($set == 'all'){
        $files = glob(__DIR__.'/cache/*'); // get all file names
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
        if(is_file(__DIR__.'/cache/flickr_set_'.$set.'.json') && unlink(__DIR__.'/cache/flickr_set_'.$set.'.json')) {
            $status = 1;
        }
    }
    
	return json_encode(array(
		'status' => $status
	));
};
$app->get('/clear-cache', $clear_cache);
$app->get('/clear-cache/{set}', $clear_cache);

// Route - album view method
$album_view = function($set,$image = null) use ($app) {
    $id = $app->escape($set);
	
	// cache or flickr api
	if(is_file(__DIR__.'/cache/flickr_set_'.$id.'.json')){
		$photos = json_decode(file_get_contents(__DIR__.'/cache/flickr_set_'.$id.'.json'),true);
        if($image !== null) {
            foreach($photos['photoset']['photo'] as $k => $photo){
                // thumbnail
                if($photo['id'] == $image) {
                    $photos['photoset']['thumbnail'] = $photo;
                    break;
                }
            }
        }
	} else {
		require_once __DIR__.'/vendor/phpflickr/phpFlickr.php';
		$f = new phpFlickr($app['conf']['key'], $app['conf']['secret']);
		$f->setToken($app['conf']['token']);

		//change this to the permissions you will need
		$f->auth("read");
		$photos = $f->photosets_getPhotos($id, 'date_taken, geo, tags, url_o, url_'.$app['conf']['vb_size'].', url_z');
        if(!isset($photos) || !isset($photos['photoset']) || !isset($photos['photoset']['photo']))
            $app->abort(404);
		// calculate thumb parameters
		foreach($photos['photoset']['photo'] as $k => $photo){
			// landscape
			if($photo['width_z'] > $photo['height_z']){
				$photo['th_h'] = $app['conf']['th_size'];
				$photo['th_w'] = ($app['conf']['th_size'] * $photo['width_z']) / $photo['height_z'];
				$photo['th_mt'] = 0;
				$photo['th_ml'] = -(($photo['th_w'] - $app['conf']['th_size'])/2);
			}
			// portrait
			else {
				$photo['th_w'] = $app['conf']['th_size'];
				$photo['th_h'] = ($app['conf']['th_size'] * $photo['height_z']) / $photo['width_z'];
				$photo['th_ml'] = 0;
				$photo['th_mt'] = -(($photo['th_h'] - $app['conf']['th_size'])/2);
			}
			$photo['url_vb'] = isset($photo['url_'.$app['conf']['vb_size']]) ? $photo['url_'.$app['conf']['vb_size']] : $photo['url_o'];
			$photo['width_vb'] = isset($photo['width_'.$app['conf']['vb_size']]) ? $photo['width_'.$app['conf']['vb_size']] : $photo['width_o'];
			$photo['height_vb'] = isset($photo['height_'.$app['conf']['vb_size']]) ? $photo['height_'.$app['conf']['vb_size']] : $photo['height_o'];
			$photos['photoset']['photo'][$k] = $photo;
            // thumbnail
            if($photo['id'] == $photos['photoset']['primary']) {
                $photos['photoset']['thumbnail'] = $photo;
            }
		}
		file_put_contents(__DIR__.'/cache/flickr_set_'.$id.'.json',json_encode($photos));
        if($image !== null) {
            foreach($photos['photoset']['photo'] as $k => $photo){
                // thumbnail
                if($photo['id'] == $image) {
                    $photos['photoset']['thumbnail'] = $photo;
                    break;
                }
            }
        }
	}
		
	return $app['twig']->render('set.html',array('set'=>$photos['photoset']));
};

// Route - album view
$app->get('/a/{set}/', $album_view);
$app->get('/a/{set}/{image}', $album_view);
//$app->get('/a/{set}/{photo}/fs', $album_view);

// Route - photo download
$app->get('/d/{set}/{photo}', function($set, $photo) use ($app) {
	$set = $app->escape($set);
    $photo = $app->escape($photo);
    
    // read file
	if(!is_file(__DIR__.'/cache/flickr_set_'.$set.'.json'))
	   $app->abort(404);
    
    $photos = json_decode(file_get_contents(__DIR__.'/cache/flickr_set_'.$set.'.json'),true);
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