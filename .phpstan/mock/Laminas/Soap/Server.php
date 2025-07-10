<?php

namespace Laminas\Soap;

class Server {

    /**
     * @param  string $wsdl
     * @param  array $options
     * @throws ExtensionNotLoadedException
     */
    public function __construct($wsdl = null, ?array $options = null){}

    /**
     * @param  string|object $class Class name or object instance which executes
     *             SOAP Requests at endpoint.
     * @param  string $namespace
     * @param  null|array $argv
     * @return self
     * @throws InvalidArgumentException If called more than once, or if class does not exist.
     */
    public function setClass($class, $namespace = '', $argv = null){}

    /**
     * @param  DOMDocument|DOMNode|SimpleXMLElement|stdClass|string $request Optional request
     * @return void|string
     */
    public function handle(){}

    /**
     * @param  bool $flag
     * @return self
     */
    public function setReturnResponse($flag = true){}
}
