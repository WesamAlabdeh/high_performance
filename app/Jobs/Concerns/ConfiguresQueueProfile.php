<?php

namespace App\Jobs\Concerns;

trait ConfiguresQueueProfile
{
    protected function configureQueueProfile(string $profile): void
    {
        $settings = config("high_performance.job_profiles.{$profile}");

        $this->tries = (int) $settings['tries'];
        $this->timeout = (int) $settings['timeout'];
        $this->backoff = (int) $settings['backoff'];
        $this->failOnTimeout = (bool) $settings['fail_on_timeout'];
    }
}
