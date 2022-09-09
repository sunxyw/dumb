<?php

declare(strict_types=1);

namespace ZM\Config;

use OneBot\Util\Singleton;
use OneBot\V12\Config\Config;
use ZM\Exception\ConfigException;
use ZM\Framework;

class ZMConfig
{
    use Singleton;

    /**
     * @var array 支持的文件扩展名
     */
    public const ALLOWED_FILE_EXTENSIONS = ['php', 'yaml', 'yml', 'json', 'toml'];

    /**
     * @var array 配置文件加载顺序，后覆盖前
     */
    public const LOAD_ORDER = ['default', 'environment', 'patch'];

    /**
     * @var string 默认配置文件路径
     */
    public const DEFAULT_CONFIG_PATH = SOURCE_ROOT_DIR . '/config';

    /**
     * @var string[] 环境别名
     */
    public static array $environment_alias = [
        'dev' => 'development',
        'test' => 'testing',
        'prod' => 'production',
    ];

    /**
     * @var array 已加载的配置文件
     */
    private array $loaded_files = [];

    /**
     * @var array 配置文件路径
     */
    private array $config_paths;

    /**
     * @var string 当前环境
     */
    private string $environment;

    /**
     * @var Config 内部配置容器
     */
    private Config $holder;

    /**
     * @var null|ConfigTracer 配置跟踪器
     */
    private ?ConfigTracer $tracer;

    /**
     * 构造配置实例
     *
     * @param array  $config_paths 配置文件路径
     * @param string $environment  环境
     *
     * @throws ConfigException 配置文件加载出错
     */
    public function __construct(array $config_paths = [], string $environment = 'uninitiated')
    {
        $this->config_paths = $config_paths ?: [self::DEFAULT_CONFIG_PATH];
        $this->environment = self::$environment_alias[$environment] ?? $environment;
        $this->holder = new Config([]);
        if (Framework::getInstance()->getArgv()['debug'] ?? false) {
            $this->tracer = new ConfigTracer();
        } else {
            $this->tracer = null;
        }
        if ($environment !== 'uninitiated') {
            $this->loadFiles();
        }
    }

    /**
     * 加载配置文件
     *
     * @throws ConfigException
     */
    public function loadFiles(): void
    {
        $stages = [
            'default' => [],
            'environment' => [],
            'patch' => [],
        ];

        // 遍历所有需加载的文件，并按加载类型进行分组
        foreach ($this->config_paths as $config_path) {
            $files = scandir($config_path);
            foreach ($files as $file) {
                [, $ext, $load_type,] = $this->getFileMeta($file);
                // 略过不支持的文件
                if (!in_array($ext, self::ALLOWED_FILE_EXTENSIONS, true)) {
                    continue;
                }

                $file_path = zm_dir($config_path . '/' . $file);
                if (is_dir($file_path)) {
                    // TODO: 支持子目录（待定）
                    continue;
                }

                // 略过不应加载的文件
                if (!$this->shouldLoadFile($file)) {
                    continue;
                }

                // 略过加载顺序未知的文件
                if (!in_array($load_type, self::LOAD_ORDER, true)) {
                    continue;
                }

                // 将文件加入到对应的加载阶段
                $stages[$load_type][] = $file_path;
            }
        }

        // 按照加载顺序加载配置文件
        foreach (self::LOAD_ORDER as $load_type) {
            foreach ($stages[$load_type] as $file_path) {
                $this->loadConfigFromPath($file_path);
            }
        }
    }

    /**
     * 合并传入的配置数组至指定的配置项
     *
     * 请注意内部实现是 array_replace_recursive，而不是 array_merge
     *
     * @param string $key    目标配置项，必须为数组
     * @param array  $config 要合并的配置数组
     */
    public function merge(string $key, array $config): void
    {
        $original = $this->get($key, []);
        $this->set($key, array_replace_recursive($original, $config));
    }

    /**
     * 获取配置项
     *
     * @param string $key     配置项名称，可使用.访问数组
     * @param mixed  $default 默认值
     *
     * @return null|array|mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->holder->get($key, $default);
    }

    /**
     * 设置配置项
     * 仅在本次运行期间生效，不会保存到配置文件中哦
     *
     * 如果传入的是数组，则会将键名作为配置项名称，并将值作为配置项的值
     * 顺带一提，数组支持批量设置
     *
     * @param array|string $key   配置项名称，可使用.访问数组
     * @param mixed        $value 要写入的值，传入 null 会进行删除
     */
    public function set($key, $value = null): void
    {
        $keys = is_array($key) ? $key : [$key => $value];
        foreach ($keys as $i_key => $i_val) {
            $this->holder->set($i_key, $i_val);
        }
    }

    /**
     * 添加配置文件路径
     *
     * @param string $path 路径
     */
    public function addConfigPath(string $path): void
    {
        if (!in_array($path, $this->config_paths, true)) {
            $this->config_paths[] = $path;
        }
    }

    /**
     * 获取当前环境
     *
     * @return string 当前环境
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * 设置当前环境
     *
     * 变更环境后，将会自动调用 `reload` 方法重载配置
     *
     * @param string $environment 目标环境
     */
    public function setEnvironment(string $environment): void
    {
        $target = self::$environment_alias[$environment] ?? $environment;
        if ($this->environment !== $target) {
            $this->environment = $target;
            $this->reload();
        }
    }

    /**
     * 重载配置文件
     * 运行期间新增的配置文件不会被加载哟~
     *
     * @throws ConfigException
     */
    public function reload(): void
    {
        $this->holder = new Config([]);
        $this->loaded_files = [];
        $this->loadFiles();
    }

    /**
     * 获取内部配置容器
     */
    public function getHolder(): Config
    {
        return $this->holder;
    }

    /**
     * 获取配置项的来源
     *
     * @param  string      $key 配置项
     * @return null|string 来源，如果没有找到，返回 null
     */
    public function getTrace(string $key): ?string
    {
        if ($this->tracer === null) {
            logger()->warning('你正在获取配置项的来源，但没有开启配置来源追踪功能');
            return null;
        }

        return $this->tracer->getTraceOf($key);
    }

    /**
     * 获取文件元信息
     *
     * @param string $name 文件名
     *
     * @return array 文件元信息，数组元素按次序为：配置组名/扩展名/加载类型/环境类型
     */
    private function getFileMeta(string $name): array
    {
        $basename = pathinfo($name, PATHINFO_BASENAME);
        $parts = explode('.', $basename);
        $ext = array_pop($parts);
        $load_type = $this->getFileLoadType(implode('.', $parts));
        if ($load_type === 'default') {
            $env = null;
        } else {
            $env = array_pop($parts);
            $env = self::$environment_alias[$env] ?? $env;
        }
        $group = implode('.', $parts);
        return [$group, $ext, $load_type, $env];
    }

    /**
     * 获取文件加载类型
     *
     * @param string $name 文件名，不带扩展名
     *
     * @return string 可能为：default, environment, patch
     */
    private function getFileLoadType(string $name): string
    {
        // 传入此处的 name 参数有三种可能的格式：
        // 1. 纯文件名：如 test，此时加载类型为 default
        // 2. 文件名.环境：如 test.development，此时加载类型为 environment
        // 3. 文件名.patch：如 test.patch，此时加载类型为 patch
        // 至于其他的格式，则为未定义行为
        if (strpos($name, '.') === false) {
            return 'default';
        }
        $name_and_env = explode('.', $name);
        if (count($name_and_env) !== 2) {
            return 'undefined';
        }
        if ($name_and_env[1] === 'patch') {
            return 'patch';
        }
        return 'environment';
    }

    /**
     * 判断是否应该加载配置文件
     *
     * @param string $path 文件名，包含扩展名
     */
    private function shouldLoadFile(string $path): bool
    {
        $name = pathinfo($path, PATHINFO_FILENAME);
        // 对于 `default` 和 `patch`，任何情况下均应加载
        // 对于 `environment`，只有当环境与当前环境相同时才加载
        // 对于其他情况，则不加载
        $type = $this->getFileLoadType($name);
        if ($type === 'default' || $type === 'patch') {
            return true;
        }
        if ($type === 'environment') {
            $name_and_env = explode('.', $name);
            if ($name_and_env[1] === $this->environment) {
                return true;
            }
        }
        return false;
    }

    /**
     * 从传入的路径加载配置文件
     *
     * @param string $path 配置文件路径
     *
     * @throws ConfigException 传入的配置文件不支持
     */
    private function loadConfigFromPath(string $path): void
    {
        if (in_array($path, $this->loaded_files, true)) {
            return;
        }
        $this->loaded_files[] = $path;

        // 判断文件格式是否支持
        [$group, $ext, $load_type, $env] = $this->getFileMeta($path);
        if (!in_array($ext, self::ALLOWED_FILE_EXTENSIONS, true)) {
            throw ConfigException::unsupportedFileType($path);
        }

        // 读取并解析配置
        $content = file_get_contents($path);
        $config = [];
        switch ($ext) {
            case 'php':
                $config = require $path;
                break;
            case 'json':
                try {
                    $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw ConfigException::loadConfigFailed($path, $e->getMessage());
                }
                break;
            case 'yaml':
            case 'yml':
                $yaml_parser_class = 'Symfony\Component\Yaml\Yaml';
                if (!class_exists($yaml_parser_class)) {
                    throw ConfigException::loadConfigFailed($path, 'YAML 解析器未安装');
                }
                try {
                    $config = $yaml_parser_class::parse($content);
                } catch (\RuntimeException $e) {
                    throw ConfigException::loadConfigFailed($path, $e->getMessage());
                }
                break;
            case 'toml':
                $toml_parser_class = 'Yosymfony\Toml\Toml';
                if (!class_exists($toml_parser_class)) {
                    throw ConfigException::loadConfigFailed($path, 'TOML 解析器未安装');
                }
                try {
                    $config = $toml_parser_class::parse($content);
                } catch (\RuntimeException $e) {
                    throw ConfigException::loadConfigFailed($path, $e->getMessage());
                }
                break;
            default:
                throw ConfigException::unsupportedFileType($path);
        }

        // 加入配置
        $this->merge($group, $config);
        logger()->debug("已载入配置文件：{$path}");

        if ($this->tracer !== null) {
            $this->tracer->addTracesOf($group, $config, $path);
        }
    }
}
