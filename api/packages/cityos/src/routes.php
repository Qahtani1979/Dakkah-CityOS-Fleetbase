<?php

use Illuminate\Support\Facades\Route;

Route::prefix(config('cityos.api.routing.prefix', 'cityos'))->namespace('Fleetbase\CityOS\Http\Controllers')->group(
    function ($router) {
        $router->prefix(config('cityos.api.routing.internal_prefix', 'int'))->group(
            function ($router) {
                $router->group(
                    ['prefix' => 'v1', 'middleware' => ['fleetbase.protected']],
                    function ($router) {
                        $router->fleetbaseRoutes('countries');
                        $router->fleetbaseRoutes('cities');
                        $router->fleetbaseRoutes('sectors');
                        $router->fleetbaseRoutes('categories');
                        $router->fleetbaseRoutes('tenants', function ($router, $controller) {
                            $router->get('{id}/node-context', $controller('getNodeContext'));
                        });
                        $router->fleetbaseRoutes('channels');
                        $router->fleetbaseRoutes('surfaces');
                        $router->fleetbaseRoutes('portals');

                        $router->get('hierarchy/tree', 'HierarchyController@tree');
                        $router->get('hierarchy/resolve', 'HierarchyController@resolve');
                        $router->get('hierarchy/stats', 'HierarchyController@stats');
                    }
                );
            }
        );

        $router->prefix('v1')->group(
            function ($router) {
                $router->get('hierarchy/tree', 'HierarchyController@tree');
                $router->get('hierarchy/resolve', 'HierarchyController@resolve');
            }
        );
    }
);
