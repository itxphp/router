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
    private $isGroup = true;
    private $scheme = "http";
    private $forceHttps = false;
    private $options = [];
    private $scope = "";
    private $prevScope = "";
    private $currentGroup = [
        "host" => "parent",
        "prefix" => "",
        "postfix" => "",
        "scheme" => "https",
        "namespace" => "\\",
        "dir" => "//"
    ];
    private $current = null;
    private $groupsMiddlewares = [];
    private $isRoute = false;
    private $hosts = [];

    /**
     *  Convert route variables into regex 
     *  example /users/@id will be (?<id>[0-9]{1,}) and /users/(<uid>@id) will be (?<uid>[0-9]{1,})
     *  @var string[] 
     */
    private $pregex     = [
        "(<"        =>  "(?<",
        ">@query"   =>  ">\?([\w-]+(=[\w-]*)?(&[\w-]+(=[\w-]*)?)*)",
        "@query"    =>  "(?<query>\?([\w-]+(=[\w-]*)?(&[\w-]+(=[\w-]*)?)*))",
        ">@id"      =>  ">[0-9]{1,}",
        "@id"       => "(?<id>[0-9]{1,})",
        ">@string"  => ">[a-z0-9A-Z\-\_\.\%\p{L}]{1,}",
        "@string"   => "(?<string>[a-z0-9A-Z\-\_\.\%\p{L}]{1,})",
        ">@slug"    =>  ">[a-zA-Z0-9\%\-\_\p{L}]{1,}",
        "@slug"     =>  "(?<slug>[a-zA-Z0-9\%\-\_\p{L}]{1,})",
        ">@any"     => "\?.*\/?",
        ">@md5"     => ">[a-zA-Z0-9]{32}",
        "@md5"      => "(?<md5>[a-zA-Z0-9]{32})",
        ">@uuid"    => ">[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}",
        "@uuid"     => "(?<uuid>[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12})",
        ">@iso"     => ">[a-z]{2,3}",
        "@iso"      =>  "(?<iso>[a-z]{2,3})",
        "@any"      => "^(?<any>[^\/]*\/?)$",
        "@nil"      => "(?![\s\S])",
    ];

    /**
     *  
     * 
     */

    public function __construct($callback = "", $options = [])
    {
        $this->isGroup = true;
        $this->options = $options;
        $this->group(["host" => "parent"], $callback);
    }



    private function setRoute($method, $path,  $action)
    {
        $this->isRoute = true;
        if (gettype($action) == "string") {
            [$action, $name] = array_pad(explode(" as ", $action), 2, $action);
        } else {
            $name = "closure";
        }

        $prefix = trim($this->currentGroup["prefix"], "/") . "/";

        $postfix = $this->currentGroup["postfix"];

        $index = trim($prefix . trim(strtr($path, $this->pregex), "/") . $postfix, "/");

        $index = $index ?? '/';

        $host = $this->currentGroup["host"];


        $namespace = $this->currentGroup["namespace"];


        $this->routes[$this->scheme][$host][$method]["route"][$index] = [
            "action"        => $action,
            "name"          => $name,
            "namespace"     => $namespace,
            "dir"           => str_replace("\\", DS, $namespace),
            "headers"       => [],
            "middlewares"   => [],
            "scope"         => $this->scope ,
            "pregex"         => $path
        ];

        $this->current = &$this->routes[$this->scheme][$host][$method]["route"][$index];

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
    public function options($path, $action, $name = null)
    {
        return $this->setRoute("options", $path, $action, $name);
    }
    public function any($path, $action, $name = null)
    {
        return $this->setRoute("any", $path, $action, $name);
    }
    public function only(array $methods, $path, $action, $name = null)
    {
        return $this->setRoute("any", $path, $action, $name);
    }


    // public function crud($path, $action, $options = null)
    // {
    //     $crud = [
    //         "main" => "get",
    //         "show"  => "get",
    //         "store" => "post",
    //         "put"   => "update",
    //         "delete" => "delete"
    //     ];

    //     $resource = $options["key"] ?? 'id';
    //     $name = $options["name"] ?? $action;

    //     foreach ($crud as $crudAction => $method) {
    //         if (!in_array($crudAction, ["store", "main"])) {
    //             $crudPath = "{$path}/(<{$resource}>:id)";
    //         } else {
    //             $crudPath = $path;
    //         }

    //         $this->setRoute($method, str_replace("//", "/", $crudPath), "{$action}.{$crudAction} as {$name}.{$crudAction}", []);
    //     }

    //     return $this;
    // }

    public function import($use)
    {
        // TODO ...


    }

    public function group($array, $callback)
    {
        $history = $this->currentGroup;
        $scope = $this->scope;
        $this->currentGroup = array_merge($history, $array);
        $groupName = $array["name"] ?? (($array["prefix"] ?? "") . ($array["scheme"] ?? "") . ($array["host"] ?? "") . ($array["postfix"] ?? ""));
        if (isset($array["host"])) {
            $this->hosts[$array["host"]] = $array["host"];
        }
        $this->scope = $this->scope . "::" . $groupName;
        $this->isRoute = false;
        $this->isGroup = true;
        if (isset($array["scheme"])) {
            $this->scheme = $array["scheme"];
        }
        $callback($this);
        $this->currentGroup = $history;
        $this->prevScope = $this->scope;
        $this->scope = $scope;
        $this->isGroup = true;
        $this->isRoute = false;
        $this->scheme = $this->forceHttps ? "https" : "http";
        return $this;
    }
    public function withMiddlewares($list = [])
    {
        $list = (array) $list;
        foreach ($list as $middleware) {
            if ($this->isRoute) {
                $this->current["middlewares"][$middleware] =  $middleware;
            } else {
                $this->groupsMiddlewares[$this->prevScope][$middleware] =  $middleware;
            }
        }
        return $this;
    }

    public function withHeaders($list = [])
    {
        $list = (array) $list;
        foreach ($list as $key => $value) {
            $this->current["headers"][$key] =  $value;
            if ($this->isGroup) {
                $this->groupsHeaders[$this->prevScope][$key] =  $value;
            }
        }
        return $this;
    }

    public function withMiddlewaresGroup($name)
    {
        return [];
    }

    public function setPattern($var, $regex)
    {
        $this->pregex[$var] = $regex;
    }

    public function redirect($path, $url)
    {
        $this->setRoute("get", $path, "__Redirect")->current["headers"]["location"] = $url;
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
        $this->namedRoutes[$name] = $this->currentGroup;
        $this->namedRoutes[$name]["pregex"] = $this->current["pregex"]; ;
        // = [
        //     "host" => $this->current["host"] ,
        //     "prefix" => $this->current["prefix"] ,
        //     "postfix" => $this->current["postfix"] ,
        //     "regex" => $this->current["regex"] 
        // ] ;
        return $this;
    }

    public function getLevels()
    {
        return $this->levels;
    }


    public function getHosts()
    {
        return $this->hosts ?? [];
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
        $start = "";
        $middlewares = [];
        $groupParts = explode("::", trim($group, "::"));
        foreach ($groupParts as $part) {
            $start .= "::" . $part;
            if (isset($this->groupsMiddlewares[$start])) {
                $middlewares = array_merge($middlewares, $this->groupsMiddlewares[$start]);
            }
        }
        return $middlewares;
    }

    public function getGroupHeaders($group)
    {
        return $this->groupsHeaders[$group] ?? [];
    }

    public function getNamedRoutes()
    {
        return $this->namedRoutes;
    }

    public function getVersionFromHeader($version = "Accept-version", $default = "V1")
    {
        return $_SERVER[$version] ?? $default;
    }

    public function forceHttps(bool $force)
    {
        $this->forceHttps = $force;
        $this->scheme = $force ? "https" : 'http' ;
        $force && $this->group(["scheme" => "http"], function ($route) {
            $route->any("@any", "__Redirect")->withHeaders([
                "location" => "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
            ]);
        });
    }

    public function preFlight()
    {
        $this->group([], function ($route) {
            $route->options("@any" , function() {
                header('Access-Control-Allow-Origin: http://localhost:3000');
                header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
                header('Access-Control-Allow-Headers: Accept, X-Requested-With, X-HTTP-Method-Override, Content-Type, Authorization , Origin , Accept-Language');
                header('Access-Control-Allow-Credentials: true');
            }) ;
        });
    }


    public function __destruct()
    {
    }
}
