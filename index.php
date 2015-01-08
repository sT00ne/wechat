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
            $RX_TYPE = trim($postObj->MsgType);
            switch($RX_TYPE)
            {
            	case "text":
                    $resultStr = $this->handleText($postObj);
                    break;
                case "event":
                    $resultStr = $this->handleEvent($postObj);
                    break;
                default:
                    $resultStr = "Unknow msg type: ".$RX_TYPE;
                    break;
        	}
            echo $resultStr;
        }
    }

    public function handleText($postObj)
    {
    	//处理回复
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
        if(!empty($keyword)){
        	if(strtolower(trim($keyword)) == "help" || trim($keyword) == "帮助"){
                //帮助信息
        		$contentStr = "*绑定+您的姓名:以绑定账号"."\n"."*改名+新姓名:以修改绑定姓名"."\n"."*解绑:以解除绑定账号"."\n"."*也可以随意输入文字哟！";
        		$resultStr = $this->responseText($postObj, $contentStr);
    			return $resultStr;
        	}
        	//将$keyword根据加号分割成数组，判断命令
            $keywords = explode("+",$keyword);

        	if(trim($keywords[0]) == '绑定')
        	{
                //绑定账号
        		$msgType = "text";
        		$mysql = new SaeMysql();
                //获取当前时间
                $nowtime=date("Y-m-d");
                if(trim($keywords[1])==null)
                {
                    $contentStr ="绑定个名称吧！";
                }
                else{
                    $sql = "SELECT username,usable FROM user WHERE username='".$fromUsername."'";
                    $ret = $mysql->getData($sql);
                    if ($ret == true) {
                        if ($ret[0]['usable'] == 1) {
                            $contentStr ="绑定过了~~~";
                        }else{
                            $sql = "UPDATE user SET usable = 1,alias = '".trim($keywords[1])."',time = '".$nowtime."'WHERE username = '".$fromUsername."'";
                            $mysql->runSql($sql);
                            $contentStr ="绑定成功！";
                        }
                        
                    } else {
                        $sql = "INSERT INTO user(username,alias,time,usable) VALUES('".$fromUsername."','".$keywords[1]."','".$nowtime."','1')";
                        $mysql->runSql($sql);
                        $contentStr ="绑定成功！";
                    }
                }
				$resultStr = $this->responseText($postObj, $contentStr);
    			return $resultStr;
        	}
            else if(trim($keyword) == '解绑'){
                //解除绑定
                $mysql = new SaeMysql();
                $sql = "SELECT username,usable FROM user WHERE username='".$fromUsername."'";
                $ret = $mysql->getData($sql);
                if($ret == true){
                    if($ret[0]['usable'] == 1){
                        $sql = "UPDATE user SET usable = 0 WHERE username = '".$fromUsername."'";
                        $mysql->runSql($sql);
                        $contentStr ="解绑成功！";
                    }else{
                        $contentStr = "还没绑定过~~~";
                    }
                }
                else {
                    $contentStr = "还没绑定过~~~";
                }
                $resultStr = $this->responseText($postObj, $contentStr);
                return $resultStr;
            }
            else if(trim($keywords[0]) == '改名')
            {
                //改名，修改alieas字段
                $msgType = "text";
                $mysql = new SaeMysql();
                //获取当前时间
                $nowtime=date("Y-m-d G:i:s");
                if(trim($keywords[1])==null)
                {
                    $contentStr ="要改的名字呢？";
                }
                else{
                    $sql = "SELECT username,usable FROM user WHERE username='".$fromUsername."'";
                    $ret = $mysql->getData($sql);
                    if ($ret == true) {
                            $sql = "UPDATE user SET alias = '".trim($keywords[1])."' WHERE username = '".$fromUsername."'";
                            $mysql->runSql($sql);
                            $contentStr ="改名成功！";
                    } else {
                        $contentStr ="还没绑定过~~~";
                    }
                }
                $resultStr = $this->responseText($postObj, $contentStr);
                return $resultStr;
            }
            else if(trim($keyword) == '我'){
                //解除绑定
                $mysql = new SaeMysql();
                $sql = "SELECT username,usable FROM user WHERE username='".$fromUsername."'";
                $ret = $mysql->getData($sql);
                if($ret == true){
                    if($ret[0]['usable'] == 1){
                        $sql = "select alias,time from user WHERE username = '".$fromUsername."'";
                        $ret = $mysql->getData($sql);
                        $contentStr = "姓名:".$ret[0]['alias']."\n"."绑定时间:".$ret[0]['time'];
                    }else{
                        $contentStr = "还没绑定过~~~";
                    }
                }
                else {
                    $contentStr = "还没绑定过~~~";
                }
                $resultStr = $this->responseText($postObj, $contentStr);
                return $resultStr;
            }
        	else
        	{
        		$msgType = "text";
                $contentStr = $this->tuling($keyword);
                $resultStr = $this->responseText($postObj, $contentStr);
                //$resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                return $resultStr;
        	}
        }
        else{
            echo "Input Something...";
        }  
    }

     public function handleEvent($postObj)
    {
    	//触发事件
    	$contentStr = "";
        switch ($postObj->Event)
        {
            case "subscribe":
                $contentStr = "欢迎关注，还没什么功能，如需帮助请回复help^_^ ";
                break;
            case "unsubscribe":
            	$contentStr = "取消关注！";
            	 //添加数据库信息修改
                break;
            default :
                $contentStr = "Unknow Event: ".$postObj->Event;
                break;
        }
        $resultStr = $this->responseText($postObj, $contentStr);
        return $resultStr;
    }

    public function responseText($postObj, $content, $flag=0)
    {
    	//文字回复
        $textTpl = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[text]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
                    <FuncFlag>%d</FuncFlag>
                    </xml>";
        $resultStr = sprintf($textTpl, $postObj->FromUserName, $postObj->ToUserName, time(), $content, $flag);
        return $resultStr;
    }

    public function tuling($keyword){
    	//图灵机器人
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

