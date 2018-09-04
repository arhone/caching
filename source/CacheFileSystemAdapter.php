<?php declare(strict_types = 1);

namespace arhone\caching;

/**
 * Работа с кэшем
 *
 * Class CacheFileSystemAdapter
 * @package arhone\caching
 * @author Алексей Арх <info@arh.one>
 */
class CacheFileSystemAdapter implements CacheInterface {

    /**
     * Настройки класса
     *
     * @var array
     */
    protected $config = [
        'status'    => true,
        'directory' => __DIR__ . '/cache'
    ];

    /**
     * CacheFile constructor.
     * @param array $config
     */
    public function __construct (array $config = []) {

        $this->config($config);

    }

    /**
     * Проверяет и включает/отключат кеш
     *
     * @param bool $status
     * @return mixed
     */
    public function status (bool $status = null) {

        if ($status !== null) {
            $this->config['status'] = $status == true;
        }

        return $this->config['status'];

    }

    /**
     * Возвращает значение кэша
     *
     * @param string $key
     * @return mixed
     */
    public function get (string $key) {

        if (!$this->status()) {
            return false;
        }

        $path = $this->getPath($key);

        if (is_file($path)) {

            $data = unserialize(file_get_contents($path));

            if (!empty($data['remove']) && $data['remove'] < time()) {

                return false;

            }

            return $data['data'] ?? false;

        }

        return null;

    }

    /**
     * Записывает кэш в файл
     *
     * @param string $key
     * @param $data
     * @param int|null $interval
     * @return bool
     */
    public function set (string $key, $data, int $interval = null) : bool {

        if (!$this->status()) {
            return false;
        }

        $path = $this->getPath($key);
        $dir = dirname($path);

        if (!is_dir($dir)) {

            mkdir($dir, 0700, true);

        }

        $data = [
            'created' => time(),
            'remove'  => $interval ? time() + $interval : null,
            'data'    => $data
        ];
        return file_put_contents($path, serialize($data), LOCK_EX) == true;

    }

    /**
     * Удаление кеша
     *
     * @param string $key
     * @return bool
     */
    public function delete (string $key) : bool {

        return $this->deleteRecursive($this->getPath($key));

    }

    /**
     * Проверка ключа
     *
     * @param string $key
     * @return bool
     */
    public function has (string $key) : bool {

        return !empty($this->getPath($key));

    }

    /**
     * Очистка кеша
     *
     * @return bool
     */
    public function clear () : bool {

        return $this->deleteRecursive($this->config['directory']);

    }

    /**
     * Рекурсивное удаление файлов
     *
     * @param $path
     * @return bool
     */
    function deleteRecursive ($path) : bool {

        if (is_dir($path)) {

            foreach (scandir($path) as $file) {

                if ($file != '.' && $file != '..') {

                    if (is_file($file)) {

                        unlink($file);

                    } else {

                        $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $file);

                    }

                }

            }

            $emptyDir = count(glob($path . '*')) ? true : false;
            if($emptyDir) {
                return rmdir($path);
            }

        } elseif (is_file($path)) {

            return unlink($path);

        }

        return false;

    }

    /**
     * Возврщает путь до файла
     *
     * @param string $key
     * @return string
     */
    protected function getPath (string $key) : string {

        $path = $this->config['directory'] . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $key);

        if (is_dir($path) || is_file($path)) {

            return $path;

        } else {

            $dir  = dirname($path);
            $hash = md5(basename($path));
            return $dir . DIRECTORY_SEPARATOR . '.' . $hash[0] . $hash[1] . DIRECTORY_SEPARATOR . '.' . $hash[2] . $hash[3] . DIRECTORY_SEPARATOR . '.' . $hash;

        }


    }

    /**
     * Задаёт конфигурацию
     *
     * @param array $config
     * @return array
     */
    public function config (array $config) : array {

        return $this->config = array_merge($this->config, $config);

    }

}