<?php
/**
 * @author donknap
 * @date 18-7-24 下午5:31
 */

namespace W7\Http\Server;

use Psr\Http\Message\ServerRequestInterface;
use W7\Core\Dispatcher\DispatcherAbstract;
use W7\Core\Middleware\MiddlewareHandler;
use W7\Core\Helper\Context;
use W7\Core\Log\LogHelper;
use w7\HttpRoute\HttpServer;

class Dispather extends DispatcherAbstract {

	public function dispatch(...$params)
	{
		try {
			$request = $params[0];
			$response = $params[1];
			$serverContext = $params[2];
			$psr7Request = \w7\Http\Message\Server\Request::loadFromSwooleRequest($request);
			$psr7Response = new \w7\Http\Message\Server\Response($response);
			/**
			 * @var Context $contextObj
			 */

			$contextObj = iloader()->singleton(Context::class);
			$contextObj->setRequest($psr7Request);
			$contextObj->setResponse($psr7Response);

			//根据router配置，获取到匹配的controller信息
			//获取到全部中间件数据，最后附加Http组件的特定的last中间件，用于处理调用Controller
			$route = $this->getRoute($psr7Request, $serverContext[Context::ROUTE_KEY]);
			$psr7Request = $psr7Request->withAddedHeader("route", json_encode($route));

			$middlewares = $this->getMiddleware($serverContext[Context::MIDDLEWARE_KEY], $route['controller'], $route['method']);

			$requestLogContextData  = $this->getRequestLogContextData($route['controller'], $route['method']);
			$contextObj->setContextDataByKey(Context::LOG_REQUEST_KEY, $requestLogContextData);

			//ievent('beforeRequest');

			$middlewareHandler = new MiddlewareHandler($middlewares);
			$response = $middlewareHandler->handle($psr7Request);

		} catch (\Throwable $throwable) {
			/**
			 * @var LogHelper $logHandler
			 */
			$logHandler = iloader()->singleton(LogHelper::class);
			$logHandler->exceptionHandler($throwable);
			$response = $contextObj->getResponse()->json($throwable->getMessage(), $throwable->getCode());
		}

		//ievent('afterRequest');
		$response->send();
	}


	private function getRoute(ServerRequestInterface $request, $routeInfo) {
		$httpMethod = $request->getMethod();
		$url = $request->getUri()->getPath();

		$fastRoute = new HttpServer();
		$routeInfo = $fastRoute->dispathByData($httpMethod, $url, $routeInfo);

		list($controller, $method) = explode("-", $routeInfo['handler']);

		return [
			"method" => $method,
			'controller' => $controller,
			'classname' => "W7\\App\\Controller\\" . ucfirst($controller) . "Controller",
			'args' => $routeInfo['funArgs'],
		];
	}

	private function getMiddleware($allMiddleware, $controller, $action) {
		$result = [];
		$controllerMiddlerwares = !empty($allMiddleware[$controller]) ? $allMiddleware[$controller] : [];

		foreach ($controllerMiddlerwares as $method => $middlerware) {
			if (strstr($method, $action) || $method == "default") {
				$result = array_merge($result, $controllerMiddlerwares[$method]);
			}
		}

		//附加最后中间件
		if (!empty($allMiddleware['last'])) {
			$result = array_merge($result, $allMiddleware['last']);
		}

		return $result;
	}

	private function getRequestLogContextData($controller, $method) {
		$contextData = [
			'controller'=>$controller,
			'method'=>$method,
			'requestTime' => microtime(true),
		];
		return $contextData;
	}
}
