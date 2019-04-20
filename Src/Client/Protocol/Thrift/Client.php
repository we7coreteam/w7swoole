<?php

namespace W7\Client\Protocol\Thrift;

use Thrift\Protocol\TBinaryProtocol;
use Thrift\Protocol\TMultiplexedProtocol;
use Thrift\Transport\TFramedTransport;
use Thrift\Transport\TSocket;
use W7\Client\Protocol\IClient;
use W7\Client\Protocol\Thrift\Core\DispatcherClient;

class Client implements IClient
{
	private $host;
	private $port;
	private $packFormat;

	public function __construct(array $params) {
		$host = $params['host'];
		$pos = strrpos($host, ':');
		if ($pos !== false) {
			$this->host = substr($host, 0, $pos);
			$this->port = substr($host, $pos + 1);
		} else {
			$this->host = $host;
		}

		$this->packFormat = $params['pack_format'];
	}

	public function call($url, $params = null)
    {
	    $socket = new TSocket($this->host, $this->port);
	    $transport = new TFramedTransport($socket);
	    $protocol = new TBinaryProtocol($transport);
	    $service = new TMultiplexedProtocol($protocol, 'Dispatcher');
	    $transport->open();

	    $body = [
	    	'url' => $url
	    ];
	    if ($params) {
	    	$body['data'] = $params;
	    }
	    $body = $this->pack($body);

	    $client = new DispatcherClient($service);
	    $ret = $client->run($body);
	    $transport->close();

	    return $this->unpack($ret);
    }

	public function pack($body) {
	    switch ($this->packFormat) {
		    case 'serialize':
			    return serialize($body);
			    break;
		    case 'json':
		    default:
			    return json_encode($body);
	    }
    }

	public function unpack($body) {
		switch ($this->packFormat) {
			case 'serialize':
				return unserialize($body);
				break;
			case 'json':
			default:
				return json_decode($body, true);
		}
    }
}