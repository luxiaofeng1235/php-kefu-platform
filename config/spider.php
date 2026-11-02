<?php
/**
 * 蜘蛛采集相关配置
 */
return [
    'bing' => [
        // 额外 header，会覆盖默认 header
        'headers' => [
            // 默认模拟桌面 Chrome，可按需覆盖
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => 'zh-CN,zh;q=0.9',
            'Cache-Control' => 'max-age=0',
            // 将所有 Cookie 合并成一个原始字符串，以确保与浏览器行为完全一致
            'Cookie' => 'MUID=1B2C666D247D679812AC70EA25756672; MUIDB=1B2C666D247D679812AC70EA25756672; SRCHD=AF=NOFORM; SRCHUID=V=2&GUID=F51861A3314043DFA036942EC9EECD63&dmnchg=1; _UR=QS=0&TQS=0&Pn=0; BFBUSR=BFBHP=0; _EDGE_S=SID=1F2CF6D7B33862E40336E045B2726397; _Rwho=u=d&ts=2025-10-27; _HPVN=CS=eyJQbiI6eyJDbiI6NCwiU3QiOjAsIlFzIjowLCJQcm9kIjoiUCJ9LCJTYyI6eyJDbiI6NCwiU3QiOjAsIlFzIjowLCJQcm9kIjoiSCJ9LCJReiI6eyJDbiI6NCwiU3QiOjAsIlFzIjowLCJQcm9kIjoiVCJ9LCJBcCI6dHJ1ZSwiTXV0ZSI6dHJ1ZSwiTGFkIjoiMjAyNS0xMC0yN1QwMDowMDowMFoiLCJJb3RkIjowLCJHd2IiOjAsIlRucyI6MCwiRGZ0IjpudWxsLCJNdnMiOjAsIkZsdCI6MCwiSW1wIjoxOCwiVG9ibiI6MH0=; SNRHOP=I=&TS=; USRLOC=HS=1&ELOC=LAT=31.2957820892334|LON=120.56779479980469|N=%E8%99%8E%E4%B8%98%E5%8C%BA%EF%BC%8C%E6%B1%9F%E8%8B%8F%E7%9C%81|ELT=4|; ipv6=hit=1761544258653&t=4; SRCHUSR=DOB=20251017&DS=1; _RwBf=r=0&ilt=32&ihpd=2&ispd=14&rc=45&rb=0&rg=200&pc=42&mtu=0&rbb=0&clo=0&v=16&l=2025-10-26T07:00:00.0000000Z&lft=0001-01-01T00:00:00.0000000&aof=0&ard=0001-01-01T00:00:00.0000000&rwdbt=0&rwflt=0&rwaul2=0&g=&o=2&p=&c=&t=0&s=0001-01-01T00:00:00.0000000+00:00&ts=2025-10-27T04:51:00.4871226+00:00&rwred=0&wls=&wlb=&wle=&ccp=&cpt=&lka=0&lkt=0&aad=0&TH=&cid=0&gb=; _SS=SID=1F2CF6D7B33862E40336E045B2726397&R=45&RB=0&GB=0&RG=200&RP=42; SRCHHPGUSR=SRCHLANG=zh-Hans&PREFCOL=0&BRW=S&BRH=M&CW=1163&CH=911&SCW=1164&SCH=5308&DPR=1.0&UTC=480&PV=15.0.0&PRVCW=1920&PRVCH=911&HV=1761540664&HVE=CfDJ8BJecyNyfxpMtsfDoM3OqQtRkE8nZDX83fybEhqLy6dLVn7O2h4gt4DTfXsrsYApMQwthQF9PvGOR0o_OTR3YKMrm-f5oR_qJRhAWfCdEVQQYGHZOt4EAS058ituwIvKXNcqm0IxIJ-ynUhR989409_-xqaZ2jVDAOyvNTWBrmCMoqRxKjdfXc78_WsMRrSxjA&BZA=0&B=0&EXLTT=23&AV=14&ADV=14&RB=0&MB=0',
        ],
        // 'cookies' 数组被合并到上面的 'Cookie' 头中，此处留空
        'cookies' => [],
    ],
];
