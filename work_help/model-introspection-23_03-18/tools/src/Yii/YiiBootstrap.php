<?php

declare(strict_types=1);

namespace Tools\Yii;

use Tools\Config;

final class YiiBootstrap
{
    /** @return array{ok:bool,warnings:list<string>} */
    public function bootstrap(Config $config): array
    {
        $warnings = [];
        $bootstrap = $config->get('yii_bootstrap', './protected/yii.php');
        $bootstrapPath = is_string($bootstrap) ? $config->resolvePath($bootstrap) : '';

        if ($bootstrapPath !== '' && is_file($bootstrapPath)) {
            require_once $bootstrapPath;
            return ['ok' => true, 'warnings' => $warnings];
        }

        $warnings[] = 'Yii bootstrap not found, running in static-parse mode';
        return ['ok' => true, 'warnings' => $warnings];
    }
}
