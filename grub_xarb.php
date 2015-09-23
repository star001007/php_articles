ini_set('max_execution_time', '0');

header('Content-Type:text/html;charset=utf-8');
$obj = new Grab('2015-09-01');
$obj->run();

class Grab{
    private $start_date;
    private $end_date;

    const HOST = 'http://epaper.xiancn.com/xarb';
    const TYPE_URL = 'http://epaper.xiancn.com/xarb/html/%s-%s/%s/node_%s.htm';
    const ARTICLE_URL = 'http://epaper.xiancn.com/xarb/html/%s-%s/%s/%s';

    const TYPE_START_INDEX = 23;

    public function __construct($start_date, $end_date = ''){
        $this->start_date = $start_date;
        $this->end_date = empty($end_date) ? $start_date : $end_date;
    }

    public function run(){
        $date = $this->start_date;
        while($date <= $this->end_date){
            $this->grabTypeList($date);
            $date = date('Y-m-d', strtotime($date) + 24*3600);
        }
    }

    /***
     * 抓取栏目
     * @param $date
     */
    public function grabTypeList($date){
        list($year, $month, $date) = explode('-', $date);

        $type_index = self::TYPE_START_INDEX;
        while(true){
            $type_url = sprintf(self::TYPE_URL, $year, $month, $date, $type_index);
            $content = $this->getRemoteUrl($type_url);
            if(!$content){
                break;
            }
            list(, $content) = explode('<MAP NAME="pagepicmap">', $content);
            list($content,) = explode('</MAP><img src', $content);

            preg_match_all('#href="(.*?)"#',$content, $url_list);

            if(!$url_list || !$url_list[1]){
                MSG('Date:'.$date.', type:'.$type_name.', grab article urls failed!');
                return;
            }
            $this->grabArticle($year, $month, $date, $url_list[1]);

            $type_index++;
        }
    }

    private  function grabArticle($year, $month, $date, $url_list){
        print_r($url_list);
        foreach($url_list as $item){
            $url = sprintf(self::ARTICLE_URL, $year, $month, $date, $item);
            $content = $this->getRemoteUrl($url);
            list(, $content) = explode('<!----------文章部分开始---------->', $content);
            list($content, ) = explode('<INPUT type=checkbox value=0 name=titlecheckbox sourceid="" style="display:none">', $content);

            $title = $this->getArticleTitle($content);
            $sub_title = $this->getArticleSubTitle($content);
            $content = $this->getArticleContent($content);
            var_dump($title, $sub_title, $content);
        }
    }

    private function getArticleTitle($content){
        list(, $content) = explode('<td class="bt1" align=center>', $content);
        list($content, ) = explode('</td> </TR> <tr valign=top> <td class="bt2"', $content);
        return trim(strip_tags($content));
    }

    private function getArticleSubTitle($content){
        if(false === strpos($content, 'class="bt2')){
            return '';
        }
        list(, $content) = explode('<td class="bt2" align=center style="color: #827E7B;">', $content);
        list($content, ) = explode('<td background="../../../tplimg/detial_line.jpg" height="1"></td>', $content);
        return trim(strip_tags($content));
    }

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
        $content = str_replace(array('<P>','</P>'), '<p>', $content);
        $content = str_replace('src="../../../res', 'src="'.self::HOST.'/res', $content);
        $content = preg_replace('#\s#is', '', $content);
        $content = str_replace('<imgsrc=', '<img src=', $content);
        return $content;
    }

    private function getRemoteUrl($url){
        $data = file_get_contents($url);
        if(false === $data){
            MSG('GET url content failed:'.$url);
        }
        return $data;
    }
}

function MSG($msg){
    echo date('Y-m-d H:i:s')."\n message:{$msg}\n";
}
