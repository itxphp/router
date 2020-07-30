<?php

/**
 * @package    itx framework
 * @author     itx team
 * @copyright  2019-2020 itxTech
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link       https://itxTech.com
 * @since      Version 4.0.0
 */
namespace Itx\Router;


class RouteBuilder
{
    public $host     = "global";
    private $keys     = [];
    private $routes     = [];
    private $prefix = "";
    private $postfix     = "";
    protected $namespace = null;
    protected $current = null;
    protected $groupsMiddlewares = [];
    protected $namedRoutes = [];

    /**
     *  Convert route variables into regex 
     *  example /users/:id will be (?<id>[0-9]{1,}) and /users/(<uid>:id) will be (?<uid>[0-9]{1,})
     *  @var string[] 
     */
    private $pregex     = [
        "(<"        =>  "(?<",
        ">:query"   =>  ">\?([\w-]+(=[\w-]*)?(&[\w-]+(=[\w-]*)?)*)",
        ":query"    =>  "(?<query>\?([\w-]+(=[\w-]*)?(&[\w-]+(=[\w-]*)?)*))",
        ">:id"      =>  ">[0-9]{1,}",
        ":id"       => "(?<id>[0-9]{1,})",
        ">:string"  => ">[a-z0-9A-Z\-\_\.\%\p{L}]{1,}",
        ":string"   => "(?<string>[a-z0-9A-Z\-\_\.\%\p{L}]{1,})",
        ">:slug"    =>  ">[a-zA-Z0-9\%\-\_\p{L}]{1,}",
        ":slug"     =>  "(?<slug>[a-zA-Z0-9\%\-\_\p{L}]{1,})",
        ">:any"     => "\?.*\/?",
        ">:md5"     => ">[a-zA-Z0-9]{32}",
        ":md5"      => "(?<md5>[a-zA-Z0-9]{32})",
        ">:iso"     => ">[a-z]{2,3}",
        ":iso"      =>  "(?<iso>[a-z]{2,3})",
        ":any"      => "(?<any>.*\/?)",
        ":nil"      => "(?![\s\S])",
    ];

    /**
     *  
     * 
     */

    public function __construct($callback = "", $options = [])
    {
        $this->group(["host" => "global"], $callback, $options);
    }

    private function setRoute($method, $path,  $action, $options = null)
    {
        if (gettype($action) == "string") {
            list($action, $name) = array_pad(explode(" as ", $action), 2, $action);
        } else {
            $name = "closure";
        }


        $this->prefix = trim($this->prefix, "/") . "/";

        $index = trim($this->prefix . trim(strtr($path, $this->pregex), "/") . $this->postfix, "/");

        $index = $index ?? '/';

        $this->routes[$this->host][$method]["route"][$index] = [
            "action"        => $action,
            "name"          => $name,
            "group"         => $this->host . $this->prefix . $this->postfix,
            "options"       => $options ?? [],
            "method"        => $method,
            "namespace"     => $this->namespace,
            "dir"           => "",
            "headers"       => [],
            "middlewares"   => [] ,
            "regex"         => $index
        ];

        $this->current = &$this->routes[$this->host][$method]["route"][$index];

        return $this;
    }
    public function auto($path, $name = null)
    {
        return $this->setRoute("any", $path, $this->auto, $name);
    }
    public function get($path, $action, $name = null)
    {
        return $this->setRoute("get", $path, $action, $name);
    }
    public function post($path, $action, $name = null)
    {
        return $this->setRoute("post", $path, $action);
    }
    public function delete($path, $action, $name = null)
    {
        return $this->setRoute("delete", $path, $action, $name);
    }
    public function put($path, $action, $name = null)
    {
        return $this->setRoute("put", $path, $action,  $name);
    }
    public function patch($path, $action, $name = null)
    {
        return $this->setRoute("patch", $path, $action, $name);
    }
    public function any($path, $action, $name = null)
    {
        return $this->setRoute("any", $path, $action, $name);
    }
    public function setPattern($var, $regex)
    {
        $this->pregex[$var] = $regex;
    }
    public function crud($path, $action, $options = null)
    {
        $crud = [
            "main" => "get",
            "show"  => "get",
            "store" => "post",
            "put"   => "update",
            "delete" => "delete"
        ];

        $resource = $options["key"] ?? 'id';
        $name = $options["name"] ?? $action;
        
        foreach ($crud as $crudAction => $method) {
            if (!in_array($crudAction, ["store", "main"])) {
                $crudPath = "{$path}/(<{$resource}>:id)";
            } else {
                $crudPath = $path;
            }

            $this->setRoute($method, str_replace("//", "/", $crudPath), "{$action}.{$crudAction} as {$name}.{$crudAction}", []);
        }

        return $this;
    }

    public function import($use)
    {
        // TODO ...


    }

    public function group($array, $callback)
    {
        
        $history = [
            "host" => $this->host  ,
            "prefix" => $this->prefix ,
            "postfix" => $this->postfix ,
            "namespace" => $this->namespace 
        ] ;

        $this->host     = $array["host"] ??  $this->host ?? "";
        $this->prefix   = $array["prefix"] ?? $this->prefix ?? "";
        $this->postfix  = $array["postfix"] ??  $this->postfix ?? "";
        $this->namespace    =  $array["namespace"] ?? $this->namespace ?? "";
        $this->group    = $this->host . $this->prefix . $this->postfix;
        
        $this->keys[$this->host]   =  $this->host;

        $callback($this);

        foreach($history as $key => $value)  {
            $this->{$key} = $value ;
        }

        $this->group    = $this->host . $this->prefix . $this->postfix;
        
        return $this;
    }
    public function withMiddleware($list = [])
    {
        $list = (array) $list;
        foreach ($list as $middleware) {
            $this->current["middlewares"][$middleware] =  $middleware;
            $this->groupsMiddlewares[$this->current["group"]][$middleware] =  $middleware;
        }
        return $this;
    }

    public function withMiddlewareGroup($name)
    {
        return [];
    }
    
    public function redirect($path, $url)
    {
        $this->setRoute("get", $path , "__Redirect" )->current["headers"]["location"] = $url;
        return $this;
    }


    public function robots($action)
    {
        $this->current["headers"]["x-robots-tag"] = $action;
        return $this;
    }

    public function as($name)
    {
        $this->current["name"] = $name;
        $this->namedRoutes[$name] = $this->current["regex"] ?? "/";
        return $this;
    }

    public function getLevels()
    {
        return $this->levels ;
    }

    public function withHeader($key, $value)
    {
        $this->current["headers"][$key] = $value;
        return $this;
    }


    public function getKeys()
    {
        return $this->keys ?? [];
    }

    public function getRoutes()
    {
        return $this->routes ?? [];
    }

    public function getGroupsMiddleware()
    {
        return $this->groupsMiddlewares ?? [];
    }

    public function getGroupMiddleware($group)
    {
        return $this->groupsMiddlewares[$group] ?? [];
    }

    public function getNamedRoutes()
    {
        return $this->namedRoutes ;
    }
}
