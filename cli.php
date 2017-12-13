<?php

error_reporting(E_ALL);
ini_set('display_errors', true);

require_once __DIR__ . '/vendor/autoload.php';

use App\Commands\Ami\PurgeCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

if (file_exists('version.txt')) {
    $version = file_get_contents('version.txt');
} else {
    $version = 'dev';
}

$app = new Application('mamicleaner', $version);

$app->getDefinition()->addOptions([
    new InputOption('profile', 'p', InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED, 'List of AWS profiles.'),
    new InputOption('region', 'R', InputOption::VALUE_REQUIRED, 'AWS default region.'),
]);

$app->add(new PurgeCommand);
$app->run();
