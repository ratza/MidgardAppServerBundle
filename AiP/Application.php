<?php
namespace Midgard\AppServerBundle\AiP;

use Symfony\Component\HttpFoundation\Request;
use Midgard\AppServerBundle\AiP\SessionStorage\AiPSessionStorage;

class Application
{
    /**
     * @var Symfony\Component\HttpKernel\Kernel
     */
    private $kernel;

    private $prefix;

    /**
     * Construct prepares the AppServer in PHP URL mappings
     * and is run once. It also loads the Symfony Application kernel
     */
    public function __construct(array $config)
    {
        require __DIR__ . "/../../../../../../app/{$config['kernelFile']}";
        $kernelClass = "\\{$config['kernel']}";

		$this->kernel = new $kernelClass($config['environment'], $config['debug']);
		if ($config['debug']) {
			\Symfony\Component\Debug\Debug::enable();
		}
        $this->kernel->loadClassCache();
		Request::enableHttpMethodParameterOverride();
        $this->kernel->boot();
        $this->prefix = $config['path'];
    }

    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Invoke is run once per each request. Here we generate a
     * Request object, tell Symfony2 to handle it, and eventually
     * return the Result contents back to AiP
     */
    public function __invoke($context)
    {
        // Prepare Request object
        $request = $this->ctx2Request($context);

        $session = $this->kernel->getContainer()->get('session.storage');
        if ($session instanceof AiPSessionStorage) {
            $session->setContext($context);
        }

		$response = $this->kernel->handle($request);

        foreach ($response->headers->getCookies() as $cookie) {
            $context['_COOKIE']->setcookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
        }

        if ($session instanceof AiPSessionStorage) {
            $session->commitSession();
        }

        return array($response->getStatusCode(), $this->getHeaders($response), $response->getContent());
    }

    private function ctx2Request($context)
    {
        $requestUri = $context['env']['REQUEST_URI'];
        $_SERVER = $context['env'];

        $uriParts = explode('?', $requestUri);
        $_SERVER['PHP_SELF'] = $uriParts[0];
        $_SERVER['SCRIPT_FILENAME'] = "/some/path{$this->prefix}";

        if (isset($context['_GET'])) {
            $_GET = $context['_GET'];
            $_REQUEST = $_GET;
        }
        if (isset($context['_POST'])) {
            $_POST = $context['_POST'];
            $_REQUEST = $_POST;
        }
        if (isset($context['_FILES'])) {
            $_FILES = $context['_FILES'];
        }
        $_COOKIE = $context['_COOKIE']->__toArray();

        return Request::createFromGlobals();
    }

    /**
     * Normalize headers from a Symfony2 Response ParameterBag
     * to the array used by AiP
     */
    private function getHeaders($response)
    {
        $ret = array();
        $headers = $response->headers->all();
        foreach ($headers as $header => $values) {
            $ret[] = $header;
            $ret[] = implode(';', $values);
        }
        return $ret;
    }
}
