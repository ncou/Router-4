<?php
/**
 * Created by Ian den Hartog
 * Version 0.3
 * Copyright (c) 2013-2014 Ian den Hartog
 */

namespace Router;


class Router
{
    const VERSION = '0.3';
    protected $route = array();
    protected $method;
    protected $wildcard = false;

    function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Create a new route using a method, route and callback
     *
     * @param $method
     * @param string $route
     * @param null $callback
     */
    public function add($method, $route = '/', $callback = null)
    {
        extract($this->getLooseArg(func_get_args()), EXTR_OVERWRITE);
        if ($method == null) {
            $method = array('GET', 'POST', 'PUT', 'DELETE'); //default value
        }
        if (!is_object($route)) {
            $route = new Route($route);
        }

        $this->addMethod($method, $route, $callback);
    }

    private function getLooseArg(array $array)
    {
        $callback = array_pop($array);
        $route = array_pop($array);
        $method = array_pop($array);

        return array(
            'method' => $method,
            'route' => $route,
            'callback' => $callback,
        );
    }

    private function addMethod($method, Route $route, $callback)
    {
        if (is_callable($callback)) {
            $row = compact('method', 'route', 'callback');
            $this->route[] = $row;
        }
    }

    public function get($route, $callback)
    {
        $this->add('GET', $route, $callback);
    }
    public function post($route, $callback)
    {
        $this->add('POST', $route, $callback);
    }
    public function put($route, $callback)
    {
        $this->add('PUT', $route, $callback);
    }
    public function delete($route, $callback)
    {
        $this->add('DELETE', $route, $callback);
    }


    /**
     * @param $url
     * @param null $call
     * @return mixed
     */
    public function route($url, $call = null)
    {
        foreach ($this->route as $route) {
            if ($route['method'] == $this->method || (is_array($route['method']) && in_array($this->method, $route['method']))) {


                if (preg_match($route['route']->pattern($route['route']), $url)) {
                    return call_user_func($route['callback']); //simple url
                } else {
                    $result = $this->match($route['route'], $url);
                    if ($result != false) {

                        $object = json_decode(json_encode($result), FALSE);
                        return call_user_func($route['callback'], $object);
                    }
                }
            }
        }
        if (is_callable($call)) {
            return call_user_func($call);
        }
    }

    private function match($pattern, $url)
    {
        $parts = explode('/', trim($pattern, '/'));
        $partsUrl = explode('/', trim($url, '/'));
        $vars = null;
        if (count($parts) <= count($partsUrl)) {
            $patterns = array();
            for ($i = 0; $i < count($parts); $i++) {
                $patterns[] = array($parts[$i] => $partsUrl[$i]);
            }
        } else {
            return false;
        }
        foreach ($patterns as $route) {
            foreach ($route as $key => $value) {
                if (substr($key, 0, 1) == ':' || substr($key, 0, 1) == '*' || (substr($key, 0, 1) == '[' && substr($key, -1, 1) == ']') || $this->wildcard == true || preg_match($pattern->pattern($key), $value)) {
                    if (substr($key, 0, 1) == ':') {
                        $key = substr($key, 1);
                        $vars[$key] = $value;
                    } elseif (substr($key, 0, 1) == '[' && substr($key, -1, 1)) {
                        $key = substr($key, 1);
                        $key = substr_replace($key, "", -1);
                        if (strpos($key, '|'))
                        {
                            $array = explode("|", $key);

                            foreach ($array as $check) {
                                if (preg_match($pattern->pattern($check), $value)) {
                                    $true = true;
                                }
                            }
                            if (!isset($true)) {
                                return false;
                            } else {
                                $result = array(
                                    $value => true
                                );
                                return $result;
                            }
                        }
                        elseif((substr($key, 0,1) == '(') && (strpos($key, ')'))){
                            preg_match_all("/\(.*?\)/", $key, $matches);
                            $regexes = $matches[0];
                            $urlPart = explode(')', $key);
                            $urlPart = $urlPart[1];

                            if($this->inputCheck($value,$regexes) == true)
                            {
                                $vars[$urlPart] = $value;
                                return $vars;
                            }

                        }
                    } elseif (substr($key, 0, 1) == '*' || $this->wildcard == true) {
                        $this->wildcard = true;
                    }
                } else {
                    return false;
                }

            }
        }
        if ($this->wildcard == true) {
            return true;
        } elseif (count($parts) == count($partsUrl))
            return $vars;
        else
            return false;
    }
    private function inputCheck($value, $type)
    {
        switch($type[0])
        {
            case "(i)":
                if(preg_match('/^\d+$/',$value))
                    return true;
                else
                    return false;
                break;

            case "(a)":
                if(preg_match('/^[\w\-]+$/',$value))
                    return true;
                else
                    return false;
                break;
            default:
                return false;

        }
    }
} 