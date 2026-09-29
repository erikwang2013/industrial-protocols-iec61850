# IEC 61850 协议包 — MMS 变电站自动化，端口 102

> [English](README.en.md)

IEC 61850 MMS (Manufacturing Message Specification) 变电站自动化协议，端口 102。支持逻辑节点数据读取。

## 安装

```bash
composer require erikwang2013/industrial-protocols-iec61850
```

## 架构

Iec61850Driver（TCP）→ Iec61850Frame 帧编解码。MMS 协议栈，逻辑节点命名空间寻址。

## 功能

MMS 协议栈、逻辑节点寻址（IED/MMXU/XCBR 等）、电流/电压相量读取、Iec61850Exception 异常

## 使用说明

```php
$conn = $kernel->getConnectionManager()->connect('ied-001');
$conn->read('IED1/MMXU1.MX.A.phsA');   // 电流 A 相
$conn->read('IED1/MMXU1.MX.PhV.phsA');  // 电压 A 相
```

## 配置示例

```php
'devices' => [
    'ied-001' => [
        'protocol' => 'iec61850', 'variant' => 'mms',
        'host' => '10.0.1.100', 'port' => 102,
        'timeout' => 5000,
    ],
],
```

## 兼容框架

Laravel / Webman / Hyperf / ThinkPHP / Yii2 / Yii3 / Plain PHP

## 系统要求

- PHP >= 8.1
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
