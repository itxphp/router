<?php
namespace Itx\Router;

class Router
{


    static $namedRoutes = [] ;
    static $currentRoute = null ;

    protected $namespace = null;
    protected $action = null;
    protected $groupMiddleWares = null;

    public function __construct(\Itx\Router\RouteBuilder $builder, \Psr\Http\Message\ServerRequestInterface $request , array $config)
    {


       

        //self::$routes = $routes ;
        $autoRoute = false;
        // find proper server ;
        $key = "parent";


        $validMethods = [strtolower($this->getCurrentMethod($request)), "any"];
      
        $routes = $builder->getRoutes();


        self::$namedRoutes = $builder->getNamedRoutes() ;

        
        foreach ($builder->getHosts() as $_key) {
            if (preg_match("/^{$_key}$/",  $request->getServerParams()["SERVER_NAME"])) {
                $key = $_key;
                break;
            }
        }


        $currentUrl = $this->getCurrentUrl($request);

        $validKeys = [$key, "parent"];



        $scheme = $request->getUri()->getScheme() ;


        foreach ($validKeys as $key) {
            foreach ($validMethods as $method) {
                if (isset($routes[$scheme][$key][$method])) {
                    foreach ($routes[$scheme][$key][$method]["route"] as $regex => $action) {
                        if (preg_match("#^" . $regex . "$#J", $currentUrl, $out)) {
                            unset($out[0]);
                            if (is_callable($action["action"])) {
                                // just for example .. 
                                exit($action["action"]($request));

                            } else if($action["action"] == '__Redirect') {
                                header("location: {$action['headers']['location']}") ;
                                exit();
                            } else {
                                $action_string     = explode("\\", $action["action"]);
                                $controller = ucfirst(array_pop($action_string));

                                list($action["controller"], $action["method"]) = array_pad(
                                    explode(".", $controller),
                                    2,
                                    $out["method"] ?? 'main'
                                );

                             
                                

                                if (count($action_string) > 0) {
                                    $action["dir"] = implode(DS, $action_string) . DS;
                                    $action["namespace"] = implode("\\", $action_string) . "\\";
                                }
                                $action["middlewares"] = $builder->getGroupMiddleware($action["scope"]) + $action["middlewares"];
                                $action["headers"] = $builder->getGroupHeaders($action["scope"]) + $action["headers"];

                                $action["params"] = $out;
                                self::$currentRoute = $action["name"];
                                $this->action = $action;
                            }
                        }
                    }
                }
            }
        }
    }
    public static function getNamedRoute($name)
    {
        if(array_key_exists($name , self::$namedRoutes)) {

        }

        return null ;
    }
    public function getAction($what = null)
    {
        return $what == null ? $this->action : $this->action[$what];
    }
    public function getParsed()
    {
        return $this->action;
    }
    public function getRouteName()
    {
        return  $this->action["name"] ?? null;
    }
    public function getGroupName()
    {
        return $this->action["group"] ?? null;
    }

    public function getMiddlewares()
    {

        return $this->action["middlewares"] ?? [];
    }

    public function getHeaders()
    {
        return $this->action["headers"] ?? [];
    }

    public function getParams()
    {
        return $this->action["params"] ?? [];
    }


    public function hasAction()
    {
        return $this->action !== null;
    }


    private function getCurrentUrl($request)
    {
        $current =  trim(str_replace([str_replace("/index.php", "", $request->getServerParams()['SCRIPT_NAME']), "/?"], ["", "?"], $request->getServerParams()['REQUEST_URI']), "/");

        list($current, $query) = array_pad(explode("?", $current), 2, null);

        return urldecode($current);
    }

    private function getCurrentMethod($request)
    {

        $method = $original_method = $request->getServerParams()["REQUEST_METHOD"];

        if ($method == "GET") {

            return $method;
        }

        $valid = [
            'PUT', 'POST', 'DELETE', 'PATCH'
        ];

        isset($request->getServerParams()['HTTP_X_HTTP_METHOD_OVERRIDE']) && ($method  = strtoupper($request->getServerParams()['HTTP_X_HTTP_METHOD_OVERRIDE']));

        isset($request->getParsedBody()["_method"]) && ($method  = strtoupper($request->getParsedBody()["_method"]));

        return in_array($method, $valid) ? $method : $original_method;
    }
}
