<?php
namespace Potager\Router;

use Potager\Contracts\MiddlewareInterface;
use Potager\Support\Arr;
use Potager\Support\Str;

class Route
{
	/**
	 * @var string|null Route name
	 */
	protected ?string $name = null;

	/**
	 * @var string HTTP method (GET, POST, etc.)
	 */
	protected string $method;

	/**
	 * @var string Route path pattern (e.g. /users/:id)
	 */
	protected string $path;

	/**
	 * @var mixed Action for the route (controller method, closure, etc.)
	 */
	protected mixed $action;

	/**
	 * @var array Route parameters extracted from the URI
	 */
	protected array $params = [];

	/**
	 * @var callable[] Array of middleware callables
	 */
	protected array $middlewares = [];

	/**
	 * Route constructor.
	 *
	 * @param string $method HTTP method
	 * @param string $path Route path pattern
	 * @param mixed $action Route action handler
	 */
	public function __construct($method, $path, $action)
	{
		$this->method = $method;
		$this->path = $path;
		$this->action = $action;
	}

	/**
	 * Set route name.
	 *
	 * @param string $name
	 * @return $this
	 */
	public function name(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * Set HTTP method.
	 *
	 * @param string $method
	 * @return $this
	 */
	public function method(string $method): static
	{
		$this->method = $method;
		return $this;
	}

	/**
	 * Set route path.
	 *
	 * @param string $path
	 * @return $this
	 */
	public function path(string $path): static
	{
		$this->path = $path;
		return $this;
	}

	/**
	 * Set route action.
	 *
	 * @param mixed $action
	 * @return $this
	 */
	public function action(string $action): static
	{
		$this->action = $action;
		return $this;
	}

	/**
	 * Match the route against given method and URI.
	 *
	 * @param string $method HTTP method to match
	 * @param string $uri URI path to match
	 * @return bool True if route matches, false otherwise
	 */
	public function match(string $method, string $uri): bool
	{

		// Convert route parameters (e.g., /user/:id) into named regex capture groups
		// ':param' becomes '(?P<param>[^/]+)', which captures any value except a slash
		$pattern = preg_replace('#:([\w]+)#', '(?P<\1>[^/]+)', $this->path);

		// Add start (^) and end ($) anchors to ensure the entire URI matches the pattern
		// Use '@' as regex delimiter to avoid escaping slashes
		$pattern = "@^{$pattern}$@";

		// Check if the HTTP method matches and the URI matches the generated pattern
		if ($this->method == $method && preg_match($pattern, $uri, $matches)) {

			// Extract only named parameters from the regex matches (e.g., 'id' => '42')
			// Remove numeric keys (which are the full match and unnamed groups)
			$params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

			// Store extracted parameters in the route object
			$this->params = $params;

			// The route matches the request
			return true;
		}

		// No match: either method or URI didn't match the route definition
		return false;
	}

	/**
	 * Get the HTTP method.
	 *
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * Get the route path pattern.
	 *
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Get the route name.
	 *
	 * @return string|null
	 */
	public function getName(): string|null
	{
		return $this->name ?? null;
	}

	/**
	 * Get the route action.
	 *
	 * @return mixed
	 */
	public function getAction(): mixed
	{
		return $this->action;
	}

	/**
	 * Get route parameters.
	 *
	 * @return array
	 */
	public function getParams(): array
	{
		return $this->params;
	}

	/**
	 * Add middleware(s) to the route.
	 *
	 * @param callable|string|array $middlewares One or multiple middlewares to add.
	 *        Can be a callable, a string alias, or an array of those.
	 * @return $this
	 *
	 * @throws \Exception If middleware is invalid or callable does not accept exactly 2 parameters.
	 */
	public function use(mixed $middlewares): static
	{
		$middlewares = Arr::wrap($middlewares);
		foreach ($middlewares as $mw) {
			$this->middlewares[] = $this->normalizeMiddleware($mw);
		}
		return $this;
	}

	/**
	 * Validates that a callable middleware has the correct signature.
	 *
	 * It must accept exactly two parameters:
	 *  - First: \Potager\HttpContext
	 *  - Second: callable
	 *
	 * @param callable $middleware
	 * @throws \Exception
	 */
	private function assertMiddlewareSignature($middleware): void
	{
		if (!is_callable($middleware)) {
			throw new \Exception("Normalized middleware is not callable.");
		}

		$ref = new \ReflectionFunction(\Closure::fromCallable($middleware));
		$params = $ref->getParameters();

		if (count($params) !== 2) {
			throw new \Exception("Middleware callable must accept exactly 2 parameters: (HttpContext \$ctx, callable \$next).");
		}

		$first = $params[0]->getType();
		$second = $params[1]->getType();

		if (!$first || $first->getName() !== HttpContext::class) {
			throw new \Exception("First middleware parameter must be of type " . HttpContext::class);
		}

		if (!$second || $second->getName() !== 'callable') {
			throw new \Exception("Second middleware parameter must be of type callable.");
		}
	}

	/**
	 * Normalize a middleware definition into a callable middleware.
	 *
	 * This method accepts different forms of middleware:
	 * - A callable (closure, function, or invokable object) — returned as-is.
	 * - A string alias (e.g., 'auth') that will be resolved to a fully qualified middleware class.
	 * - A fully qualified middleware class name implementing MiddlewareInterface,
	 *   which will be lazily instantiated when the middleware is invoked.
	 *
	 * @param mixed $middleware Middleware definition, can be:
	 *                          - callable,
	 *                          - string alias,
	 *                          - fully qualified class name implementing MiddlewareInterface.
	 *
	 * @return callable A callable middleware with the signature (HttpContext $ctx, callable $next): mixed.
	 *
	 * @throws \Exception If the middleware class does not exist, does not implement MiddlewareInterface,
	 *                    or if the provided middleware is invalid.
	 */

	private function normalizeMiddleware(mixed $middleware): callable
	{
		// Closure, callable array or function
		if (is_callable($middleware)) {
			return $middleware;
		}

		// Auto discovery aliases (e.g. auth -> App\Middleware\AuthMiddleware)
		if (is_string($middleware) && !class_exists($middleware)) {
			$middleware = $this->resolveMiddlewareAlias($middleware);
		}

		// Class name with handle
		if (is_string($middleware) && class_exists($middleware)) {
			if (!is_subclass_of($middleware, MiddlewareInterface::class)) {
				throw new \Exception("Middleware class '{$middleware}' must implement MiddlewareInterface.");
			}

			// Lazy loading using a closure
			return function (HttpContext $ctx, callable $next) use ($middleware): mixed {
				$instance = new $middleware();
				return $instance->handle($ctx, $next);
			};
		}

		throw new \Exception("Invalid middleware of type " . gettype($middleware) . ": " . json_encode($middleware));
	}

	/**
	 * Resolve a middleware alias to its fully qualified class name.
	 *
	 * @param string $alias The middleware alias (e.g. "auth")
	 * @return string Fully qualified middleware class name
	 *
	 * @throws \Exception If middleware class not found
	 */
	private function resolveMiddlewareAlias(string $alias): string
	{
		$baseName = Str::toPascalCase("{$alias} Middleware");
		$class = "App\\Middlewares\\{$baseName}";

		if (!class_exists($class)) {
			throw new \Exception("Middleware alias '{$alias}' not found (expected class {$class})");
		}

		return $class;
	}

	/**
	 * Get all normalized middleware callables attached to the route.
	 *
	 * @return callable[]
	 */
	public function getMiddlewares(): array
	{
		foreach ($this->middlewares as $mw) {
			$this->assertMiddlewareSignature($mw);
		}
		return $this->middlewares;
	}
}