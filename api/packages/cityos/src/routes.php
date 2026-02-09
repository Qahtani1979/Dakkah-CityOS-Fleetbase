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

                        $router->prefix('integrations')->group(function ($router) {
                            $router->get('status', 'IntegrationController@status');
                            $router->get('logs', 'IntegrationController@integrationLogs');

                            $router->prefix('temporal')->group(function ($router) {
                                $router->get('connection', 'IntegrationController@temporalConnection');
                                $router->get('workflows', 'IntegrationController@temporalWorkflows');
                                $router->post('workflows/start', 'IntegrationController@temporalStartWorkflow');
                                $router->get('workflows/{workflowId}', 'IntegrationController@temporalQueryWorkflow');
                                $router->post('workflows/{workflowId}/signal', 'IntegrationController@temporalSignalWorkflow');
                                $router->post('sync/trigger', 'IntegrationController@temporalSyncTrigger');
                                $router->get('sync/status', 'IntegrationController@temporalSyncStatus');
                                $router->get('registry', 'IntegrationController@temporalWorkflowRegistry');
                                $router->get('registry/stats', 'IntegrationController@temporalWorkflowRegistryStats');
                            });

                            $router->prefix('cms')->group(function ($router) {
                                $router->get('health', 'IntegrationController@cmsHealth');
                                $router->get('nodes', 'IntegrationController@cmsNodes');
                                $router->get('tenants', 'IntegrationController@cmsTenants');
                                $router->get('pois', 'IntegrationController@cmsPOIs');
                                $router->get('collections', 'IntegrationController@cmsCollections');
                                $router->get('governance', 'IntegrationController@cmsGovernance');
                                $router->get('storage', 'IntegrationController@cmsStorage');
                                $router->get('storage/info', 'IntegrationController@cmsStorageInfo');
                            });

                            $router->prefix('erpnext')->group(function ($router) {
                                $router->get('status', 'IntegrationController@erpnextStatus');
                                $router->post('settlement', 'IntegrationController@erpnextSettlement');
                            });

                            $router->prefix('outbox')->group(function ($router) {
                                $router->get('stats', 'IntegrationController@outboxStats');
                                $router->post('dispatch', 'IntegrationController@outboxDispatch');
                                $router->post('publish', 'IntegrationController@outboxPublish');
                                $router->get('recent', 'IntegrationController@outboxRecent');
                            });
                        });
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
