<?php

define('PROXY_START', microtime(true));
define('CONFIG_FOLDER','configs');

require("vendor/autoload.php");

use Proxy\Http\Request;
use Proxy\Http\Response;
use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\FilterEvent;
use Proxy\Config;
use Proxy\Proxy;

// start the session
session_start();

// load config...
Config::load(CONFIG_FOLDER);
Config::load('./config.php',true);


if(isset($_POST['token']) && isset($_POST['config'])){
    //have token, modify config.
    if(Config::get('token') != $_POST['token']){
        die("0|Error|Token 错误");
    }

    $content = urldecode(base64_decode($_POST['config']));
    if(file_put_contents(CONFIG_FOLDER.'/'.config_file(),$content))
        die("1|Success|同步成功");

    die("0|Error|写入失败");
}

if(!Config::get('app_key')){
	die("域名未配置，请登陆 Wave 后台配置。");
}

if(!function_exists('curl_version')){
	die("cURL extension is not loaded!");
}

session_write_close();

// decode q parameter to get the real URL
$url = url_decrypt($_SERVER['REQUEST_URI']);

$proxy = new Proxy();

// load plugins
foreach(Config::get('plugins', array()) as $plugin){

	$plugin_class = $plugin.'Plugin';
	
	if(file_exists('./plugins/'.$plugin_class.'.php')){
	
		// use user plugin from /plugins/
		require_once('./plugins/'.$plugin_class.'.php');
		
	} else if(class_exists('\\Proxy\\Plugin\\'.$plugin_class)){
	
		// does the native plugin from php-proxy package with such name exist?
		$plugin_class = '\\Proxy\\Plugin\\'.$plugin_class;
	}
	
	// otherwise plugin_class better be loaded already through composer.json and match namespace exactly \\Vendor\\Plugin\\SuperPlugin
	$proxy->getEventDispatcher()->addSubscriber(new $plugin_class());
}

try {

	// request sent to index.php
	$request = Request::createFromGlobals();
	
	// remove all GET parameters such as ?q=
	$request->get->clear();
	
	// forward it to some other URL
	$response = $proxy->forward($request, $url);
	
	// if that was a streaming response, then everything was already sent and script will be killed before it even reaches this line
	$response->send();
	
} catch (Exception $ex){

	if(Config::get("error_redirect")){
	
		$url = render_string(Config::get("error_redirect"), array(
			'error_msg' => rawurlencode($ex->getMessage())
		));
		
		// Cannot modify header information - headers already sent
		header("HTTP/1.1 302 Found");
		header("Location: {$url}");
		exit;
		
	} else {

        header("HTTP/1.1 404 Not Found");
        exit;
	}
}

?>