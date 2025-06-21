<?php

namespace Potager\Router;

use Potager\Container\Container;

class Response
{
	protected $status;
	protected $body;
	protected $headers = [];
	protected ?Redirect $redirect = null;
	protected Container $container;

	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	public function status($code)
	{
		$this->status = $code;
		return $this;
	}

	public function safeStauts($code)
	{
		if (!isset($this->status)) {
			$this->status = $code;
		}
		return $this;
	}

	public function header(string $name, string $value)
	{
		$this->headers[$name] = $value;
		return $this;
	}

	public function send(mixed $body)
	{
		$this->body = $body;
		return $this;
	}

	public function ok(mixed $body)
	{
		$this->status = 200;
		$this->body = $body;
		return $this;
	}

	public function redirect(?string $path = null): Redirect
	{
		$redirect = $this->container->make(Redirect::class);
		$this->redirect = $redirect;
		if ($path)
			$this->redirect->toPath($path);
		return $this->redirect;
	}

	/**
	 * @internal This method is intended for internal use only.
	 */
	public function getStatus()
	{
		return $this->status ?? 200;
	}

	/**
	 * @internal This method is intended for internal use only.
	 */
	public function getBody()
	{
		return $this->body;
	}

	/**
	 * @internal This method is intended for internal use only.
	 */
	public function getHeaders()
	{
		return $this->headers;
	}

	/**
	 * @internal This method is intended for internal use only.
	 */
	public function getRedirect(): ?Redirect
	{
		return $this->redirect;
	}
}
