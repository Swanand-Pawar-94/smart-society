<?php

return [
    // The society name and address are configuration, not invoice data hardcoded
    // in the client. Set SOCIETY_NAME / APP_NAME and SOCIETY_ADDRESS in the deployment environment.
    'name' => env('SOCIETY_NAME', env('APP_NAME', 'Kasliwal Marvel (West)')),
    'address' => env('SOCIETY_ADDRESS', "Kasliwal Marvel (West),\nBeed Bypass,\nChhatrapati Sambhajinagar,\nMaharashtra"),
];

