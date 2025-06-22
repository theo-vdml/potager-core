<?php

namespace Potager\Router;

use Negotiation\Accept;
use Negotiation\AcceptCharset;
use Negotiation\AcceptEncoding;
use Negotiation\AcceptHeader;
use Negotiation\AcceptLanguage;
use Negotiation\CharsetNegotiator;
use Negotiation\EncodingNegotiator;
use Negotiation\LanguageNegotiator;
use Negotiation\Negotiator;
use Potager\Support\Arr;


/**
 * Class Request
 *
 * Represents an HTTP request, encapsulating server data, query parameters, 
 * post data, routing information, and content negotiation headers.
 */
class Request
{
	/** @var array $_SERVER superglobal */
	protected array $server;

	/** @var array $_GET superglobal */
	protected array $get;

	/** @var array $_POST superglobal */
	protected array $post;

	/** @var string Request method (GET, POST, etc.) */
	protected string $method;

	/** @var string Request URL path */
	protected string $url;

	/** @var Route|null Matched route object */
	protected ?Route $route;

	/** @var array Route parameters extracted from URL */
	protected array $params = [];

	// Content Negotiation Cache
	protected ?array $types = null;
	protected ?AcceptHeader $preferedType = null;

	protected ?array $languages = null;
	protected ?AcceptHeader $preferedLanguage = null;

	protected ?array $charsets = null;
	protected ?AcceptHeader $preferedCharset = null;

	protected ?array $encodings = null;
	protected ?AcceptHeader $preferedEncoding = null;

	/**
	 * Request constructor.
	 *
	 * @param array $server Typically $_SERVER
	 * @param array $get Typically $_GET
	 * @param array $post Typically $_POST
	 */
	public function __construct(array $server = [], array $get = [], array $post = [])
	{
		$this->server = $server;
		$this->get = $get;
		$this->post = $post;

		$this->method = $server['REQUEST_METHOD'] ?? 'GET';

		$this->url = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
	}

	// ─────────────────────────────────────────────
	// 🧭 Request Basics
	// ─────────────────────────────────────────────

	/**
	 * Get HTTP method (GET, POST, PUT, etc.)
	 *
	 * @return string
	 */
	public function method(): string
	{
		return $this->method;
	}

	/**
	 * Get the URL path (without query string)
	 *
	 * @return string
	 */
	public function url(): string
	{
		return $this->url;
	}

	/**
	 * Get value from HTTP header.
	 *
	 * @param string $key Header name (e.g., Accept)
	 * @param mixed $default Default value if header not found
	 * @return mixed
	 */
	public function header(string $key, mixed $default = null): mixed
	{
		$normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
		return $this->server[$normalized] ?? $default;
	}

	// ─────────────────────────────────────────────
	// 🗺️ Routing & Parameters
	// ─────────────────────────────────────────────

	/**
	 * Attach a matched route and extract its parameters.
	 *
	 * @param Route $route
	 * @return void
	 * @internal
	 */
	public function attachRoute(Route $route)
	{
		$this->route = $route;
		$this->params = $route->getParams();
	}

	/**
	 * Get route parameters.
	 *
	 * @return array
	 */
	public function params(): array
	{
		return $this->params;
	}

	// ─────────────────────────────────────────────
	// 🧾 Input: Query + Body
	// ─────────────────────────────────────────────

	/**
	 * Get a single input value from merged parameters (POST overrides GET).
	 *
	 * @param string $key     The input key to retrieve.
	 * @param mixed  $default Default value if key does not exist.
	 * @return mixed
	 */
	public function input(string $key, mixed $default = null): mixed
	{
		$data = $this->all();
		return $data[$key] ?? $default;
	}

	/**
	 * Get query string parameters ($_GET).
	 *
	 * @return array
	 */
	public function qs(): array
	{
		return $this->get;
	}

	/**
	 * Get POST body parameters ($_POST).
	 *
	 * @return array
	 */
	public function body(): array
	{
		return $this->post;
	}

	/**
	 * Get merged parameters (POST then GET override).
	 *
	 * @return array
	 */
	public function all(): array
	{
		return array_merge($this->post, $this->get);
	}

	/**
	 * Get only specific input keys from merged parameters.
	 *
	 * @param array $keys
	 * @return array
	 */
	public function only(array $keys): array
	{
		return array_filter($this->all(), fn($key): bool => in_array($key, $keys), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Get all input except specified keys.
	 *
	 * @param array $keys
	 * @return array
	 */
	public function except(array $keys): array
	{
		return array_filter($this->all(), fn($key): bool => !in_array($key, $keys), ARRAY_FILTER_USE_KEY);
	}

	// ─────────────────────────────────────────────
	// 🌐 Content Negotiation
	// ─────────────────────────────────────────────

	/**
	 * Returns an array of content types sorted by client preference.
	 *
	 * @return Accept[] Array of AcceptHeader objects in order of client preference.
	 */
	public function types(): array
	{
		if ($this->types) {
			return $this->types;
		}
		$negotiator = new Negotiator();
		$header = $this->server['HTTP_ACCEPT'] ?? '';
		$this->types = $negotiator->getOrderedElements($header);
		return $this->types;
	}

	/**
	 * Returns the best matching content type from a list of priorities.
	 *
	 * @param string|array $priorities A single type or list of acceptable types.
	 * @return ?Accept Best matching AcceptHeader or null if none matched.
	 */
	public function accepts(string|array $priorities): ?Accept
	{
		if ($this->preferedType) {
			return $this->preferedType;
		}
		$negotiator = new Negotiator();
		$header = $this->server['HTTP_ACCEPT'] ?? 'text/html';
		$priorities = Arr::wrap($priorities);
		$this->preferedType = $negotiator->getBest($header, $priorities);
		return $this->preferedType;
	}

	/**
	 * Returns an array of languages sorted by client preference.
	 *
	 * @return AcceptLanguage[] Array of AcceptHeader objects in order of client preference.
	 */
	public function languages(): array
	{
		if ($this->languages) {
			return $this->languages;
		}
		$negotiator = new LanguageNegotiator();
		$header = $this->server['HTTP_ACCEPT_LANGUAGE'] ?? '';
		$this->languages = $negotiator->getOrderedElements($header);
		return $this->languages;
	}

	/**
	 * Returns the best matching language from a list of priorities.
	 *
	 * @param string|array $priorities A single language or list of acceptable languages.
	 * @return ?AcceptLanguage Best matching AcceptHeader or null if none matched.
	 */
	public function language(string|array $priorities): ?AcceptLanguage
	{
		if ($this->preferedLanguage) {
			return $this->preferedLanguage;
		}
		$negotiator = new LanguageNegotiator();
		$header = $this->server['HTTP_ACCEPT_LANGUAGE'] ?? '';
		$priorities = Arr::wrap($priorities);
		$this->preferedLanguage = $negotiator->getBest($header, $priorities);
		return $this->preferedLanguage;
	}

	/**
	 * Returns an array of charsets sorted by client preference.
	 *
	 * @return AcceptCharset[] Array of AcceptHeader objects in order of client preference.
	 */
	public function charsets(): array
	{
		if ($this->charsets) {
			return $this->charsets;
		}
		$negotiator = new CharsetNegotiator();
		$header = $this->server['HTTP_ACCEPT_CHARSET'] ?? '';
		$this->charsets = $negotiator->getOrderedElements($header);
		return $this->charsets;
	}

	/**
	 * Returns the best matching charset from a list of priorities.
	 *
	 * @param string|array $priorities A single charset or list of acceptable charsets.
	 * @return ?AcceptCharset Best matching AcceptHeader or null if none matched.
	 */
	public function charset(string|array $priorities): ?AcceptCharset
	{
		if ($this->preferedCharset) {
			return $this->preferedCharset;
		}
		$negotiator = new CharsetNegotiator();
		$header = $this->server['HTTP_ACCEPT_CHARSET'] ?? '';
		$priorities = Arr::wrap($priorities);
		$this->preferedCharset = $negotiator->getBest($header, $priorities);
		return $this->preferedCharset;
	}

	/**
	 * Returns an array of encodings sorted by client preference.
	 *
	 * @return AcceptEncoding[] Array of AcceptHeader objects in order of client preference.
	 */
	public function encodings(): array
	{
		if ($this->encodings) {
			return $this->encodings;
		}
		$negotiator = new EncodingNegotiator();
		$header = $this->server['HTTP_ACCEPT_ENCODING'] ?? '';
		$this->encodings = $negotiator->getOrderedElements($header);
		return $this->encodings;
	}

	/**
	 * Returns the best matching encoding from a list of priorities.
	 *
	 * @param string|array $priorities A single encoding or list of acceptable encodings.
	 * @return ?AcceptEncoding Best matching AcceptHeader or null if none matched.
	 */
	public function encoding(string|array $priorities): ?AcceptEncoding
	{
		if ($this->preferedEncoding) {
			return $this->preferedEncoding;
		}
		$negotiator = new EncodingNegotiator();
		$header = $this->server['HTTP_ACCEPT_ENCODING'] ?? '';
		$priorities = Arr::wrap($priorities);
		$this->preferedEncoding = $negotiator->getBest($header, $priorities);
		return $this->preferedEncoding;
	}
}