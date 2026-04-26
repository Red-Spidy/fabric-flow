<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->routes(
            function () {
                // Health-check route (exists for Docker/LB probes)
                Route::get(
                    '/health',
                    function (Request $request) {
                        return response()->json(
                            [
                                'status' => 'ok',
                                'time'   => microtime(true) - $request->attributes->get('request_start_time'),
                            ]
                        );
                    }
                );

                // Fabric Manufacturing Supply Chain API routes
                if (file_exists(base_path('routes/api.php'))) {
                    Route::middleware([\Illuminate\Routing\Middleware\SubstituteBindings::class])
                         ->group(base_path('routes/api.php'));
                }
            }
        );
    }
}
