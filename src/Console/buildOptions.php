<?php

namespace Crud\Console;

trait buildOptions
{
    /**
     * Build the options
     *
     * @return $this|array
     */
    protected function buildOptions()
    {
        $route = $this->option('route');

        if (!empty($route)) {
            $this->options['route'] = $route;
        }

        return $this;
    }
}
