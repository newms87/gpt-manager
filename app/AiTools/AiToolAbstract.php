<?php

namespace App\AiTools;

use Exception;
use Newms87\Danx\Helpers\FileHelper;

abstract class AiToolAbstract implements AiToolContract
{
    public static string   $name             = '';
    protected static array $parsedParameters = [];

    public static function description(): string
    {
        return static::parseConfig()['description'];
    }

    public static function parameters(): array
    {
        return static::parseConfig()['parameters'];
    }

    public static function parseConfig(): array
    {
        if (!static::$name) {
            throw new Exception(static::class . "::\$name must be defined.");
        }

        if (empty(static::$parsedParameters[static::$name])) {
            $dir = preg_replace("#\\\\#", '/', pathinfo(static::class, PATHINFO_FILENAME));
            $dir = app_path(str_replace("App/", '', dirname($dir)) . '/config.yaml');

            static::$parsedParameters[static::$name] = FileHelper::parseYamlFile($dir);
        }

        return static::$parsedParameters[static::$name];
    }
}
