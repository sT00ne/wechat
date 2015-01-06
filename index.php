<?php
require_once("configure.php");

//define your token
define("TOKEN", "weixin");
$wechatObj = new wechatCallbackapiTest();
if (isset($_GET['echostr'])) {
    $wechatObj->valid();
}else{
    $wechatObj->responseMsg();
}

class wechatCallbackapiTest
{
    public function __construct()
    {
        $mysql = new SaeMysql();
    }

    public function valid()
    {
        $echoStr = $_GET["echostr"];
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }
    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }

    public function responseMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $keyword = trim($postObj->Content);
            $time = time();
            $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";
            if(trim($postObj->MsgType) == "event" and trim($postObj->Event) == "subscribe")//判断是否是新关注
            {
                $msgType = "text";
                $contentStr = "欢迎关注，还没什么功能，随便发。^_^";
                $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                echo $resultStr; 
            }
            else if(trim($postObj->MsgType) == "event" and trim($postObj->Event) == "unsubscribe")//判断是否取消关注
            {
                $msgType = "text";
                $contentStr = "取消关注！";
                $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                echo $resultStr;
            }
            else if(!empty($keyword)){
                $keywords = explode("+",$keyword);
                if(trim($keywords[0] == '绑定'))
                {
                    $msgType = "text";
                    $mysql = new SaeMysql();
                    //将￥keyword根据加号分割成数组
                    
                    //获取当前时间
                    $nowtime=date("Y-m-d G:i:s");
                    if(trim($keywords[1])==null)
                    {
                        $contentStr ="绑定个名称吧！";
                    }
                    else{
                         $sql = "SELECT username FROM user WHERE username='".$fromUsername."'";
                        $ret = $mysql->getData($sql);
                        if ($ret == true) {
                            $contentStr ="绑定过了~~~";
                        } else {
                            $sql = "INSERT INTO user(username,alias,time,usable) VALUES('".$fromUsername."','".$keywords[1]."','".$nowtime."','1')";
                            $mysql->runSql($sql);
                            $contentStr ="绑定成功！";
                        }
                    }
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;
                }
                else
                {
                    $msgType = "text";
                    $contentStr = $this->tuling($keyword);
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;
                }
            }
            else{
                echo "Input Something...";
            }
        }
    }

    public function tuling($keyword){
        $apiKey = "90adc313ccf07b58bda3e7660520506d"; 
        $apiURL = "http://www.tuling123.com/openapi/api?key=".$apiKey."&info=".$keyword;
        $res =file_get_contents($apiURL);
        $result=json_decode($res,true);
        if ($result['code'] == 100000) {
            $re = $result['text'];
        }
        else if ($result['code'] == 200000) {
            $re = "<a href=\"".$result['url']."\">".$result['text']."</a>";
        }
        else{
            $re = "我还不够机智！";
        }
        return $re;
    }
}

?>