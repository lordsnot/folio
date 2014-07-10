<?php
namespace BM\BigMobile\Wap; 

use BM\SiteBuilder\Recipe\Multi\DialogueUrlBuilder;

use BM\Framework\Application;
use BM\SiteBuilder;

class RendererSelector extends \BM\SiteBuilder\View\Renderer\DeviceSelector
{
	public $front;
	public $cache;
	public $cachePrefix;
	public $viewPath;
	public $default = 'smartphone';
	public $viewIdTemplates = [
		'smartphone'=>'<rendererId>/<viewId>'
	];
	public $aliases = [];
	
	public function __construct($front, $classifier, $viewPath, $cache, $cachePrefix=null)
	{
		throw new \Exception("aborted");
		
		$this->front = $front;
		$this->viewPath = $viewPath;
		$this->default = 'smartphone';
		$this->cache = $cache;
		$this->cachePrefix = $cachePrefix ?: $viewPath;
		
		parent::__construct($classifier);
		
		// parent::__construct interferes with $this->renderers
		$this->rendererContainer = $this->createRendererContainer();
		$this->renderers = [
			'smartphone'=>$this->rendererContainer->getProxy($this->renderers['smartphone'], 'RendererLocalTwig'),
			'dialogue'=>$this->rendererContainer->getProxy($this->renderers['dialogue'], 'ViewSelectorDefault'),
		];
	}
	
	private function createRendererContainer()
	{
		$rendererContainer = new \BM\Injector\Container();
		$rendererContainer->import([
			// renderers
			'ViewSelectorDefault'=>function ($container) {
				$selector = new \BM\SiteBuilder\View\Renderer\Selector();
				$selector->default = 'twig';
				$selector->renderers = array(
					'twig'=>$container->getProxy($selector->renderers['twig'], 'RendererDialogueTwig'),
				);
				return $selector;
			},
			
			'RendererDialogueTwig'=>function ($container) {
				$twig = new \BM\SiteBuilder\View\Renderer\Twig($container->get('ViewLoaderDialogue'));
				$twig->cachePrefix = $this->cachePrefix . '/dialogue';
				$twig->environment = array(
					'cacheManager'=>$this->cache,
					'debug'=>\BMLib::$debug,
				);
				return $twig;
			},
			
			'ViewLoaderDialogue'=>function ($container) {
				$loader = new \BM\Dialogue\SiteBuilder\View\Loader(
					new \BM\Dialogue\Msb\LegacyRenderer($container->get('MsbLoader'))
				);
				$loader->urlBuilder = new DialogueUrlBuilder($this->front);
				$loader->defaultSite = 'site';
				$loader->allPagesUnderHome = true;
				return $loader;
			},
			
			'RendererLocalTwig'=>function ($container) {
				$twig = new \BM\SiteBuilder\View\Renderer\Twig($container->get('ViewLoaderLocal'));
				$twig->cachePrefix = $this->cachePrefix . '/local';
				$twig->environment = array(
					'cacheManager'=>$this->cache,
					'debug'=>\BMLib::$debug,
				);
				return $twig;
			},
			
			'ViewLoaderLocal'=>function ($container) {
				$loader = new \BM\SiteBuilder\View\Loader\File($this->viewPath);
				$loader->template = '<id>.html.twig';
				return $loader;
			},
			
			'MsbLoader'=>array(
				'shared'=>true,
				'instantiate'=>function ($container) {
					return new \BM\Dialogue\Msb\Loader\File($this->viewPath.'/dialogue');
				},
			),
		]);
		return $rendererContainer;
	}
}
