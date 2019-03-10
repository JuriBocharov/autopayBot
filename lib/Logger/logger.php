<?php

namespace NPF\Logger;

use SplObjectStorage;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Class Logger.
 */
class Logger extends AbstractLogger implements LoggerInterface
{
    /**
     * @var SplObjectStorage Список роутов
     */
    public $routes;

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->routes = new SplObjectStorage();
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, array $context = [])
    {
        foreach ($this->routes as $route) {
            if (!$route instanceof Route) {
                continue;
            }
            if (!$route->isEnable) {
                continue;
            }
            $route->log($level, $message, $context);
        }
    }

    /**
     * Возвращает имя файла, в который будут щаписаны данные экспорта.
     *
     * @param string $fileName
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getPathToFile()
    {
        $dir = dirname(dirname(__DIR__)) . '/log';
        if (!is_dir($dir) && !mkdir($dir)) {
            throw new Exception("Can't create dir {$dir}");
        }

        $fileName = 'autoPayBot_' . (new DateTime())->format('d.m.Y') . '.log';
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.log$/', $fileName)) {
            throw new Exception("Wrong file name {$fileName}");
        }

        return $dir . '/' . $fileName;
    }
}
