# 存储管理（文件）

DataProvider 是框架内提供的一个简易的文件管理类。

定义：`\ZM\Utils\DataProvider`

## DataProvider::getWorkingDir()

同 `working_dir()`。

## DataProvider::getSourceRootDir()

获取用户的源码根目录，除 Phar 模式外与 `getWorkingDir()` 相同。

## DataProvider::getFrameworkLink()

同 `ZMConfig::get("global", "http_reverse_link")`，获取反向代理的链接。

## DataProvider::getDataFolder()

获取配置项 `zm_data` 指定的目录。

如果指定参数 `$second`，则返回二级目录地址，如果二级目录不存在则自动创建。

```php
DataProvider::getDataFolder("TestModule"); // 例如返回 /root/zhamao-framework/zm_data/TestModule/
```

## DataProvider::getFrameworkRootDir()

返回框架本体的根目录。

## DataProvider::saveToJson()

将变量内容保存为 json 格式的文件，存储在 `zm_data/config/` 目录下或子目录下。

定义：`saveToJson($filename, $file_array)`

`$filename` 是文件名，不需要加后缀，比如你想保存成 `foo/bar.json`，这里写 `foo/bar` 就好。如果不想要二级目录，就直接写 `bar`，不需要加 `.json` 后缀。

这里只支持二级目录，不支持更多级的子目录。

`$file_array` 为内容，一般是数组，比如你缓存了一个 API 接口返回的数据，然后直接解析成数组后丢给它就好了。

## DataProvider::loadFromJson()

从 json 文件加载内容至变量。

定义：`loadFromJson($filename)`

文件名同上 `saveToJson()` 的定义，解析后的返回值为原先的内容或 `null`（如果文件不存在或 json 解析失败）。

## DataProvider::scanDirFiles()

递归或非递归扫描目录，返回相对目录的文件列表或绝对目录的文件列表。（非常好用）

定义：`scanDirFiles($dir, $recursive = true, $relative = false)`

`$dir` 为要扫描的目录，`$recursive` 为是否递归，`$relative` 为是否返回相对目录的文件列表。

从给定的目录下开始遍历整个目录，如果将 `$recursive` 设置为 `true`，则会递归扫描子目录，否则将返回包含目录的文件列表。

如果将 `$relative` 设置为 `true`，则会返回文件列表的相对路径，否则返回绝对路径。

例如：假设目录 `/home/test/` 下有两个文件：`test1.txt` 和 `testdir/test2.txt`：如果将 `$recursive` 设置为 `true`，`$relative` 设置为 `false`，则返回的文件列表为：

```json
[
    "/home/test/test1.txt",
    "home/test/testdir/test2.txt"
]
```

相同条件下，如果将 `$relative` 设置为 `true`：

```json
[
    "test1.txt",
    "testdir/test2.txt"
]
```

如果再把 `$recursive` 设置为 `false`：

```json
[
    "test1.txt",
    "testdir"
]
```

## 其他文件读取

框架比较贴近原生的 PHP，所以推荐直接使用原生的方法来读写文件（`file_get_contents` 和 `file_put_contents`）。但有一点要注意，框架内最好使用**工作目录或者绝对路径**。

```php
// 读取框架工作目录的文件 composer.json 文件
$r = file_get_contents(working_dir() . "/composer.json");

// 写入 Linux 临时目录下的文件
file_put_contents("/tmp/test.txt", "hello world");
```

::: warning 注意

在默认的情况里，框架的根目录均为可写可读的，在读写文件时务必要注意目录的位置和权限。使用 `working_dir()` 获取目录后面需要加 `/` 再追加自己的文件名或子目录名。

:::
