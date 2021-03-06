<?php

namespace Bow\Http;

use Bow\Session\Session;
use Bow\Support\Collection;
use Bow\Support\Str;
use Bow\Validation\Validate;
use Bow\Validation\Validator;

class Request
{
    /**
     * Variable d'instance
     *
     * @static self
     */
    private static $instance;

    /**
     * @var array
     */
    private $input;

    /**
     * Constructeur
     */
    private function __construct()
    {
        $this->input = array_merge($_POST, $_GET);

        foreach ($this->input as $key => $value) {
            $value = trim($value);

            if (strlen($value) == 0) {
                $value = null;
            }

            $this->input[$key] = $value;
        }
    }

    /**
     * Singletion loader
     *
     * @return null|self
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Check if key is exists
     *
     * @param string $key
     * @return mixed
     */
    public function has($key)
    {
        return $this->input[$key] ?? null;
    }

    /**
     * Get all input value
     *
     * @return array
     */
    public function all()
    {
        return $this->input;
    }

    /**
     * retourne uri envoyer par client.
     *
     * @return string
     */
    public function path()
    {
        $pos = strpos($_SERVER['REQUEST_URI'], '?');

        if ($pos) {
            $uri = substr($_SERVER['REQUEST_URI'], 0, $pos);
        } else {
            $uri = $_SERVER['REQUEST_URI'];
        }

        return $uri;
    }

    /**
     * retourne le nom host du serveur.
     *
     * @return string
     */
    public function hostname()
    {
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * retourne url envoyé par client.
     *
     * @return string
     */
    public function url()
    {
        return $this->origin().$this->path();
    }

    /**
     * origin le nom du serveur + le scheme
     *
     * @return string
     */
    public function origin()
    {
        if (!isset($_SERVER['REQUEST_SCHEME'])) {
            return 'http://' . $this->hostname();
        }

        return $this->scheme().'://'.$this->hostname();
    }

    /**
     * Request scheme
     *
     * @return string
     */
    private function scheme()
    {
        return isset($_SERVER['REQUEST_SCHEME']) ? strtolower($_SERVER['REQUEST_SCHEME']) : 'http';
    }

    /**
     * retourne path envoyé par client.
     *
     * @return string
     */
    public function time()
    {
        return $_SESSION['REQUEST_TIME'];
    }

    /**
     * Retourne la methode de la requete.
     *
     * @return string
     */
    public function method()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

        if ($method == 'POST') {
            if (array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
                if (in_array($_SERVER['HTTP_X_HTTP_METHOD'], ['PUT', 'DELETE'])) {
                    $method = $_SERVER['HTTP_X_HTTP_METHOD'];
                }
            }
        }

        return $method;
    }

    /**
     * Si la réquête est de type POST
     *
     * @return bool
     */
    public function isPost()
    {
        if ($this->method() == 'POST') {
            return true;
        }

        return false;
    }

    /**
     * Si la réquête est de type GET
     *
     * @return bool
     */
    public function isGet()
    {
        if ($this->method() == 'GET') {
            return true;
        }

        return false;
    }

    /**
     * Si la réquête est de type PUT
     *
     * @return bool
     */
    public function isPut()
    {
        if ($this->method() == 'PUT' || $this->get('_method') == 'PUT') {
            return true;
        }

        return false;
    }

    /**
     * Si la réquête est de type DELETE
     *
     * @return bool
     */
    public function isDelete()
    {
        if ($this->method() == 'DELETE' || $this->get('_method') == 'DELETE') {
            return true;
        }

        return false;
    }

    /**
     * Charge la factory pour le FILES
     *
     * @param  string $key
     * @return UploadFile|Collection
     */
    public function file($key)
    {
        if (!isset($_FILES[$key])) {
            return null;
        }

        if (!is_array($_FILES[$key]['name'])) {
            return new UploadFile($_FILES[$key]);
        }

        $files = $_FILES[$key];

        $collect = [];

        foreach ($files['name'] as $key => $name) {
            $file['name'] = $name;

            $file['type'] = $files['type'][$key];

            $file['size'] = $files['size'][$key];

            $file['error'] = $files['error'][$key];

            $file['tmp_name'] = $files['tmp_name'][$key];

            $collect[] = new UploadFile($file);
        }

        return new Collection($collect);
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public static function hasFile($key)
    {
        return isset($_FILES[$key]);
    }

    /**
     * Accès au donnée de la précédente requete
     *
     * @param  mixed $key
     * @return mixed
     */
    public function old($key)
    {
        $old = Session::getInstance()->get('__bow.old', []);

        return $old[$key] ?? null;
    }

    /**
     * Vérifie si on n'est dans le cas d'un requête AJAX.
     *
     * @return boolean
     */
    public function isAjax()
    {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return false;
        }

        $xhr_obj = Str::lower($_SERVER['HTTP_X_REQUESTED_WITH']);

        if ($xhr_obj == 'xmlhttprequest' || $xhr_obj == 'activexobject') {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si une url match avec le pattern
     *
     * @param  string $match Un regex
     * @return int
     */
    public function is($match)
    {
        return preg_match('@'.$match.'@', $this->path());
    }

    /**
     * L'address ip du client
     *
     * @return string
     */
    public function ip()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Retourne de port du client
     *
     * @return string
     */
    public function port()
    {
        return $_SERVER['REMOTE_PORT'];
    }

    /**
     * Retourne la provenance de la requête courante.
     *
     * @return string
     */
    public function referer()
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    }

    /**
     * retourne la locale de la requête.
     *
     * la locale c'est langue original du client
     * e.g fr => locale = fr_FR // français de france
     * e.g en => locale [ en_US, en_EN]
     *
     * @return string|null
     */
    public function locale()
    {
        $accept_language = $this->getHeader('accept_language');

        $tmp = explode(';', $accept_language)[0];

        preg_match('/^([a-z]+(?:-|_)?[a-z]+)/i', $tmp, $match);

        return end($match);
    }

    /**
     * retourne la lang du naviagateur.
     *
     * @return string|null
     */
    public function lang()
    {
        $accept_language = $this->getHeader('accept_language');

        $language = explode(',', explode(';', $accept_language)[0])[0];

        preg_match('/([a-z]+)/', $language, $match);

        return end($match);
    }

    /**
     * le protocol de la requête.
     *
     * @return mixed
     */
    public function protocol()
    {
        return $this->scheme();
    }

    /**
     * Vérifier le protocol de la requête
     *
     * @param string $protocol
     * @return mixed
     */
    public function isProtocol($protocol)
    {
        return $this->scheme() == $protocol;
    }

    /**
     * Vérifier si le protocol sécurisé
     *
     * @return mixed
     */
    public function isSecure()
    {
        return $this->isProtocol('https');
    }

    /**
     * Get Request header
     *
     * @param  string $key
     * @return bool|string
     */
    public function getHeader($key)
    {
        $key = str_replace('-', '_', strtoupper($key));

        if ($this->hasHeader($key)) {
            return $_SERVER[$key];
        }

        if ($this->hasHeader('HTTP_'.$key)) {
            return $_SERVER['HTTP_'.$key];
        }

        return null;
    }

    /**
     * Verifir si une entête existe.
     *
     * @param  string $key
     * @return bool
     */
    public function hasHeader($key)
    {
        return isset($_SERVER[strtoupper($key)]);
    }

    /**
     * Get session information
     *
     * @return Session
     */
    public function session()
    {
        return session();
    }

    /**
     * Get cookie
     *
     * @param string $property
     * @return mixed
     */
    public function cookie($property = null)
    {
        return cookie($property);
    }

    /**
     * Get, permet de récupérer une valeur ou la colléction de valeur.
     *
     * @param  string $key     =null
     * @param  mixed  $default =false
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->input[$key] ?? $default;
    }

    /**
     * Permet récupérer les valeurs contenu dans le tableau d'exception
     *
     * @param array $exceptions
     * @return array
     */
    public function only($exceptions)
    {
        $data = [];

        if (! is_array($exceptions)) {
            $exceptions = func_get_args();
        }

        foreach ($exceptions as $exception) {
            if (isset($this->input[$exception])) {
                $data[$exception] = $this->input[$exception];
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function ignore($ignores)
    {
        $data = $this->input;

        if (! is_array($ignores)) {
            $ignores = func_get_args();
        }

        foreach ($ignores as $ignore) {
            if (isset($data[$ignore])) {
                unset($data[$ignore]);
            }
        }

        return $data;
    }

    /**
     * Permet de valider les données entrantes
     *
     * @param  array $rule
     * @return Validate
     */
    public function validate(array $rule)
    {
        return Validator::make($this->input, $rule);
    }

    /**
     * @param $property
     * @return mixed
     */
    public function __get($property)
    {
        return $this->get($property);
    }
}
