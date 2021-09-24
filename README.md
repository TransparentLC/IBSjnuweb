<div align="center">

# JNU 番禺校区水电费查询 API

![](https://github.com/TransparentLC/IBSjnuweb/workflows/build-phar/badge.svg)

</div>

<div align="center">

![](https://ae01.alicdn.com/kf/H3668b8a13a0a4faf9bf81af63651779bJ.gif)

</div>

水电费查询 API，番禺校区校园网内[“宿舍能耗查询系统”](https://pynhcx.jnu.edu.cn/ibsjnuweb)的二次封装，支持以 JSON / 纯文本（Markdown）/ HTML 格式输出数据。

具体使用方式请参见 `public/index.md`。

本项目[以 GNU AGPL 3.0 协议开源](https://github.com/TransparentLC/IBSjnuweb/blob/master/LICENSE)，如果你的项目（包括但不限于用于商业用途、公众号、小程序等）依赖本 API 的代码并对他人提供服务，则整个项目也必须以该协议开源。

# 部署

**部署的设备必须可以正常连接番禺校区的校园网，否则无法使用 API。**

1. 配置好 PHP（建议使用 7.4 或更新版本）和 Nginx 环境。
2. 从[这里](https://nightly.link/TransparentLC/IBSjnuweb/workflows/build-phar/master/IBSjnuweb)下载打包好的 PHAR 文件和其他静态文件到网站目录，这里假设解压后保存在 `IBSjnuweb-source` 文件夹。
3. 在网站目录下创建配置文件 `config.json`，可以基于 `config.example.json` 的内容进行修改。`config.schema.json` 是配置文件的 JSON Schema。
4. 假设需要将 API 部署在 `https://example.com/IBSjnuweb/`，添加以下 Nginx 配置：

```nginx
location = /IBSjnuweb-source {
    return 403;
}
location = /IBSjnuweb {
    return 301 /IBSjnuweb/;
}
location ~ \/IBSjnuweb\/(.*)$ {
    try_files
        /IBSjnuweb-source/public/$1
        /IBSjnuweb-source/public/$1/
        /IBSjnuweb-source/index.php?/$1?$query_string;
}
```

你可以通过编辑 `public/index.html` 来修改主页上的说明。

如果你有云服务器资源，可以在云服务器和部署的设备上配置 [frp](https://github.com/fatedier/frp) 等内网穿透工具，这样在校园网以外也可以查询水电费了。

# 使用 PHP 内置的 Web 服务器运行（开发用）

下载源代码后，在终端中执行 `php -S 0.0.0.0:5000 main.php`，API 就会运行在本机的 5000 端口（也可以修改为其它端口）。

Windows 用户可以直接双击 `run-dev-server.bat`。

# 请求次数统计

自带了一个简单的请求次数统计功能，会以小时为单位记录最近一周各功能的请求次数，并以图表的形式展示。此功能在正确配置 Redis 后自动开启。

访问 `/api/statistics` 即可获取统计信息：

```json
{
  "code": 200,
  "msg": "",
  "result": {
    "chart": "https://quickchart.io/chart/render/********",
    "statistics": [
      {
        "time": 1623402000,
        "billing": 3,
        "payment": 1,
        "metrical": 2
      },
      {
        "time": 1623405600,
        "billing": 1,
        "payment": 3,
        "metrical": 0
      },
      ...
    ]
  }
}
```

# 请求次数限制

自带了一个简单的请求次数限制功能，可以以 IP 为单位对一段时间内的请求次数进行限制，超过限制后将返回 HTTP 状态码 429 Too Many Requests。可以在配置文件中配置所有/某个 IP 的限制时间窗口及请求次数上限。

开启此功能后，查询时会增加以下响应头：

* `X-RateLimit-Limit` 目前配置的请求次数限制。
* `X-RateLimit-Window` 目前配置的时间窗口。从第一次请求开始计时，经过这一时间后才会重设请求次数。
* `X-RateLimit-Remaining` 剩余的请求次数。
* `X-RateLimit-Reset` 下次重设请求次数的时间戳。
