# JNU 番禺校区水电费查询 API

大部分人可能都不知道，番禺校区的校园网里实际上有一个[“宿舍能耗查询系统”](http://10.136.2.5/IBSjnuweb/)，可以用来查自己宿舍的水电费数据。如果你之前有看到过某个“暨”字头的<ruby>公众号<rt>营销号</rt></ruby>做了“查询水电费”的功能，其中的数据实际上就来自那个系统。

你可以直接去系统里手动查询，不过或许有人会需要水电费数据用于一些别的用途也说不定……？比如邮件提醒什么的，总之这里有个 API 可以用啦！(　-\`ω-)✧

因为做了内网穿透，所以这个接口即使是**在校园网以外**也能访问 (〃′▽`)

> 在这里输入宿舍号（和充水电费时输入的一致，例如 T1 的 114 对应 `t10114`，T11 的 514 对应 `t110514`），看一看自己宿舍的水电费数据～
>
> <input id="room" type="text"><button id="query">查询</button>

这个 API 是使用 PHP 编写的，运行在一个性能极差的<ruby>白色低级路由器<rt>土豆服务器</rt></ruby>上。因为它有时会出现一些奇怪的问题，它的可用性可能还不到一个⑨……

**所以如果这个接口用不了，一定是因为路由器坏了，这个时候就耐心等原作者来修吧…φ(･ω･` )**

# 使用方法

`GET https://i.akarin.dev/IBSjnuweb/api/billing/{room}`
`GET https://i.akarin.dev/IBSjnuweb/api/billing/{room}?text`
`GET https://i.akarin.dev/IBSjnuweb/api/billing/{room}?html`

接口默认返回 JSON 格式的数据，你也可以添加 URL 参数 `text` 或 `html`，分别可以获取纯文本（实际上是 Markdown）或 HTML 格式（仅添加了标签，并不是完整的网页）的查询结果。

`room` 是宿舍号，数据仅供参考。

返回的 JSON 数据的示例与说明：

```json
{
  "code": 200,
  "msg": "T114514 查询成功",
  "result": {
    // 水电费余额
    "balance": 19.19,
    // 水电费补贴
    // total     每个月发放的水电费补贴，单位为度（电能）和吨（冷热水）
    // available 当前剩余的补贴，用完了（值为零）则开始扣费
    "allowance": {
      "electricity": { "total": 32, "available": 0 },
      "coldWater": { "total": 8, "available": 3 },
      "hotWater": { "total": 8, "available": 6.5 }
    },
    // 水电费读数
    // price   单价，单位为度（电能）和吨（冷热水）
    // start   上次查表时的水电表读数
    // current 当前的水电表读数
    // usage   增加的读数（当前值减去上次查表的值）
    "bill": {
      "electricity": { "price": 0.6259, "start": 5521.1899, "current": 5670.3599, "usage": 149.17 },
      "coldWater": { "price": 3.15, "start": 311.7, "current": 316.7, "usage": 5 },
      "hotWater": { "price": 25, "start": 41.2, "current": 42.7, "usage": 1.5 }
    }
  }
}
```

# 注意事项
* 这个接口可以直接查询到**任何宿舍**的水电费数据，但是**水电费数据中并不会有任何涉及个人隐私的内容**。
* 仅限个人使用，请勿用于公众号等商业用途。

<p style="text-align:right"><small>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄<br>这个人……正在等待一个可爱的她。</small></p>

<script>(()=>{const n=n=>document.getElementById(n),o=n("room");n("query").onclick=()=>o.value&&open(`api/billing/${o.value}?text`)})()</script>