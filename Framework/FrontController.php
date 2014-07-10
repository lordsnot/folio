<?php
namespace BM\SiteBuilder;

use BM\UrlMapper\UrlRouter;
use BMInjector, BMLib, Url;

use BM\Framework;
use BM\Framework\Request;
use BM\Framework\Response;
use BM\Injector;
use BM\Cache\Expirations\FixedLength;

class FrontController extends Framework\WebApplication
{
	const ASSET_SYMLINK = 'symlink';
	const ASSET_COPY = 'copy';
	
	/**
	 * The currently running site configuration
	 * 
	 * @var \BM\SiteBuilder\Site\Config
	 */
	public $site;
	
	/**
	 * @var BM\SiteBuilder\Site\Loader
	 */
	public $siteLoader;
	
	/**
	 * @var BM\SiteBuilder\Site\Config[]
	 */
	private $siteCache = array();

	/**
	 * @var BM\UrlMapper\UrlRouter
	 */
	public $urlRouter;
	
	public $urlTemplate;
	
	public $assetUrl;
	public $assetPath;
	public $assetMode = self::ASSET_SYMLINK;
	
	public $route;
	
	/**
	 * Query parameters to apply to every URL. When serviceUrl() and siteServiceUrl() are called,
	 * this will be merged with the query argument. 
	 */
	public $urlQuery=array();
	
	public $siteId;
	public $defaultSiteId;
	public $host;
	
	public $siteUrlKey = 'siteUrl';
	
	/**
	 * @var BM\SiteBuilder\Auth\Manager
	 */
	public $authManager;
	
	/**
	 * @var BM\Cache\Cache
	 */
	public $cache;
	
	/**
	 * The default expiration to use for authentication rules.
	 * 
	 * @var BM\Cache\Expiration
	 */
	public $accessExpiration;
	
	/**
	 * @var BM\Notes\Reader
	 */
	private $notesReader;
	
	public $publicPath;
	
	public function __construct($options)
	{
		$basePath = tryget($options['basePath'], true);
		unset($options['basePath']);
		parent::__construct($basePath);
		
		$this->publicPath = tryget($options['publicPath']) ?: $this->basePath.'/pub';
		$this->settings = tryget($options['settings']) ?: new \BM\Settings\Memory;
		$this->container = tryget($options['container']) ?: new \BM\Injector\Container;
		$this->serviceLoader = tryget($options['serviceLoader']) ?: new \BM\Framework\SimpleServiceLoader;
		$this->siteLoader = tryget($options['siteLoader']);
	}
	
	protected function parseServiceParams(&$service, &$params)
	{
		if (!$service)
			$service = array();
		if (is_string($service))
			$service = explode('/', trim($service, '/'));
		
		if (!$params)
			$params = [];
		
		$cnt = count($service);
		if ($cnt == 2)
			list($params['serviceId'], $params['viewActionId']) = $service;
		elseif ($cnt == 1)
			$params['serviceId'] = $service[0];
		elseif ($cnt !== 0)
			throw new \Exception("service ID ".implode("/", $service)." unparseable");
	}

	public function url($params, array $query=null)
	{
		if (isset($query[$this->siteUrlKey])) {
			throw new \InvalidArgumentException("Url query tried to clobber siteUrl parameter.");
		}
		
		if (!$this->site)
			throw new \UnexpectedValueException("Site must be loaded before URLs can be created");
		
		if (!isset($params['siteId']))
			$params['siteId'] = $this->siteId ?: $this->defaultSiteId;
		
		if (!isset($params['siteId']))
			throw new \InvalidArgumentException("Must pass a siteId key in \$params");
		
		$site = $params['siteId'] == $this->siteId 
			? $this->site 
			: $this->getSite($params['siteId'])
		;
		if (!$site)
			throw new \InvalidArgumentException("Unable to load site for params ".http_build_query($params, null, '&'));
		
		$url = array();
		if ($site->urlRouter) {
			$url = $site->urlRouter->url($params, $query);
			if ($url) {
				$query = null;
				$params = $url['query'];
				$params[$this->siteUrlKey] = $url['url'];
			}
		}
		
		$thisUrl = $this->urlRouter->url($params, $query);
		
		return $thisUrl;
	}
	
	public function resolveUrl($url)
	{
		$urlString = null;
		if (!$url instanceof \Url) {
			$urlString = $url;
			$url = new \Url($url);
		}
		
		if ($url->getScheme() == 'route') {
			$service = $url->getPath()->toString();
			if ($url->host == 'this')
				return $this->serviceUrl($service, $url->query);
			else
				return $this->siteServiceUrl($url->host, $service, $url->query);
		}
		
		return $urlString ?: $url->toString();
	}
	
	public function determineAssetPath()
	{
		if ($this->assetPath) return $this->assetPath;
		if ($this->assetUrl) return rtrim($this->publicPath, '/').$this->assetUrl;
	}

	public function serviceUrl($service, array $query=null, $host=null)
	{
		$params = array('siteId'=>$this->siteId);
		$this->parseServiceParams($service, $params);
		
		$url = $this->url($params, $query);
		if (!$url) {
			throw new \UnexpectedValueException(
				"Can't find url for params: "
				.\ArrayHelper::implodeAssoc($params, '=', ', ')
				.", query: "
				.\ArrayHelper::implodeAssoc($query ?: [], '=', ', ')
			);
		}
		return $this->formatUrl($url, $host);
	}
	
	public function siteServiceUrl($siteId, $service, array $query=null, $host=null)
	{
		$params = array('siteId'=>$siteId);
		$this->parseServiceParams($service, $params);
	
		$url = $this->url($params, $query);
		if (!$url) {
			throw new \UnexpectedValueException(
				"Can't find url for params: "
				.\ArrayHelper::implodeAssoc($params, '=', ', ')
				.", query: "
				.\ArrayHelper::implodeAssoc($query ?: [], '=', ', ')
			);
		}
		return $this->formatUrl($url, $host);
	}
	
	public function formatUrl(array $urlInfo, $host=null)
	{
		$query = tryget($urlInfo['query']);
		
		// not using array_merge because we want the global urlquery parameters
		// to appear at the end, but not to override existing parameters.
		// array_merge puts them first if you want this priority.
		foreach ($this->urlQuery as $k=>$v) {
			if (!isset($query[$k])) $query[$k] = $v;
		}
		
		// always pass &, otherwise it uses ini setting "arg_separator.output", which is unreliable
		$qs = http_build_query($query, null, '&');
		
		if ($qs) $qs = '?'.$qs;
		
		$fq = null;
		if ($host) {
			$fq = $this->ensureUrlHost('', $host); 
		}
		
		$return = null;
		if ($this->urlTemplate) {
			$return = $fq.strtr(
				$this->urlTemplate, 
				array(
					'<baseUrl>'  => rtrim($this->baseUrl, '/'), 
					'<frontUrl>' => $this->frontUrl,
					'<url>'      => $urlInfo['url'], 
					'<query>'    => $qs,
				)
			);
		}
		else {
			$return = $fq.rtrim($this->baseUrl, "/") . '/' . $this->frontUrl . $urlInfo['url'] . $qs;
		}
		return $return;
	}
	
	public function ensureUrlHost($url, $host)
	{
		if ($host === true)
			$host = $this->host ? $this->host : tryget($this->request->server['HTTP_HOST']);
		
		if (!$host)
			throw new \UnexpectedValueException("Cannot determine host");
		
		$fq = 'http'.(isset($this->request->server['HTTPS']) ? 's' : '').'://'.$host;
		
		if ($url)
			return rtrim($fq, '/').'/'.ltrim($url, '/');
		else
			return rtrim($fq, '/');
	}
	
	protected function getSite($siteId)
	{
		$site = null;
		if (!array_key_exists($siteId, $this->siteCache)) {
			$site = $this->siteLoader->get($siteId);
			$this->siteCache[$siteId] = $site;
		}
		return $site ?: $this->siteCache[$siteId];
	}

	public function parseUrl($url)
	{
		$route = $this->urlRouter->parseUrl($url);
		
		$siteId = self::sanitise(trim(tryget($route['params']['siteId'])));
		
		if (empty($siteId)) {
			if ($this->defaultSiteId)
				$siteId = $this->defaultSiteId;
			else
				return false;
		}
		
		$site = $this->getSite($siteId);
		if (!$site)
			return false;
		
		if (isset($route['params'][$this->siteUrlKey]) && $site->urlRouter) {
			$params = array();
			foreach ($route['tokens'] as $k)
				$params[$k] = $route['params'][$k];

			$currentUrl = $route['params'][$this->siteUrlKey];
			unset($route['params'][$this->siteUrlKey]);
			
			// BW: if the currentUrl is empty, have we parsed as much as we need, or
			// should we allow the site to define an empty route?
			if ($currentUrl) {
				$siteRoute = $site->urlRouter->parseUrl($currentUrl);
				if (!$siteRoute) {
					$route = null;
				}
				else {
					$route = array(
						'data'=>array(),
						'params'=>array_merge($route['params'], $siteRoute['params']),
						'tokens'=>array_merge($route['tokens'], $siteRoute['tokens']),
						'matched'=>$url,
						'parents'=>array($siteRoute, $route),
					);
				}
			}
		}
		
		return array($site, $route);
	}
	
	public function serviceAllowed($user, $service)
	{
		return $this->authManager->allowed($user, $service);
	}

	protected function loadService($id)
	{
		$service = null;
		
		if ($this->site->serviceLoader)
			$service = $this->site->serviceLoader->getService($id);
		
		if (!$service)
			$service = parent::loadService($id);
		
		return $service;
	}
	
	public function run($serviceId=null)
	{
		$url = $serviceId ?: (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '');
		
		if (!$this->urlRouter)
			throw new \Exception("Url router not set");
		
		// parseUrl also loads the site
		$parsed = $this->parseUrl($url);
		if (!$parsed)
			return $this->raiseError(404);
		
		list ($this->site, $route) = $parsed;
		$this->siteId = self::sanitise(trim($route['params']['siteId']));
		$this->route = $route;
		
		// HACK: there doesn't appear to be a better way to make hosts
		// dynamically switchable in the parameters file at this time.
		if ($this->settings->has('host'))
			$this->host = $this->settings->get('host');
		
		$this->site->init();
		
		$this->site->configureModules($this);
		
		$response = parent::run($this->resolveServiceId());
		
		if (!$response instanceof Response) {
			// a null response code will allow PHP to guess the code. If the
			// response is not a response object, the service code may have
			// done a header('Location:...') redirect or set the code by hand
			$body = $response;
			$response = new Response(null);
			$response->body = $body;
		}
		
		if ($response->code && $response->code != 200) {
			$responder = $this->findResponder($response->code);
			
			if ($responder) 
				$responder->handle($response, $this);
		}
		
		return $response;
	}
	
	protected function runService(Framework\Service $service)
	{
		if (!($service instanceof Service))
			throw new \UnexpectedValueException("FrontController only supports BM\\SiteBuilder\\Service instances, found ".\DebugHelper::getType($service));
	
		$this->populateService($service);
		
		// allows the service to substitute itself. necessary for ViewControllers.
		$newservice = $service->resolveService($this->request);
		
		if ($newservice != $service) {
			$newservice->parent = $service;
			$service = $newservice;
			$this->populateService($newservice);
		}
		
		$this->request->service = $service;
		
		$authorised = true;
		if ($this->authManager) {
			$user = $this->authManager->getAuthenticatedUser();
			$allowed = $this->serviceAllowed($user, $service);
			
			if (!$allowed) {
				if (!$user)
					$authorised = $this->authManager->prompt();
				else
					$authorised = false;
			}
			else {
				$this->request->user = $user;
			}
		}
		
		if ($authorised) {
			return parent::runService($service);
		}
		else {
			return $this->raiseError(401, 'You are not authorised to view this page');
		}
	}
	
	protected function configureModules()
	{
		parent::configureModules();
		
		// FIXME: modules happen on every request, so they should not be
		// dynamic dependencies.
		foreach ($this->container->keys() as $key) {
			if (strpos($key, "Module")===0) {
				$id = substr($key, 6);
				$module = $this->container->get($key);
				if (!$module->getId())
					$module->setId($id);
				
				$this->addModule($module);
			}
		}
	}
	
	protected function resolveServiceId($service=null)
	{
		$serviceId = null;
		if ($service == null && isset($this->route['params']['serviceId']))
			$serviceId = self::sanitise(trim($this->route['params']['serviceId']));
		else
			$serviceId = $service;

		if (empty($serviceId) && !empty($this->site->defaultServiceId))
			$serviceId = self::sanitise(trim($this->site->defaultServiceId));
		
		return $serviceId;
	}
	
	protected function populateService($service)
	{
		$service->setRequest($this->request);
		$service->site = $this->site;
		$service->front = $this;
	}
	
	protected function createRequest()
	{
		$rq = new Request();
		$rq->route = $this->route['params'];
		$rq->requestTime = time();
		return $rq;
	}
	
	protected function prepareRequest($rc)
	{
		parent::prepareRequest($rc);
		$this->site->populateRequest($rc);
		return $rc;
	}

	public static function sanitise($input)
	{
		return preg_replace("/[^0-9A-z_\-]/", "", $input);
	}
	
	/**
	 * @deprecated use return $front->respond()
	 */
	public function raiseError($code, $details=array())
	{
		return $this->respond($code, $details);
	}
	
	public function getNotesReader()
	{
		if (!$this->notesReader) {
			$this->notesReader = new \BM\Notes\Reader($this->cache);
		}
		return $this->notesReader;
	}
	
	public function setNotesReader($notesReader)
	{
		$this->notesReader = $notesReader;
		return $this;
	}
}
