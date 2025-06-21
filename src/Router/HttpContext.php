<?php

namespace Potager\Router;

use Potager\Container\Container;

class HttpContext
{
	protected Container $container;
	protected Request $request;
	protected Response $response;

	public function __construct(Container $container)
	{
		$this->container = $container;
		$this->request = $this->container->make(Request::class);
		$this->response = $this->container->make(Response::class);
	}

	/**
	 * Returns the request object. Use this method to access request data like parameters, headers, etc.
	 * @return Request
	 */
	public function request(): Request
	{
		return $this->request;
	}

	/**
	 * Returns the response object. Use this method to define the response.
	 * @return Response
	 */
	public function response(): Response
	{
		return $this->response;
	}
}
