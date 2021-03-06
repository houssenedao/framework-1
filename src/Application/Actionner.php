<?php

namespace Bow\Application;

use Bow\Contracts\ResponseInterface;
use Bow\Database\Barry\Model;
use Bow\Http\Request;
use Bow\Router\Exception\RouterException;
use Bow\Support\Collection;

class Actionner
{
    /**
     * La liste des namespaces défini dans l'application
     *
     * @var array
     */
    private $namespaces;

    /**
     * La liste de middleware charge dans l'application
     *
     * @var array
     */
    private $middlewares;

    /**
     * @var Actionner
     */
    private static $instance;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * Actionner constructor
     *
     * @param array $namespaces
     * @param array $middlewares
     */
    public function __construct(array $namespaces, array $middlewares)
    {
        $this->namespaces = $namespaces;

        $this->middlewares = $middlewares;

        $this->dispatcher = new Dispatcher;
    }

    /**
     * Configuration de l'actionneur
     *
     * @param array $namespaces
     * @param array $middlewares
     * @return static
     */
    public static function configure(array $namespaces, array $middlewares)
    {
        if (is_null(static::$instance)) {
            static::$instance = new static($namespaces, $middlewares);
        }

        return static::$instance;
    }

    /**
     * Récupère une instance de l'actonneur
     *
     * @return Actionner
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * Ajout un middleware à la liste
     *
     * @param array|callable $middlewares
     * @param bool $end
     */
    public function pushMiddleware($middlewares, $end = false)
    {
        $middlewares = (array) $middlewares;

        if ($end) {
            array_merge($this->middlewares, $middlewares);
        } else {
            array_merge($middlewares, $this->middlewares);
        }
    }

    /**
     * Ajout un namespace à la liste
     *
     * @param array|string $namespace
     */
    public function pushNamespace($namespace)
    {
        $namespace = (array) $namespace;

        $this->namespaces = array_merge($this->namespaces, $namespace);
    }

    /**
     * Lanceur de callback
     *
     * @param  callable|string|array $actions
     * @param  mixed  $param
     * @return mixed
     *
     * @throws RouterException
     */
    public function call($actions, $param = null)
    {
        $param = (array) $param;

        /**
         * Execution d'action definir comme chaine de caractère
         */
        if (is_string($actions) || is_callable($actions)) {
            $actions = [$actions];
        }

        if (!is_array($actions)) {
            throw new \InvalidArgumentException(
                'Le premier paramètre doit être un tableau, une chaine ou une closure',
                E_USER_ERROR
            );
        }

        $middlewares = [];

        /**
         * Vérification de l'existance de middleware associté à l'action
         * et extraction du middleware
         */
        if (isset($actions['middleware'])) {
            $middlewares = (array) $actions['middleware'];

            unset($actions['middleware']);
        }

        /**
         * Vérification de l'existance de controlleur associté à l'action
         * et extraction du controlleur
         */
        if (isset($actions['controller'])) {
            $actions = (array) $actions['controller'];
        }

        $functions = [];

        /**
         * Normalisation de l'action à executer et creation de
         * l'injection de dépendance
         */
        foreach ($actions as $key => $action) {
            if (is_string($action)) {
                array_push($functions, $this->controller($action));

                continue;
            }

            if (! is_callable($action)) {
                continue;
            }

            if (is_array($action) && $action[0] instanceof \Closure) {
                $injection = $this->injectorForClosure($action[0]);
            } else {
                $injection = $this->injectorForClosure($action);
            }

            array_push($functions, ['action' => $action, 'injection' => $injection]);
        }

        /**
         * Chargement des middlewares associés à l'action
         */
        foreach ($middlewares as $middleware) {
            if (is_callable($middleware)) {
                if ($middleware instanceof \Closure || is_array($middleware)) {
                    $this->dispatcher->pipe($middleware);

                    continue;
                }
            }

            if (class_exists($middleware)) {
                $this->dispatcher->pipe($middleware);

                continue;
            }
            
            $parts = [];

            if (is_string($middleware)) {
                $parts = explode(':', $middleware, 2);

                $middleware = $parts[0];
            }

            if (!array_key_exists($middleware, $this->middlewares)) {
                throw new RouterException(sprintf('%s n\'est pas un middleware définir.', $middleware), E_ERROR);
            }

            // On vérifie si le middleware définie est une middleware valide.
            if (!class_exists($this->middlewares[$middleware])) {
                throw new RouterException(sprintf('%s n\'est pas un class middleware.', $middleware));
            }

            // Add middleware into dispatch pipeline
            $this->dispatcher->pipe(
                $this->middlewares[$middleware],
                count($parts) != 2 ? [] : explode(',', $parts[1])
            );
        }

        // Process middleware dispatcher
        $response = $this->dispatcher->process(
            Request::getInstance()
        );

        switch (true) {
            case is_null($response):
            case is_string($response):
            case is_array($response):
            case is_object($response):
            case $response instanceof \Iterable:
            case $response instanceof ResponseInterface:
                return $response;
            case $response instanceof Model || $response instanceof Collection:
                return $response->toArray();
        }

        return $this->dispatchControllers($functions, $param);
    }

    /**
     * Execution of define controller
     *
     * @param array $functions
     * @param array $param
     * @return mixed
     */
    private function dispatchControllers(array $functions, array $param)
    {
        $response = null;

        // Lancement de l'éxècution de la liste des actions definir
        // Fonction a éxècuté suivant un ordre
        foreach ($functions as $function) {
            $response = call_user_func_array(
                $function['action'],
                array_merge($function['injection'], $param)
            );

            if ($response === true) {
                continue;
            }

            if ($response === false || is_null($response)) {
                return $response;
            }
        }

        return $response;
    }

    /**
     * Permet de faire un injection
     *
     * @param string $classname
     * @param string $method
     * @return array
     * @throws
     */
    public function injector($classname, $method = null)
    {
        $params = [];
        $reflection = new \ReflectionClass($classname);

        if (is_null($method)) {
            $method = "__invoke";
        }

        $parameters = $reflection->getMethod($method)->getParameters();

        foreach ($parameters as $parameter) {
            $class = $parameter->getClass();

            if (is_null($class)) {
                continue;
            }

            $contructor = $class->getName();

            if (! class_exists($contructor, true)) {
                continue;
            }

            if (!in_array(strtolower($contructor), $this->getInjectorExceptedType())) {
                if (method_exists($contructor, 'getInstance')) {
                    $params[] = $contructor::getInstance();
                } else {
                    $params[] = new $contructor();
                }
            }
        }

        return $params;
    }

    /**
     * Injection de type pour closure
     *
     * @param callable $closure
     * @return array
     * @throws
     */
    public function injectorForClosure(callable $closure)
    {
        $reflection = new \ReflectionFunction($closure);
        $parameters = $reflection->getParameters();
        $params = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (is_null($type)) {
                continue;
            }

            $class = trim($type->getName());

            if (! class_exists($class, true)) {
                continue;
            }

            if (!in_array(strtolower($class), $this->getInjectorExceptedType())) {
                if (method_exists($class, 'getInstance')) {
                    $params[] = $class::getInstance();
                } else {
                    $params[] = new $class();
                }
            }
        }

        return $params;
    }

    /**
     * La liste de type non permis
     *
     * @return array
     */
    private function getInjectorExceptedType()
    {
        return [
            'string', 'array', 'bool', 'int',
            'integer', 'double', 'float', 'callable',
            'object', 'stdclass', '\closure', 'closure'
        ];
    }

    /**
     * Next, lance successivement une liste de fonction.
     *
     * @param array|callable $arr
     * @param array|callable $arg
     * @return mixed
     */
    public function execute($arr, $arg)
    {
        if (is_callable($arr)) {
            return call_user_func_array($arr, $arg);
        }

        if (is_array($arr)) {
            return call_user_func_array($arr, $arg);
        }

        // On lance la loader de controller si $cb est un String
        $controller = $this->controller($arr);

        if ($controller['action'][1] == null) {
            array_splice($controller['action'], 1, 1);
        }

        if (is_array($controller)) {
            return call_user_func_array(
                $controller['action'],
                array_merge($controller['injection'], $arg)
            );
        }

        return false;
    }

    /**
     * Charge les controleurs definie comme chaine de caractère
     *
     * @param string $controller_name
     *
     * @return array
     */
    public function controller($controller_name)
    {
        // Récupération de la classe et de la methode à lancer.
        if (is_null($controller_name)) {
            return null;
        }

        $parts = preg_split('/::|@/', $controller_name);

        if (count($parts) == 1) {
            $parts[1] = '__invoke';
        }

        list($class, $method) = $parts;

        if (!class_exists($class, true)) {
            $class = sprintf('%s\\%s', $this->namespaces['controller'], ucfirst($class));
        }

        $injections = $this->injector($class, $method);

        return [
            'action' => [new $class(), $method],
            'injection' => $injections
        ];
    }

    /**
     * Charge les closure definir comme action
     *
     * @param \Closure $closure
     *
     * @return array
     */
    public function closure($closure)
    {
        // Récupération de la classe et de la methode à lancer.
        if (!is_callable($closure)) {
            return null;
        }

        $injections = $this->injectorForClosure($closure);

        return [
            'action' => $closure,
            'injection' => $injections
        ];
    }
}
