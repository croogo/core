<?php

namespace Croogo\Core\Controller\Api;

use Cake\Controller\Component\AuthComponent;
use Cake\Controller\Controller;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;

/**
 * Base Api Controller
 *
 */
class AppController extends Controller
{

    protected function setupAuthConfig()
    {
        $authConfig = [
            AuthComponent::ALL => [
                'userModel' => 'Croogo/Users.Users',
                'fields' => [
                    'username' => 'username',
                    'password' => 'password',
                ],
                'passwordHasher' => [
                    'className' => 'Fallback',
                    'hashers' => ['Default', 'Weak'],
                ],
                'scope' => [
                    'Users.status' => true,
                ],
            ],
        ];

        $authConfig = [
            'authenticate' => [
                'Form',
            ],
        ];

        if (Plugin::loaded('ADmad/JwtAuth')) {
            $authConfig['authenticate']['ADmad/JwtAuth.Jwt'] = [
                'fields' => [
                    'username' => 'id',
                ],
                'parameter' => 'token',
                'queryDatasource' => true,
            ];

            $authConfig += [
                'unauthorizedRedirect' => false,
                'checkAuthInd' => 'Controller.initialize',
                'loginAction' => false,
            ];
        }

        return $authConfig;
    }

/**
 * Initialize
 *
 * @return void
 */
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('Auth', $this->setupAuthConfig());
        $this->loadComponent('RequestHandler');

        $this->loadComponent('Crud.Crud', [
            'actions' => [
                'index' => [
                    'className' => 'Crud.Index',
                ],
                'lookup' => [
                    'className' => 'Crud.Lookup',
                    'findMethod' => 'all'
                ],
                'view' => [
                    'className' => 'Crud.View',
                ],
                'add' => [
                    'className' => 'Crud.Add',
                ],
                'edit' => [
                    'className' => 'Crud.Edit',
                ],
                'delete' => [
                    'className' => 'Crud.Delete'
                ]
            ],
            'listeners' => [
                'Crud.Search',
                'Crud.RelatedModels',
                'Crud.Api',
            ]
        ]);

        Configure::write('debug', false);

    }

    /**
     * beforeFilter
     *
     * @return void
     */
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);

        if (Configure::read('Site.status') == 0 &&
            $this->Auth->user('role_id') != 1
        ) {
            if (!$this->request->is('whitelisted')) {
                $this->response->statusCode(503);
            }
        }
    }

}
