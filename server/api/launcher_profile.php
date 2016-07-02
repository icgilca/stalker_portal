<?php

include "./common.php";

if (empty($_GET['uid'])){

    exit;
}

use Stalker\Lib\Core\Config;
use Stalker\Lib\Core\Stb;

$config = array(
    'options' => array(),
    'themes'  => array(),
    'apps'    => array()
);

$language = isset($_GET['language']) ? $_GET['language'] : 'en';

$allowed_languages = Config::get('allowed_locales');
$allowed_languages_map = array();

foreach ($allowed_languages as $loc){
    $allowed_languages_map[substr($loc, 0, 2)] = $loc;
}

if (isset($allowed_languages_map[$language])){
    $locale = $allowed_languages_map[$language];
}elseif (count($allowed_languages_map) > 0){
    reset($allowed_languages_map);
    $locale = $allowed_languages_map[key($allowed_languages_map)];
}else{
    $locale = 'en_GB.utf8';
}

setlocale(LC_MESSAGES, $locale);
putenv('LC_MESSAGES='.$locale);

$app_manager = new SmartLauncherAppsManager($language);
$installed_apps = $app_manager->getInstalledApps();

$installed_apps_names = array_map(function($app){
    return 'launcher_'.$app['alias'];
}, $installed_apps);

$all_modules = array_merge(Config::get('all_modules'), $installed_apps_names);
$disabled_modules = Stb::getDisabledModulesByUid((int) $_GET['uid']);

$user = Stb::getById((int) $_GET['uid']);

// if user is off - return empty menu
if ($user['status'] == 1){

    echo json_encode($config);
    exit;
}

$config['options']['pluginsPath'] = '../plugins/';

$config['options']['stalkerHost'] = 'http'.(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 's' : '')
    .'://'.$_SERVER['HTTP_HOST'];

$config['options']['stalkerApiHost'] = 'http'.(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 's' : '')
    .'://'.$_SERVER['HTTP_HOST']
    .Config::getSafe('portal_url', '/stalker_portal/')
    .'api/v3/';

$config['options']['stalkerAuthHost'] = 'http'.(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 's' : '')
    .'://'.$_SERVER['HTTP_HOST']
    .Config::getSafe('portal_url', '/stalker_portal/')
    .'auth/token.php';

$config['options']['sap'] = 'http'.(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 's' : '')
    .'://'.$_SERVER['HTTP_HOST']
    .Config::getSafe('portal_url', '/stalker_portal/')
    .'server/api/sap.php';

$config['options']['pingTimeout'] = Config::getSafe('watchdog_timeout', 120) * 1000;
$available_modules = array_values(array_diff($all_modules, $disabled_modules));

$themes = $app_manager->getInstalledApps('theme');

if (!empty($themes)){

    foreach ($themes as $theme){
        $config['themes'][$theme['alias']] = '../'.$theme['alias'].'/'.$theme['current_version'].'/';
    }
}

$user_apps = array();

$system_apps = $app_manager->getSystemApps();

$installed_apps = array_merge($system_apps, $installed_apps);

foreach ($installed_apps as $app) {

    if (!in_array('launcher_'.$app['alias'], $available_modules) && $app['type'] == 'app'){
        continue;
    }

    if ($app['config']){
        $app_config = json_decode($app['config'], true);
        if ($app_config){
            $app['config'] = $app_config;
        }
    }

    if ($app['config']){

        $app['config']['version'] = $app['current_version'];

        $app['config']['url'] = 'http'.(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 's' : '')
            .'://'.$_SERVER['HTTP_HOST']
            .'/'.Config::getSafe('launcher_apps_path', 'stalker_launcher_apps/')
            .$app['alias']
            .'/'.$app['current_version'].'/app/';

        $app['config']['dependencies'] = $app_manager->getFullAppDependencies($app['id']);

        $user_apps[] = $app['config'];
    }
}

$config['apps'] = $user_apps;
header('Content-Type: application/json');
echo json_encode($config, 192);