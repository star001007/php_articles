<?php
/**
 * Created by PhpStorm.
 * User: yy
 * Date: 15-9-23
 * Time: 下午8:50
 */
ini_set('max_execution_time', '0');

header('Content-Type:text/html;charset=utf-8');
$obj = new Grab('2015-08-19', '2015-08-19');
$obj->run();


class Common{
    const LEVER_WARNING = 1;
    const LEVER_ERROR = 2;

    /***
     * 若结果$result为empty，则抛出异常
     * @param $result
     * @param $msg
     * @return mixed
     * @throws Exception
     */
    protected function eT($result, $msg){
        if(empty($result)){
            $this->throwException($msg);
        }
        return $result;
    }

    /***
     * 错误
     * @param $msg
     * @param $level
     */
    protected function error($msg = '', $level = self::LEVER_ERROR){
        switch($level){
            case self::LEVER_WARNING:
                $this->MSG($msg);
                break;
            case self::LEVER_ERROR:
                $this->throwException($msg);
                break;
        }
    }

    /***
     * 错误处理
     * @param Exception $e
     * @return array
     */
    protected function errorDeal(Exception $e){
        $this->MSG($e->getMessage());
    }

    /***
     * 抛出异常
     * @param $msg
     * @throws Exception
     */
    private function throwException($msg){
        throw new Exception($msg);
    }

    /****
     * 打印信息
     * @param $msg
     */
    protected function MSG($msg){
        echo date('Y-m-d H:i:s')."\n message:{$msg}\n";
    }
}


class Grab extends Common{
    private $start_date;
    private $end_date;

    public function __construct($start_date, $end_date = ''){
        $this->start_date = $start_date;
        $this->end_date = empty($end_date) ? $start_date : $end_date;
    }

    public function run(){
        $date = $this->start_date;

        $h = new HtmlAnalyze();
        while($date <= $this->end_date){
            try{
                $h->run($date);
            }catch (Exception $e){
                $this->errorDeal($e);
            }
            $date = date('Y-m-d', strtotime($date) + 24*3600);
        }
    }
}

class HtmlAnalyze extends Common{

    private $year;  //年
    private $month; //月
    private $date;  //日
    private $source_date;   //年-月-日

    private $type_list; //栏目列表：版块

    const HOST = 'http://epaper.xiancn.com/xarb';
    const TYPE_URL = 'http://epaper.xiancn.com/xarb/html/%s-%s/%s/node_%s.htm';
    const ARTICLE_URL = 'http://epaper.xiancn.com/xarb/html/%s-%s/%s/content_%s.htm';

    const TYPE_START_INDEX = 23;

    const TYPE_PAGE_VALID_START_INDEX = '陕新网审字[2002]008号　陕ICP备06000875号';

    const TYPE_ID_MARK_START_INDEX = '<!-------bmdh版面导航------>';
    const TYPE_ID_MARK_END_INDEX = '<!-------bmdh版面导航END------>';

    const ARTICLE_ID_MARK_START_INDEX = '<!-- -------------------------标题导航-------------->';
    const ARTICLE_ID_MARK_END_INDEX = '<!-- -------------------------标题导航 END -------------->';

    public function init($source_date){
        $this->source_date = $source_date;
        list($year, $month, $date) = explode('-', $source_date);
        $this->year = $year;
        $this->month = $month;
        $this->date = $date;

        $this->type_list = array();
    }

    public function run($source_date){
        $this->init($source_date);

        $this->grabTypeList();
        if(!$this->type_list){
            return;
        }

        foreach($this->type_list as $type_index=>$type_name){
            $this->grabArticleList($type_index);
        }
    }

    private function getIndexUrl(){
        return sprintf(self::TYPE_URL, $this->year, $this->month, $this->date, self::TYPE_START_INDEX);
    }

    private function getTypeUrl($type_index){
        return sprintf(self::TYPE_URL, $this->year, $this->month, $this->date, $type_index);
    }

    private function getArticleUrl($article_id){
        return sprintf(self::ARTICLE_URL, $this->year, $this->month, $this->date, $article_id);
    }

    /***
     * 抓取栏目
     */
    private function grabTypeList(){
        $index_url = $this->getIndexUrl();
        $content = $this->getRemoteUrlContent($index_url);
        if(!$content){
            return;
        }

        list(, $content) = explode(self::TYPE_ID_MARK_START_INDEX, $content);
        list($content, ) = explode(self::TYPE_ID_MARK_END_INDEX, $content);

        preg_match_all('#<a href=\.?/?node_(.*?).htm class="black" id=pageLink>(.*?)</a>#is', $content, $type_list);

        if(!$type_list || 0 === count($type_list[1]) || 0 === count($type_list[2])){
            $this->error($this->source_date.':获取版块栏目失败！');
        }
        foreach($type_list[1] as $key=>$type_id){
            $this->type_list[$type_id] = isset($type_list[2][$key]) ? $type_list[2][$key] : '';
        }
    }

    /****
     * 抓取文章列表
     * @param $type_index
     */
    private function grabArticleList($type_index){
        $article_ids = $this->grabArticleIds($type_index);
        if(!$article_ids){
            return;
        }

        $articles = $this->grabArticles($article_ids);
        if(!$articles){
            return;
        }

        foreach($articles as $id=>$item){
            $articles[$id]['sub_title'] = $item['title'] == $item['sub_title'] ? '' : $item['sub_title'];
            $articles[$id]['type_name'] = isset($this->type_list[$type_index]) ? $this->type_list[$type_index] : '';
            $articles[$id]['create_date'] = $this->source_date;
            $articles[$id]['rel_article_id'] = $id;
        }
        $this->insertArticles($articles);
    }

    /****
     * 抓取文章链接
     * @param $type_index
     * @return bool
     */
    private function grabArticleIds($type_index){
        $type_url = $this->getTypeUrl($type_index);
        $content = $this->getRemoteUrlContent($type_url, self::LEVER_WARNING);
        if(!$content){
            return false;
        }

        list(, $content) = explode('<MAP NAME="pagepicmap">', $content);
        list($content,) = explode('</MAP><img src', $content);

        preg_match_all('#href="content_(.*?)\.htm"#',$content, $article_ids);

        if(!$article_ids || !$article_ids[1]){
            $this->error('Date:'.$this->source_date.', type:'.$this->type_list[$type_index].', grab article urls failed!');
        }

        return $article_ids[1];
    }

    /*****
     * 抓取某篇文章
     * @param $article_ids
     * @return array
     */
    private function grabArticles($article_ids){
        $data = array();
        foreach($article_ids as $article_id){
            $url = $this->getArticleUrl($article_id);
            $content = $this->getRemoteUrlContent($url, self::LEVER_WARNING);
            list(, $content) = explode('<!----------文章部分开始---------->', $content);
            list($content, ) = explode('<INPUT type=checkbox value=0 name=titlecheckbox sourceid="" style="display:none">', $content);

            $title = $this->getArticleTitle($content);
            $sub_title = $this->getArticleSubTitle($content);
            $content = $this->getArticleContent($content);

            $data[$article_id] = array('title'=>$title, 'sub_title'=>$sub_title, 'content'=>$content);
        }
        return $data;
    }

    /****
     * 获取文章标题
     * @param $content
     * @return string
     */
    private function getArticleTitle($content){
        list(, $content) = explode('<td class="bt1" align=center>', $content);
        list($content, ) = explode('</td> </TR> <tr valign=top> <td class="bt2"', $content);
        return trim(strip_tags($content));
    }

    /*****
     * 获取文章子标题
     * @param $content
     * @return string
     */
    private function getArticleSubTitle($content){
        if(false === strpos($content, 'class="bt2')){
            return '';
        }
        list(, $content) = explode('<td class="bt2" align=center style="color: #827E7B;">', $content);
        list($content, ) = explode('<td background="../../../tplimg/detial_line.jpg" height="1"></td>', $content);
        return trim(strip_tags($content));
    }

    /*****
     * 获取文章正文
     * @param $content
     * @return mixed|string
     */
    private function getArticleContent($content){
        /*  $start_index = strpos($content, '<founder-content>');
          if(false !== $start_index){
              $content = substr($content, $start_index);
              list($content, ) = explode('</founder-content>', $content);
              return $content;
          }*/
        list(, $content) = explode('<tr><td align="center" height="50"></td>', $content);
        list($content, ) = explode('<td height="150" align="center"><iframe', $content);
        $content = preg_replace('#(<img src=".*?" width="300" border="0" />)#is', '', $content);
        $content = trim(strip_tags(preg_replace("@<script(.*?)</script>@is", '', $content), '<p><img>'));
//        $content = preg_replace('@width(=".*?")@is', '', $content);
//        $content = preg_replace('@border(=".*?")@is', '', $content);
        $content = str_replace('&nbsp;', '', $content);
        $content = str_replace(array('<P>','</P>'), array('<p>', '</p>'), $content);
        $content = str_replace('src="../../../res', 'src="'.self::HOST.'/res', $content);
        $content = preg_replace('#\s#is', '', $content);
        $content = str_replace(array('<imgsrc=', '<p></p>'), array('<img src=', ''), $content);
        return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    }

    /*****
     * 插入数据库
     * @param $articles
     */
    private function insertArticles($articles){
        $db = new DB();
        $affected_rows = $db->insertMultiRows($articles, DB::XARB_ARTICLE_TALBE);
        $this->MSG('insert rows: '.$affected_rows);
    }

    /****
     * 抓取远程http内容
     * @param $url
     * @param $level
     * @return string
     */
    private function getRemoteUrlContent($url, $level = self::LEVER_ERROR){
        $data = file_get_contents($url);
        if(false === $data){
            $this->error('GET url content failed:'.$url, $level);
        }
        return $data;
    }
}

class DB extends Common{
    const DB_MARK_ARTICLE = '30qingnian';
    const XARB_ARTICLE_TALBE = 'xarb_article';

    public function getDBConn($db_mark, $read = true){
        switch($db_mark){
            case self::DB_MARK_ARTICLE:
                return $this->getDBConnObj(self::DB_MARK_ARTICLE, $read);
                break;
        }
    }

    /****
     * 获取数据库连接
     * @param $db_mark
     * @param $read
     * @return mixed
     */
    private function getDBConnObj($db_mark, $read){
        $conn = mysql_connect('localhost', 'root', 'root');
        mysql_select_db($db_mark, $conn);
        return $conn;
    }

    public function insertMultiRows($data, $table_name){
        $keys = implode(",", array_keys(current($data)));
        $values = array();
        foreach($data as $item){
            $values[] = "('".implode("','", $item)."')";
        }
        $values = implode(',', $values);
        $sql = "insert into {$table_name}({$keys}) values {$values}";
        return $this->insert($sql);
    }

    public function insert($sql){
        $db = $this->getDBConn(self::DB_MARK_ARTICLE);
        $rs = mysql_query($sql, $db);
        if(false === $rs){
            $error_no = mysql_errno($db);
            $error_str = mysql_error($db);
            $this->error("mysql error no :{$error_no}, error msg: {$error_str}, sql:{$sql}");
        }

        $sleep_time = mt_rand(100, 2000);
        usleep($sleep_time);
        return mysql_affected_rows($db);
    }
}

