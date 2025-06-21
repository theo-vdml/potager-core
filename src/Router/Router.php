<?php

namespace Potager\Router;

use Potager\App;
use Potager\Container\Container;
use Potager\Exceptions\HttpException;
use Potager\View;
use Exception;

/**
 * Class Router
 *
 * Handles route registration and request dispatching.
 * Supports GET, POST, PUT, PATCH, and DELETE methods.
 * Invokes appropriate controllers and applies middleware pipeline.
 */
class Router
{

	/**
	 * Array to hold all registered routes.
	 *
	 * @var Route[]
	 */
	protected array $routes = [];

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Router constructor.
	 *
	 * @param Container $container Dependency injection container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Register a GET route.
	 *
	 * @param string $path
	 * @param array $controllerAction
	 * @return Route
	 */
	public function get(string $path, array $controllerAction): Route
	{
		return $this->register('GET', $path, $controllerAction);
	}

	/**
	 * Register a POST route.
	 *
	 * @param string $path
	 * @param array $controllerAction
	 * @return Route
	 */
	public function post(string $path, array $controllerAction): Route
	{
		return $this->register('POST', $path, $controllerAction);
	}

	/**
	 * Register a PUT route.
	 *
	 * @param string $path
	 * @param array $controllerAction
	 * @return Route
	 */
	public function put(string $path, array $controllerAction): Route
	{
		return $this->register('PUT', $path, $controllerAction);
	}

	/**
	 * Register a PATCH route.
	 *
	 * @param string $path
	 * @param array $controllerAction
	 * @return Route
	 */
	public function patch(string $path, array $controllerAction): Route
	{
		return $this->register('PATCH', $path, $controllerAction);
	}

	/**
	 * Register a DELETE route.
	 *
	 * @param string $path
	 * @param array $controllerAction
	 * @return Route
	 */
	public function delete(string $path, array $controllerAction): Route
	{
		return $this->register('DELETE', $path, $controllerAction);
	}

	/**
	 * Helper to register a route.
	 *
	 * @param string $method HTTP method
	 * @param string $path URL path
	 * @param array $controllerAction Controller class and method
	 * @return Route
	 */
	protected function register($method, $path, $controllerAction): Route
	{
		$route = new Route($method, $path, $controllerAction);
		$this->routes[] = $route;
		return $route;
	}

	/**
	 * Find a registered route by its name.
	 *
	 * @param string $name
	 * @return Route
	 * @throws Exception If route not found
	 */
	public function findByName($name): Route
	{
		foreach ($this->routes as $route) {
			if ($route->getName() === $name) {
				return $route;
			}
		}
		throw new Exception("Route named {$name} does not exist");
	}

	/**
	 * Handle the current HTTP request by matching and dispatching to the appropriate route.
	 *
	 * @return never
	 * @throws HttpException If no matching route is found
	 */
	public function handleRequest(): never
	{
		$context = $this->container->make(HttpContext::class);
		$request = $context->request();

		$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // TODO : Get from request
		$method = $_SERVER['REQUEST_METHOD'];
		foreach ($this->routes as $route) {
			if ($route->match($method, $uri)) {
				$request->attachRoute($route);
				$this->invokeController($route, $context);
			}
		}
		throw new HttpException(404);
	}

	/**
	 * Invoke the controller associated with a matched route.
	 * Applies middleware and executes controller method.
	 *
	 * @param Route $route
	 * @param HttpContext $context
	 * @return never
	 */
	protected function invokeController(Route $route, HttpContext $context): never
	{
		$middlewares = $route->getMiddlewares();

		$output = null;

		$controller = function () use ($route, $context, &$output): void {
			[$controller, $action] = $route->getAction();
			$output = (new $controller())->$action($context);
		};

		$pipeline = array_reverse($middlewares);
		$next = $controller;
		foreach ($pipeline as $middleware) {
			$prev = $next;
			$next = fn(): mixed => $middleware($context, $prev);
		}

		$next();
		$response = $this->resolveControllerOutput($output, $context->response());
		$this->sendResponse($response);
		exit;
	}

	/**
	 * Resolve the controller's return value into a Response object.
	 *
	 * @param mixed $controllerResult Controller return value
	 * @param Response $response Default response object
	 * @return Response
	 */
	protected function resolveControllerOutput(mixed $controllerResult, Response $response): Response
	{
		// If the controller returned a instance of Reponse, it should prior to the default $reponse object
		if ($controllerResult instanceof Response) {
			$response = $controllerResult;
		}
		// If the controller returned a instance of a View, it should prior to any other values
		elseif ($controllerResult instanceof View) {
			$controllerResult->with("auth", App::useAuth());
			$response->send($controllerResult->render());
		}
		// If the controller returned a raw value, it should be used as the body of the response
		elseif (isset($controllerResult)) {
			$response->send($controllerResult);
		}
		return $response;
	}

	/**
	 * Send the HTTP response to the client.
	 * Applies headers, status code, and body.
	 *
	 * @param Response $response
	 * @return void
	 */
	protected function sendResponse(Response $response): void
	{
		// Apply the redirection if any was set
		if ($response->getRedirect() instanceof Redirect) {
			$path = $response->getRedirect()->getPath();
			header("Location: $path", true, 302);
			exit; // Stop the script after redirection
		}

		// Set the headers according to the response object
		$headers = $response->getHeaders();
		foreach ($headers as $name => $value) {
			header("$name: $value");
		}

		// Set the status code
		http_response_code($response->getStatus());

		// return the response body
		echo $response->getBody();
	}
}
