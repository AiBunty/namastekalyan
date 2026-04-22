<?php

declare(strict_types=1);

use NK\Services\WhatsAppCloudService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$limit = isset($argv[1]) ? max(1, min(200, (int) $argv[1])) : 100;
$service = new WhatsAppCloudService();
$result = $service->runScheduler('cli-scheduler', $limit);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
