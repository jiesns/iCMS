<?php

/**
 * iCMS - i Content Management System
 * Copyright (c) 2007-2017 iCMSdev.com. All rights reserved.
 *
 * @author icmsdev <master@icmsdev.com>
 * @site https://www.icmsdev.com
 * @licence https://www.icmsdev.com/LICENSE.html
 */
defined('iPHP') OR exit('What are you doing?');

class spider_post {
    public static $callback = array();
    public static function get($id) {
        $spost = iDB::row("SELECT * FROM `#iCMS@__spider_post` WHERE `id`='$id' LIMIT 1;");
        if ($spost->post) {
            $postArray = explode("\n", $spost->post);
            $postArray = array_filter($postArray);
            foreach ($postArray AS $key => $pstr) {
                list($pkey, $pval) = explode("=", $pstr);
                if(strpos($pkey, '[')!==false && strpos($pkey, ']')!==false){
                    preg_match('/(.+)\[(.+)\]/', $pkey,$match);
                    $_POST[$match[1]][$match[2]] = trim($pval);
                }else{
                    $_POST[$pkey] = trim($pval);
                }
            }
        }
        return $spost;
    }
	public static function option($id = 0, $output = null) {
		$rs = iDB::all("SELECT * FROM `#iCMS@__spider_post`");
		foreach ((array) $rs AS $post) {
			$pArray[$post['id']] = $post['name'];
			$opt .= "<option value='{$post['id']}'" . ($id == $post['id'] ? " selected='selected'" : '') . ">{$post['name']}:{$post['app']}[id='{$post['id']}'] </option>";
		}
		if ($output == 'array') {
			return $pArray;
		}
		return $opt;
	}
    public static function commit($code,$suid=0,$spo=null) {
        is_numeric($spo) && $spo = self::get($spo);
        iSecurity::slashes($_POST);
        $fun = $spo->fun;
        if(iFS::checkHttp($fun)){
            $json = self::postUrl($fun,$_POST);
            $result = json_decode ($json,true);
            if($result['code']==$code){
                $indexid = $result['indexid'];
                self::update_spider_url_indexid($suid,$indexid);
                self::update_spider_url_publish($suid);
            }
        }else{
            if(strpos($fun, '::')===false){
                $obj = $spo->app."Admincp";
            }else{
                list($obj,$fun) = explode('::', $fun);
            }

            $acp = new $obj;
            $acp->callback['code'] = $code;
            /**
             * 主表 回调 更新关联ID
             */
            $acp->callback['primary'] = array(
                array('spider','update_spider_url_indexid'),
                array('suid'=>$suid)
            );
            /**
             * 数据表 回调 成功发布
             */
            $acp->callback['data'] = array(
                array('spider','update_spider_url_publish'),
                array('suid'=>$suid)
            );

            $result = $acp->$fun();
            if($result) return $result;

            spider_error::log("发布失败",$_POST['reurl'],'spider_post::commit.fail');
            return false;
        }
    }
    public static function postUrl($url, $data) {
        if(!iHttp::is_url($url,true)){
            if (spider::$dataTest || spider::$ruleTest) {
                echo "<b>{$url} 请求错误:非正常URL格式,因安全问题只允许提交到 http:// 或 https:// 开头的链接</b>";
            }
            return false;
        }
        is_array($data) && $data = http_build_query($data);
        $options = array(
            CURLOPT_URL                  => $url,
            CURLOPT_REFERER              => $_SERVER['HTTP_REFERER'],
            CURLOPT_USERAGENT            => $_SERVER['HTTP_USER_AGENT'],
            CURLOPT_POSTFIELDS           => $data,
            // CURLOPT_HTTPHEADER           => array(
            //     'Content-Type:application/x-www-form-urlencoded',
            //     'Content-Length:'.strlen($data),
            //     'Host: www.icmsdev.com'
            // ),
            CURLOPT_POST                 => 1,
            CURLOPT_TIMEOUT              => 10,
            CURLOPT_CONNECTTIMEOUT       => 10,
            CURLOPT_RETURNTRANSFER       => 1,
            CURLOPT_FAILONERROR          => 1,
            CURLOPT_HEADER               => false,
            CURLOPT_NOBODY               => false,
            CURLOPT_NOSIGNAL             => true,
            // CURLOPT_DNS_USE_GLOBAL_CACHE => true,
            // CURLOPT_DNS_CACHE_TIMEOUT    => 86400,
            CURLOPT_SSL_VERIFYPEER       => false,
            CURLOPT_SSL_VERIFYHOST       => false
        );

        $ch = curl_init();
        curl_setopt_array($ch,$options);
        $responses = curl_exec($ch);
        curl_close ($ch);
        return $responses;
    }
}