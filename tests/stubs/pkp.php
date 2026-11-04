<?php

/**
 * Test stand-ins only; these are not the real PKP implementation.
 */

namespace PKP\plugins {
    abstract class Plugin
    {
        public $pluginPath;

        public $pluginCategory;

        public function register($category, $path, $mainContextId = null)
        {
            $this->pluginCategory = $category;
            $this->pluginPath = $path;

            return true;
        }

        public function getCategory()
        {
            return $this->pluginCategory;
        }

        public function getPluginPath()
        {
            return $this->pluginPath;
        }

        abstract public function getName();

        abstract public function getDisplayName();

        abstract public function getDescription();
    }

    abstract class LazyLoadPlugin extends Plugin
    {
        public function getName()
        {
            return strtolower((new \ReflectionClass($this))->getShortName());
        }
    }

    abstract class GenericPlugin extends LazyLoadPlugin
    {
    }
}

namespace {
    function __($key = null, $replace = [], $locale = null): string|array|null
    {
        return $key;
    }
}
