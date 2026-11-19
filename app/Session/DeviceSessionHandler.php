<?php

namespace App\Session;

use App\Auth\DeviceId;
use Illuminate\Session\DatabaseSessionHandler;

/**
 * The database session handler, plus the browser's device id (App\Auth\DeviceId) in the `sessions.device_id`
 * column, next to the user id, IP and user agent Laravel already keeps there.
 */
class DeviceSessionHandler extends DatabaseSessionHandler
{
    protected function addRequestInformation(&$payload)
    {
        parent::addRequestInformation($payload);

        if ($this->container?->bound('request')) {
            $payload['device_id'] = DeviceId::from($this->container->make('request'));
        }

        return $this;
    }
}
