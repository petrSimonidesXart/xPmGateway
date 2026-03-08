<?php
declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList();

        // MCP endpoint
        $router->addRoute('mcp', 'Mcp:Mcp:default');

        // Internal API for worker
        $router->addRoute('api/internal/jobs/next', 'Internal:Jobs:next');
        $router->addRoute('api/internal/jobs/<id>/result', 'Internal:Jobs:result');

        // Admin module
        $admin = $router->withModule('Admin');
        $admin->addRoute('admin/login', 'Sign:in');
        $admin->addRoute('admin/logout', 'Sign:out');
        $admin->addRoute('admin/clients/<id>/tokens', 'Token:default');
        $admin->addRoute('admin/jobs/<id>/screenshot/<filename>', 'Job:screenshot');
        $admin->addRoute('admin/<presenter>/<action=default>[/<id>]', 'Dashboard:default');

        return $router;
    }
}
