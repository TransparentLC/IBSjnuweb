# JNU 番禺校区水电费查询 API

大部分人可能都不知道，番禺校区的校园网里实际上有一个[“宿舍能耗查询系统”](http://10.136.2.5/IBSjnuweb/)，可以用来查自己宿舍的水电费数据。如果你之前有看到过某个“暨”字头的<ruby>公众号<rt>营销号</rt></ruby>做了“查询水电费”的功能，其中的数据实际上就来自那个系统。

你可以直接去系统里手动查询，不过或许有人会需要水电费数据用于一些别的用途也说不定……？比如邮件提醒什么的，总之这里有个 API 可以用啦！(　-\`ω-)✧

> 在这里输入宿舍号（和充水电费时输入的一致，例如 T1 的 114 对应 `t10114`，T11 的 514 对应 `t110514`），看一看自己宿舍的水电费数据～
>
> <input id="room" type="text"><button id="query">查询</button>

# 注意事项

* 这个接口可以直接查询到**任何宿舍**的水电费数据，但是**水电费数据中并不会有任何涉及个人隐私的内容**。
* 仅限个人使用，请勿用于公众号等商业用途。

# API 文档

* [水电费余额和读数](#水电费余额和读数)
* [充值记录](#充值记录)
* [耗能记录](#耗能记录)

> 通用的说明：
> * URL 中的 `{}` 部分表示参数，`[]` 部分是可选的。
> * 获取的数据仅供参考，可能与实际存在延迟或误差。
> * 一些 API 可以使用 URL 参数 `format` 指定返回数据的类型，参见下表：
>
> | 返回数据的类型 | 描述 |
> | - | - |
> | `json` | JSON 格式，默认值 |
> | `markdown` | 格式化后的 Markdown 文本 |
> | `text` | `markdown` 的别名 |
> | `html` | 添加了 HTML 标签的格式，并不是完整的网页 |
> | `chart` | 通过公共 API “[QuickChart](https://quickchart.io/)” 生成的图表 （使用了 302 重定向） |
> | `graph` | `chart` 的别名 |

## 水电费余额和读数

`GET /IBSjnuweb/api/billing/{room}[?format={format}]`

| 参数 | 类型 | 描述 |
| - | - | - |
| `room` | `String` | 宿舍号，不区分大小写，例如 `t10114` |
| `format` | `String` | 返回数据的类型，可选择 `json`、`markdown`、`html` |

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

## 充值记录

`GET /IBSjnuweb/api/payment-record/{room}[?page={page}&count={count}&format={format}]`

| 参数 | 类型 | 描述 |
| - | - | - |
| `room` | `String` | 宿舍号，不区分大小写，例如 `t10114` |
| `page` | `Number` | 页数，默认为 1 |
| `count` | `Number` | 每页的记录数，默认为 10，不能超过 100 |
| `format` | `String` | 返回数据的类型，可选择 `json`、`markdown`、`html` |

```json
{
  "code": 200,
  "msg": "T114514 查询成功",
  "result": {
    // 记录总数
    "total": 23,
    // 以当前设置的记录数计算的分页总数
    "pageCount": 3,
    "records": [
      // 详细的充值记录
      // time   时间戳
      // event  充值说明
      // amount 充值金额
      { "time": 1606758115, "event": "补贴发放 (热水)", "amount": 200 },
      { "time": 1606758115, "event": "补贴发放 (冷水)", "amount": 25.2 },
      { "time": 1606758115, "event": "补贴发放 (电)", "amount": 20.0288 }
    ]
  }
}
```

## 耗能记录

`GET /IBSjnuweb/api/metrical-data/{room}/{date}[?format={format}]`

| 参数 | 类型 | 描述 |
| - | - | - |
| `room` | `String` | 宿舍号，不区分大小写，例如 `t10114` |
| `date` | `String` | 查询日期，可以使用 `2020` 或 `2020-09` 这两种格式，分别查询一整年 / 月的数据 |
| `format` | `String` | 返回数据的类型，可选择 `json`、`markdown`、`html`、`chart` |

```json
{
  "code": 200,
  "msg": "T114514 查询成功",
  "result": {
    // time  时间戳，一般是一年中每个月的第一天（按年份查询）/一个月中每一天（按月份查询）的零点
    // usage 使用量
    "electricity": [
      { "time": 1606752000, "usage": 3.24 },
      { "time": 1606838400, "usage": 3.63 }
    ],
    "coldWater": [
      { "time": 1606752000, "usage": 0 },
      { "time": 1606838400, "usage": 0 }
    ],
    "hotWater": [
      { "time": 1606752000, "usage": 0.2 },
      { "time": 1606838400, "usage": 0.1 }
    ]
  }
}
```

<p style="text-align:center">
    <small>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</small>
    <br>
    <small>Commit: <abbr id="version">...</abbr></small>
    <br>
    <small><a href="https://github.com/TransparentLC/IBSjnuweb" target="_blank">Source code on GitHub</a></small>
</p>

<script>(()=>{const e=e=>document.getElementById(e),t=e=>`${e}`.padStart(2,0),n=e("room");e("query").onclick=()=>n.value&&open(`api/billing/${n.value}?format=markdown`),fetch("api/version").then((e=>e.json())).then((n=>{const r=n.result,o=new Date(1e3*r.commitTime),v=e("version");v.innerText=r.commitShort;v.title=`${r.commit} (${o.getFullYear()}-${t(o.getMonth()+1)}-${t(o.getDate())} ${t(o.getHours())}:${t(o.getMinutes())}:${t(o.getSeconds())})`}))})()</script>