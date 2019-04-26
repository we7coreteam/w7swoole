<?php
/**
 * 开启服务之前，先构造中间件数据及路由数据
 * @author donknap
 * @date 18-7-25 下午4:51
 */

namespace W7\Tcp\Listener;

use W7\App;
use W7\Core\Listener\ListenerAbstract;
use W7\Core\Middleware\MiddlewareMapping;
use W7\Core\Route\RouteMapping;
use W7\Core\Helper\Storage\Context;
use FastRoute\Dispatcher\GroupCountBased;

class BeforeStartListener extends ListenerAbstract {
	public function run(...$params) {
		$context = App::getApp()->getContext();
		$context->setContextDataByKey(Context::ROUTE_KEY, $this->getRoute());
		$context->setContextDataByKey(Context::MIDDLEWARE_KEY, $this->getMiddleware());

		return true;
	}

	/**
	 * @return GroupCountBased
	 */
	private function getRoute() {
		$routeInfo = iloader()->singleton(RouteMapping::class)->getMapping();
		return new GroupCountBased($routeInfo);
	}

	private function getMiddleware() {
		/**
		 * @var MiddlewareMapping $middlerwareObj
		 */
		$middlerwareObj = iloader()->singleton(MiddlewareMapping::class);
		return $middlerwareObj->getLastMiddle();
	}
}