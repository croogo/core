<?php

namespace Croogo\Core\Controller;

use App\Controller\AppController;
use Cake\Controller\ErrorController;
use Cake\Controller\Exception\MissingComponentException;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Utility\Hash;

use Croogo\Core\Croogo;
use Croogo\Core\PropertyHookTrait;
use Croogo\Extensions\CroogoTheme;

/**
 * Croogo App Controller
 *
 * @category Croogo.Controller
 * @package  Croogo.Croogo.Controller
 * @version  1.5
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class Controller extends AppController {

	use PropertyHookTrait;

/**
 * Default Components
 *
 * @var array
 * @access public
 */
	protected $_defaultComponents = array(
		'Croogo/Core.Croogo',
		'Croogo/Acl.Filter',
		'Security',
		'Acl.Acl',
		'Auth',
		'Flash',
		'RequestHandler',
	);

/**
 * List of registered Application Components
 *g
 * These components are typically hooked into the application during bootstrap.
 * @see Croogo::hookComponent
 */
	protected $_appComponents = array();

/**
 * List of registered API Components
 *
 * These components are typically hooked into the application during bootstrap.
 * @see Croogo::hookApiComponent
 */
	protected $_apiComponents = array();

/**
 * Pagination
 */
	public $paginate = array(
		'limit' => 10,
	);

/**
 * Cache pagination results
 *
 * @var boolean
 * @access public
 */
	public $usePaginationCache = true;

/**
 * View
 *
 * @var string
 * @access public
 */
//	public $viewClass = 'Theme';

/**
 * Constructor
 *
 * @access public
 * @param Request $request
 * @param Response $response
 * @param null $name
 */
	public function __construct(Request $request = null, Response $response = null, $name = null) {
		parent::__construct($request, $response, $name);
		if ($request) {
			$request->addDetector('api', array(
				'callback' => array('CroogoRouter', 'isApiRequest'),
			));
			$request->addDetector('whitelisted', array(
				'callback' => array('CroogoRouter', 'isWhitelistedRequest'),
			));
		}
		$this->eventManager()->dispatch(new Event('Controller.afterConstruct', $this));
		$this->afterConstruct();
	}

/**
 * Initialize
 */
	public function initialize() {
		parent::initialize();

		Croogo::applyHookProperties('Hook.controller_properties', $this);
		$this->_setupComponents();
	}

/**
 * implementedEvents
 */
	public function implementedEvents() {
		return parent::implementedEvents() + array(
			'Controller.afterConstruct' => 'afterConstruct',
		);
	}

/**
 * afterConstruct
 *
 * called when Controller::__construct() is complete.
 * Override this method to perform class configuration/initialization that
 * needs to be performed earlier from Controller::beforeFilter().
 *
 * You still need to call parent::afterConstruct() method to ensure correct
 * behavior.
 */
	public function afterConstruct() {
		if (empty($this->viewClass)) {
			$this->viewClass = 'Croogo/Core.Croogo';
		}
		Croogo::applyHookProperties('Hook.controller_properties', $this);

		$this->viewBuilder()->helpers(Croogo::options('Hook.view_builder_options', $this, 'helpers'));

		$this->_setupComponents();
	}

/**
 * Setup the components array
 *
 * @param void
 * @return void
 */
	protected function _setupComponents() {
		$components = [];

		$components = Hash::merge(
			$this->_defaultComponents,
			$this->_appComponents
		);

		foreach ($components as $component => $config) {
			if (!is_array($config)) {
				$component = $config;
				$config = [];
			}

			$this->loadComponent($component, $config);
		}
	}

	public function loadComponent($name, array $config = []) {
		list(, $prop) = pluginSplit($name);
		list(, $modelProp) = pluginSplit($this->modelClass);
		$component = $this->components()->load($name, $config);
		if ($prop !== $modelProp) {
			$this->{$prop} = $component;
		}
		return $component;
	}

/**
 * Setup themes
 *
 * @return void
 */
	protected function _setupTheme() {
		$theme = Configure::read('Site.theme');
		if (!$theme) {
			return;
		}

		$this->viewBuilder()->theme($theme);
		$this->_loadThemeSettings($theme);
	}

/**
 * Load theme settings
 *
 * @return void
 */
	protected function _loadThemeSettings($theme) {
		$prefix = $this->request->param('prefix');
		$croogoTheme = new CroogoTheme();
		$settings = $croogoTheme->getData($theme)['settings'];

		$themePrefix = ($prefix) ? $prefix : '';

		$themeHelpers = [];
		if (isset($settings['prefixes'][$themePrefix])) {
			foreach ($settings['prefixes'][$themePrefix]['helpers'] as $helper => $options) {
				$themeHelpers[$helper] = $options;
			}
		}

		$this->viewBuilder()->helpers($themeHelpers);
	}

/**
 * Allows extending action from component
 *
 * @throws MissingActionException
 */
	public function invokeAction() {
		$request = $this->request;
		try {
			return parent::invokeAction($request);
		} catch (MissingActionException $e) {
			$params = $request->params;
			$prefix = isset($params['prefix']) ? $params['prefix'] : '';
			$action = str_replace($prefix . '_', '', $params['action']);
			foreach ($this->_apiComponents as $component => $setting) {
				if (empty($this->{$component})) {
					continue;
				}
				if ($this->{$component}->isValidAction($action)) {
					$this->setRequest($request);
					return $this->{$component}->{$action}($this);
				}
			}
			throw $e;
		}
	}

/**
 * beforeFilter
 *
 * @return void
 * @throws MissingComponentException
 */
	public function beforeFilter(Event $event) {
		parent::beforeFilter($event);
		$aclFilterComponent = 'Filter';
		if (empty($this->{$aclFilterComponent})) {
			throw new MissingComponentException(array('class' => $aclFilterComponent));
		}
		$this->{$aclFilterComponent}->auth();

		if (!$this->request->is('api')) {
			$this->Security->blackHoleCallback = 'securityError';
			if ($this->request->param('action') == 'delete' && $this->request->param('prefix') == 'admin') {
				$this->request->allowMethod('post');
			}
		}

		$this->viewBuilder()->helpers([
			'Html',
			'Form',
			'Text',
			'Time',
			'Croogo/Core.Js',
			'Croogo/Core.Layout',
		]);

		$this->_setupTheme();

		if ($this->viewBuilder()->theme()) {
			$this->viewBuilder()->helpers(['Croogo/Core.Theme']);
		}

		if ($this->request->is('ajax')) {
			$this->viewBuilder()->layout('ajax');
		}

		if (isset($this->request->params['locale'])) {
			Configure::write('Config.language', $this->request->params['locale']);
		}
	}

/**
 * blackHoleCallback for SecurityComponent
 *
 * @return void
 */
	public function securityError($type) {
		switch ($type) {
			case 'auth':
			break;
			case 'csrf':
			break;
			case 'get':
			break;
			case 'post':
			break;
			case 'put':
			break;
			case 'delete':
			break;
			default:
			break;
		}
		$this->set(compact('type'));
		$this->response = $this->render('../Errors/security');
		$this->response->statusCode(400);
		$this->response->send();
		$this->_stop();
		return false;
	}

/**
 * _setupAclComponent
 */
	protected function _setupAclComponent() {
		$config = Configure::read('Access Control');
		if (isset($config['rowLevel']) && $config['rowLevel'] == true) {
			if (strpos($config['models'], $this->plugin . '.' . $this->modelClass) === false) {
				return;
			}
			$this->Components->load(Configure::read('Site.acl_plugin') . '.RowLevelAcl');
		}
	}

/**
 * Combine add and edit views
 *
 * @see Controller::render()
 */
	public function render($view = null, $layout = null) {
//		list($plugin, ) = pluginSplit(App::location(get_parent_class($this)));
//		if ($plugin) {
//			App::build(array(
//				'View' => array(
//					Plugin::path($plugin) . 'View' . DS,
//				),
//			), App::APPEND);
//		}

		if (strpos($view, '/') !== false || $this instanceof ErrorController) {
			return parent::render($view, $layout);
		}

		$fallbackView = $this->__getDefaultFallbackView();
		if (is_null($view) && in_array($this->request->action, ['edit', 'add'])) {
			$searchPaths = App::path('Template', $this->plugin);
			if ($this->viewBuilder()->theme()) {
				$searchPaths = array_merge(App::path('Template', $this->viewBuilder()->theme()), $searchPaths);
			}

			$view = $this->__findRequestedView($searchPaths);
			if (empty($view)) {
				$view = $fallbackView;
			}
		}

		return parent::render($view, $layout);
	}

/**
 * Get Default Fallback View
 *
 * @return string
 */
	private function __getDefaultFallbackView() {
		$fallbackView = 'form';
		if (!empty($this->request->params['prefix']) && $this->request->params['prefix'] === 'admin') {
			$fallbackView = 'form';
		}
		return $fallbackView;
	}

/**
 * Search for existing view override in registered view paths
 *
 * @return string
 */
	private function __findRequestedView($viewPaths) {
		if (empty($viewPaths)) {
			return false;
		}

		foreach ($viewPaths as $path) {
			$file = $this->viewBuilder()->templatePath() . DS . $this->request->action . '.ctp';
			$requested = $path . $file;
			if (file_exists($requested)) {
				return $requested;
			} else {
				if (!$this->plugin) {
					continue;
				}
				$requested = $path . 'Plugin' . DS . $this->plugin . DS . $file;
				if (file_exists($requested)) {
					return $requested;
				}
			}
		}
		return false;
	}
}
