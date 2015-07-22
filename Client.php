<?php
/**
 * 
 * @link http://changyan.sohu.com/
 * @author ylx
 * 畅言HTTP请求类
 */

class ChangYan_Client {
	
	var $userAgent;
	var $http;
	
	public function __construct () {
		global $wp_version;
		$this->http = new WP_Http();
		$this->userAgent = 'WordPress/' . $wp_version . '|ChangYan/'. ChangYan_Handler::version;
	}

    /**
     *
     * @param $url
     * @param $params
     * @thorws Exception
     * @return array
     */
	
    public function httpRequest($url, $method, $params) {
        require_once 'Exception.php';
		$args = array(
			'method' => $method,
			'timeout' => 600,
			'redirection' => 5,
			'httpversion' => '1.0',
			'user-agent' => $this->userAgent,
			'headers' => array('Expect'=>''),
			'sslverify' => false,
		);
		switch($method){
			case 'GET':
				$url .= '?' . http_build_query($params, null, '&');	
				break;
			case 'POST':
				$args['body'] = $params;
                break;
			default:
		}
		$response = $this->http->request($url, $args);
			
		if (isset($response->errors)) {
			if (isset($response->errors['http_request_failed'])){
				$message = $response->errors['http_request_failed'][0];
				if ($message == 'name lookup timed out')
					$message = 'DNS解析超时，请重试或检查你的主机的域名解析(DNS)设置.';
				elseif (stripos($message, 'Could not open handle for fopen') === 0)
					$message = '无法打开fopen句柄，请重试或联系畅言技术工程师。http://changyan.sohu.com/';
				elseif (stripos($message, 'Couldn\'t resolve host') === 0)
					$message = '无法解析changyan.sohu.com域名，请重试或检查你的主机的域名解析(DNS)设置.';
				elseif (stripos($message, 'Operation timed out after ') === 0)
					$message = '操作超时, 请重试或联系畅言技术工程师。http://changyan.sohu.com/';
				throw new Http_Exception($message, Http_Exception::REQUEST_TIMED_OUT);
			}
            else
            	throw new Http_Exception('连接服务器失败, 详细信息：' . json_encode($response->errors), Http_Exception::REQUEST_TIMED_OUT);
		}

		$json = json_decode($response['body'], true);
		return $json === null ? $response['body'] : $json;
	}
}
