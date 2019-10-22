# ChunkWorldEditor

高速ワールドエディタ！    
従来の29倍(当社比)の速度を実現することに成功致しました！    
また、非同期、マルチスレッドに対応しており、さらに高速、快適に変更することが可能にてございます！    

## Usage
金ブロック(Id: 41)を手に持ち、設置、破壊を行うことにより、座標の指定を行います。    
以下のコマンドを実行することによって、ブロックの設置等を行うことが可能にてございます。

## Commands

|Command|Description|undoable|Async|MultiThread|
|:---:|:---:|:---:|:---:|:---:|
|/////set|同期チャンクワールドエディタ<br><br>Usage:`/////set [BlockId:BlockDamage]`|❌|❌|❌|
|/////setpp|非同期チャンクワールドエディタ(1スレッド)<br><br>Usage:`/////setpp [BlockId:BlockDamage]`️️|❌|✔️|❌|
|/////setppp|非同期チャンクワールドエディタ(マルチスレッド)<br><br>Usage:`/////setppp [BlockId:BlockDamage] [ThreadCount]`|❌|✔️|✔️|
|/////uset|同期チャンクワールドエディタ<br><br>Usage:`/////set [BlockId:BlockDamage]`|✔️|❌|❌|
|/////usetpp|非同期チャンクワールドエディタ(1スレッド)<br><br>Usage:`/////setpp [BlockId:BlockDamage]`️️|✔️|✔️|❌|
|/////setppp|非同期チャンクワールドエディタ(マルチスレッド)<br><br>Usage:`/////setppp [BlockId:BlockDamage] [ThreadCount]`|✔️|✔️|✔️|
|/////undo|前回行った操作を取り消します。<br>`undoable`なコマンドのみundoすることが可能にてございます<br>前回の操作はマルチスレッドの場合、このコマンドはマルチスレッドにて動作いたします。<br>usage:`/////undo`|N/A|✔️|🔺|
|/////e|座標をリセット致します。|N/A|N/A|N/A|

## Warning
`/////setpp`または、`/////setppp`使用時にサーバーに負荷がかかる可能性があるため、    
他人の所持しているサーバー、VPS等にてこのコマンドを使用して、サーバー等に不利益が生じた場合、    
プラグイン作成者は責任を負いません。        
申し訳ないです...

## License

Plugin Name|Author|License|
|:---|:---|:---|
|MineReset|Falkirks|MIT|
|WEdit|Gonbe34|None|

### MineReset

```
The MIT License (MIT)

Copyright (c) 2017 Falkirks

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
