<?php
declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        // MCP endpoint
        $router->addRoute('mcp', 'Mcp:Mcp:default');

        // REST API v1 for external integrations (ChatGPT Actions, Make.com, n8n)
        $router->addRoute('api/v1/openapi.json', 'Api:V1:openapi');
        $router->addRoute('api/v1/tools/<toolName>', 'Api:V1:tool');
        $router->addRoute('api/v1/jobs/<id>', 'Api:V1:jobStatus');
        $router->addRoute('api/v1/jobs', 'Api:V1:jobs');
        $router->addRoute('api/v1/artifacts/<id>/download', 'Api:V1:artifactDownload');

        // Internal API for worker
        $router->addRoute('api/internal/jobs/next', 'Internal:Jobs:next');
        $router->addRoute('api/internal/jobs/<id>/artifacts', 'Internal:Jobs:artifacts');
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
