## Workerman-Uv

### 简介

[Libuv](http://libuv.org/)是一个事件驱动的异步I/O库，最初是为Node.js开发的。随后也在Python，Julia等语言中被应用。

[php-uv](https://pecl.php.net/package/uv)是对libuv的封装，使其可以在PHP中被应用。

本项目将php-uv的event-loop应用于Workerman，从而可以在基于Workerman的项目中利用php-uv提供的特性。

### 使用说明

1. 使用包管理器安装libuv和libuv-devel（可能需要手动添加源）。

2. 使用pecl安装php-uv（也可以从pecl官网或GitHub仓库）。

3. 将src目录下的Uv.php复制到Workerman\Events下。

4. 在项目中使用Workerman\Events\Uv提供的event-loop。如下：

```php
Worker::$eventLoopClass = '\\Workerman\\Events\\Uv';
```

### 注意

1. 使用Uv的event-loop后，Workerman的子进程处理SIGINT事件时会exit 2（no such file or directory），这个问题待解决。

2. 如果需要使用libuv的多线程特性，需要线程安全（ZTS）的PHP。

3. php-uv目前处于Beta阶段，其稳定性不能保证。请避免将其应用于生产环境。