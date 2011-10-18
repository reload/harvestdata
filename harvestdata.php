<?php

use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

//Setup Symfony classloader and components
require_once __DIR__.'/vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php';
$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
  'Symfony' => __DIR__.'/vendor',
  'HarvestData' => __DIR__.'/lib',
));
$loader->register();

//Load HaPi
require_once 'vendor/hapi/HarvestAPI.php';
spl_autoload_register( array('HarvestAPI', 'autoload') );

//Load Geckoboard class
require_once 'vendor/Geckoboard/GeckoResponse.php';
spl_autoload_register( array('GeckoResponse', 'autoload') );

//Load Geckoboard chart class
require_once 'vendor/Geckoboard/GeckoChart.php';
spl_autoload_register( array('GeckoChart', 'autoload') );

$app = new HarvestData\HarvestData();
$app->run();