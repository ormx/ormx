<?php
/**
 * Created by PhpStorm.
 * User: Xav
 * Date: 30-Jun-16
 * Time: 13:35
 */

namespace OrmX;

use Application\Controller\LoginController;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Mvc\MvcEvent;
use Zend\Session\Container;
use Zend\View\Model\JsonModel;

abstract class AbstractRestController extends AbstractRestfulController
{
    protected $session;
    protected $view;

    public function __construct()
    {
        $this->view = new JsonModel();
        $this->setSession(new Container(LoginController::USER));
    }

    /**
     * @param null|Container $session
     * @return $this
     */
    public function setSession($session)
    {
        $this->session = $session;

        return $this;
    }

    /**
     * @return Container
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * We use exception codes to send back certain info about a request, but other exceptions such as DB access will send back
     * a code that isn't a HTTP status so capture it see if it's valid and return it otherwise the util will send back a 500
     *
     * @param MvcEvent $e
     * @return mixed
     * @throws \Zend\View\Exception\InvalidArgumentException
     */
    public function onDispatch(MvcEvent $e)
    {
        try {
            $return = parent::onDispatch($e);
        } catch (\Exception $t) {
            $this->getResponse()
                 ->setStatusCode(Util::httpStatus($t->getCode()));

            $response['success'] = false;
            $response['message'] = $t->getMessage();
            if (\defined('APP_DEBUG') && APP_DEBUG === true) {
                $response['callStack'] = $t->getTrace();
            }
            $return = $this->getView();
            $return->setVariables($response);
            $e->setResult($return);
        } catch (\Throwable $t) {
            $this->getResponse()
                 ->setStatusCode(Util::httpStatus($t->getCode()));

            $response['success'] = false;
            $response['message'] = $t->getMessage();
            if (\defined('APP_DEBUG') && APP_DEBUG === true) {
                $response['callStack'] = $t->getTrace();
            }
            $return = $this->getView();
            $return->setVariables($response);
            $e->setResult($return);
        }

        return $return;
    }

    /**
     * @return JsonModel
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param JsonModel $view
     */
    public function setView(JsonModel $view)
    {
        $this->view = $view;
    }
}