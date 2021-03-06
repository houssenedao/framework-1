<?php

namespace Bow\View;

use Bow\Configuration\Loader;
use Bow\View\Exception\ViewException;

abstract class EngineAbstract
{
    /**
     * Liste des helpers
     * @var array
     */
    const HELPERS = [
        'secure' => 'secure',
        'route' => 'route',
        'bhash' => 'bhash',
        'config' => 'config',
        'faker' => 'faker',
        'env' => 'env',
        'app_mode' => 'app_mode',
        'app_lang' => 'app_lang',
        'flash' => 'flash',
        'cache' => 'cache',
        'encrypt' => 'encrypt',
        'decrypt' => 'decrypt',
        'collect' => 'collect',
        'url' => 'url',
        'input' => 'input',
        'response' => 'response',
        'request' => 'request',
        'sanitaze' => 'sanitaze',
        'slugify' => 'slugify',
        'str_slug' => 'str_slug',
        'session' => 'session',
        'csrf_token' => 'csrf_token',
        'csrf_field' => 'csrf_field',
        'trans' => 'trans',
        't' => 't',
        'escape' => 'e',
        'old' => 'old',
        "public_path" => "public_path",
        "component_path" => "component_path",
        "storage_path" => "storage_path",
        "client_locale" => "client_locale",
        "auth" => "auth",
    ];

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Loader
     */
    protected $config;

    /**
     * Permet de transforme le code du temple en code html
     *
     * @param  string $filename
     * @param  array  $data
     * @return mixed
     */
    abstract public function render($filename, array $data = []);

    /**
     * Permet de verifier le fichier à parser
     *
     * @param  string $filename
     * @param  bool   $extended
     * @return string
     * @throws ViewException
     */
    protected function checkParseFile($filename, $extended = true)
    {
        $tmp_filename = preg_replace('/@|\./', '/', $filename) . $this->config['view.extension'];

        // Vérification de l'existance du fichier
        if ($this->config['view.path'] !== null) {
            if (!file_exists($this->config['view.path'].'/'.$tmp_filename)) {
                throw new ViewException(
                    sprintf(
                        'La vue [%s] n\'existe pas. %s/%s',
                        $tmp_filename,
                        $this->config['view.path'],
                        $filename
                    ),
                    E_ERROR
                );
            }
        } else {
            if (!file_exists($tmp_filename)) {
                throw new ViewException(
                    sprintf('La vue [%s] n\'existe pas!.', $tmp_filename),
                    E_ERROR
                );
            }
        }

        if ($extended) {
            $filename = $tmp_filename;
        }

        return $filename;
    }

    /**
     * Permet de retourne le nom de template charge
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }
}
