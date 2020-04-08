<?php

/**
 * This file is part of Rangine
 *
 * (c) We7Team 2019 <https://www.rangine.com>
 *
 * document http://s.w7.cc/index.php?c=wiki&do=view&id=317&list=2284
 *
 * visited https://www.rangine.com/ for more details
 */

namespace W7\Core\Session\Channel;

use W7\Http\Message\Base\Cookie;
use W7\Http\Message\Server\Response;

class CookieChannel extends ChannelAbstract {
	protected function generateId() {
		return $this->request->cookie($this->getSessionName(), parent::generateId());
	}

	public function replenishResponse(Response $response) : Response {
		//如果用户自定义channel,在这里要通过响应的data中携带sessionid的话,暂不支持

		// websocket 需要使用 header Set-Cookie 下发 Cookie
		$sessionCookie = Cookie::create(
			$this->getSessionName(),
			$this->getSessionId(),
			0,
			null,
			null,
			null,
			null,
			false,
			''
		);
		return $response->withHeader('Set-Cookie', $sessionCookie->__toString())->withCookie($this->getSessionName(), $this->getSessionId());
	}
}
