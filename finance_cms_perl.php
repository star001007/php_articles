<?php
/**
 * @description:
 *
 * @encoding: GBK
 * @author 宜星<yixing3@staff.sina.com.cn>
 * @create time: 2015/10/21 9:42
 */


/*****
 * 所有不可替换关键词的文本进行过滤处理
 * @param $text
 * @return array
 */
function finNewsFilterTag($text)
{
    //替换所有注释
    /*
     while($text =~s/(<!--.*?-->)/#####filter$i#####/si){
       $filter[$i++]=$1;
     }*/
    $i = 0;
    $tags = array();
    while (1 === preg_match('/<!--.*?-->/is', $text, $match)) {
        $text = preg_replace('/<!--.*?-->/is', "#####filter{$i}#####", $text, 1, $count);
        $tags[$i++] = $match[0];
    }

    //替换所有表格
    /*while($text =~s/(<table.*?<\/table>)/#####filter$i#####/si){
      $filter[$i++]=$1;
    }*/
    while (1 === preg_match('/<table.*?<\/table>/is', $text, $match)) {
        $text = preg_replace('/<table.*?<\/table>/is', "#####filter{$i}#####", $text, 1, $count);
        $tags[$i++] = $match[0];
    }

    //替换所有链接
    /* while($text =~s/(<a.*?<\/a>)/#####filter$i#####/si){
       $filter[$i++]=$1;
     }*/
    while (1 === preg_match('/<a.*?<\/a>/is', $text, $match)) {
        $text = preg_replace('/<a.*?<\/a>/is', "#####filter{$i}#####", $text, 1, $count);
        $tags[$i++] = $match[0];
    }

    //替换所有图片
    while (1 === preg_match('/<img.*?\/?>/is', $text, $match)) {
        $text = preg_replace('/<img.*?\/?>/is', "#####filter{$i}#####", $text, 1, $count);
        $tags[$i++] = $match[0];
    }

    //替换所有 含class=img_descrd的span
    while (1 === preg_match('/<span\s+class=([\'"])img_descr[\'"]>.*?<\/span>/is', $text, $match)) {
        $text = preg_replace('/<span\s+class=([\'"])img_descr[\'"]>.*?<\/span>/is', "#####filter{$i}#####", $text, 1, $count);
        $tags[$i] = $match[0];
    }

    return array($text, $tags);
}

/****
 * 还原所有过滤文本及标签
 * @param $text
 * @param $tags
 * @return string
 */
function finNewsRestoreTag($text, $tags)
{
    if (!$tags) {
        return $text;
    }

    foreach ($tags as $i => $str) {
        $text = str_replace("#####filter{$i}#####", $str, $text);
    }
    return $text;
}

/*****
 * 获取各股票类型的关键词列表
 * @param $type
 * @return array
 */
function getStockKeyWords($type)
{
    if (in_array($type, array('stock', 'hkstock', 'forex', 'futures', 'overseas_futures'))) {
        $url = "http://finance.sina.com.cn/api/918/2014/keywords/$type.json";
    } elseif ($type == 'us') {
        //美股
        $url = "http://stock.finance.sina.com.cn/usstock/api/openapi.php/US_CompanyInfoService.getKeyWords";
    } else {
        return array(false, '没有想关keywords文件可读取');
    }

    //远程获取文件
    $data = file_get_contents($url);
    if (false === $data) {
        return array(false, '网络故障，请稍后重试');
    }

    $data = json_decode($data, true);
    if (!$data || !$data['result']['data']) {
        return array(false, 'json文件 decode失败，返回文件内容截取部分：' . substr($data, 0, 500));
    }

    dealKeywordsData($data['result']['data']);
    if (!$data['result']['data']) {
        return array(false, '配置文件keywords为空');
    }

    return array(true, $data['result']['data']);
}

/****
 * 遍历处理关键词
 * @param $keywords_list
 */
function dealKeywordsData(&$keywords_list)
{
    if (!$keywords_list || !is_array($keywords_list)) {
        return;
    }
    foreach ($keywords_list as &$item) {
        $item['keywords'] = dealKeywordItem($item['keywords']);
    }
}

/****
 * 处理关键词
 * @param $keyword
 * @return string
 */
function dealKeywordItem($keyword)
{
    //替换文内所有空白字符 $keyextend =~ s/\n+|\r+|\t+//sg;
    $keyword = preg_replace('/\s/', '', $keyword);
    //替换中文逗号为英文逗号
    $keyword = str_replace('，', ',', $keyword);
    //替换多个连续逗号替换为一个
    $keyword = preg_replace('/,+/', ',', $keyword);
    return $keyword;
}

/*****
 * 过滤主函数
 * @param $text
 * @param $type
 * @param $flag
 * @return mixed
 */
function finNewsQuoteKeyRep($text, $type ='', $flag = 0)
{
    # $flag = 0; //设置匹配类别，0表示匹配当前类别关键词，1,2,3 对应匹配多个类别的

    ###base config
    $stock = "stock";
    $hkstock = "hkstock";
    $forex = "forex";
    $future = "futures";
    $overseas = "overseas_futures";

    //根据所属一级栏目频道，定义替换关键词优先级
    switch ($type) {
        case '港股':
            $quotecfg = array($hkstock, $stock, $forex, $future, $overseas);
            break;
        case '期货':
            $quotecfg = array($future, $overseas, $stock, $forex, $hkstock);
            break;
        case '外汇':
            $quotecfg = array($forex, $stock, $future, $overseas, $hkstock);
            break;
        case '贵金属':
            $quotecfg = array($overseas, $future, $forex, $stock, $hkstock);
            break;
        default:
            $quotecfg = array($stock, $forex, $future, $overseas, $hkstock);
            break;
    }

    $match_code_list = array(); # match stock code list
    list($text, $tags) = finNewsFilterTag($text);

    for ($mi = 0; $mi <= 4 && $mi <= $flag; $mi++) {
        $type = $quotecfg[$mi];

        list($status, $keywords_list) = getStockKeyWords($type);
        if (false === $status) {
            return array(false, $keywords_list);
        }

        foreach ($keywords_list as $item) {
            $s_keywords = $item['keywords'];    //关键词
            $s_code = $item['symbol']; //股票代码

            //转义 *,(,)  发布的文章会转义，关键词添加转义来进行匹配
            $oneWord = str_replace(array('*', '(', ')'), array('\*', '\(', '\)'), $s_keywords);
            if(!$oneWord){
                continue;
            }

            //关键词匹配，不在已匹配代码列表。  if ($text =~/$oneWord/ && $codestr!~/$codelist[$i]/ ){
            if (false !== strpos($text, $oneWord) && !in_array($s_code, $match_code_list)) {

                //替换第一个关键词，只替换第一次
                switch ($type) {
                    case 'stock':
                        $text = preg_replace("/$oneWord/", "<span id=stock_{$s_code}><a href=http://finance.sina.com.cn/realstock/company/{$s_code}/nc.shtml class=\"keyword\" target=_blank>{$s_keywords}</a></span><span id=quote_{$s_code}></span>", $text, 1);
                        break;
                    case 'hkstock':
                        //去hk 股票代码前缀
                        $hk_code_ = ltrim(ltrim($s_code), 'hk');
                        $text = preg_replace("/$oneWord/", "<span id=hkstock_{$s_code}><a href=http://stock.finance.sina.com.cn/hkstock/quotes/{$hk_code_}.html class=\"keyword\" target=_blank>{$s_keywords}</a></span><span id=quote_{$s_code}></span>", $text, 1);
                        break;
                    case 'forex':
                        $tmp_codestr = strtolower($s_code);
                        $text = preg_replace("/$oneWord/", "<span id=forex_fx_s{$tmp_codestr}><a href=http://finance.sina.com.cn/money/forex/hq/{$s_code}.shtml class=\"keyword\" target=_blank>{$s_keywords}</a></span><span id=quote_fx_s{$tmp_codestr}></span>", $text, 1);
                        break;
                    case 'futures':
                        $text = preg_replace("/$oneWord/", "<span id=futures_{$s_code}><a href=http://finance.sina.com.cn/money/future/quote.html?code={$s_code} class=\"keyword\" target=_blank>{$s_keywords}</a></span><span id=quote_{$s_code}></span>", $text, 1);
                        break;
                    case 'overseas_futures':
                        $text = preg_replace("/$oneWord/", "<span id=overseas_futures_hf_{$s_code}><a href=http://finance.sina.com.cn/money/future/{$s_code}/quote.shtml class=\"keyword\" target=_blank>{$s_keywords}</a></span><span id=quote_hf_{$s_code}></span>", $text, 1);
                        break;
                }

                //记录
                if ($type == 'overseas_futures') {
                    $match_code_list[] = 'hf_'.$s_code;
                }else{
                    $match_code_list[] = $s_code;
                }
            }
        }
    }

    if ($flag == 0) {
        $codestr = implode('&', $match_code_list);
        $text .= "<!-- news_keyword_pub,{$quotecfg[$flag]},{$codestr} -->";
    }

    $text = finNewsRestoreTag($text, $tags);
    return array(true, $text);
}
