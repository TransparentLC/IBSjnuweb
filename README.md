<div align="center">

# JNU 番禺校区水电费查询 API

</div>

<div align="center">

![](https://ae01.alicdn.com/kf/H3668b8a13a0a4faf9bf81af63651779bJ.gif)

</div>

水电费查询 API，番禺校区校园网内[“宿舍能耗查询系统”](http://10.136.2.5/IBSjnuweb/)的二次封装，支持以 JSON / 纯文本（Markdown）/ HTML 格式输出数据。

# 部署方式

**部署的设备必须可以正常连接番禺校区的校园网，否则无法使用 API！**

1. 配置好 PHP 和 Nginx 环境
2. 下载源代码到网站目录，这里假设源代码保存在 `IBSjnuweb-source` 文件夹
3. 在终端中执行 `composer install` 安装依赖
4. 假设需要将 API 部署在 `http://example.com/IBSjnuweb/`，添加以下 Nginx 配置：

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
        /IBSjnuweb-source/main.php?/$1?$query_string;
}
```

你可以通过编辑 `public/index.html` 来修改主页上的说明。

如果你有云服务器资源，你可以在云服务器和部署的设备上配置 [frp](https://github.com/fatedier/frp) 等内网穿透工具，这样在校园网以外也可以查询水电费了。
