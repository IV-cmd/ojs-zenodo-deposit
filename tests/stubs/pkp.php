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

namespace PKP\core {
    class DataObject
    {
        public function getData($key, $locale = null): mixed
        {
            return null;
        }
    }
}

namespace APP\submission {
    class Submission extends \PKP\core\DataObject
    {
    }
}

namespace APP\publication {
    class Publication extends \PKP\core\DataObject
    {
    }
}

namespace PKP\context {
    class Context extends \PKP\core\DataObject
    {
    }
}

namespace PKP\author {
    class Author extends \PKP\core\DataObject
    {
    }
}

namespace PKP\affiliation {
    class Affiliation extends \PKP\core\DataObject
    {
    }
}

namespace PKP\doi {
    class Doi extends \PKP\core\DataObject
    {
    }
}

namespace {
    function __($key = null, $replace = [], $locale = null): string|array|null
    {
        return $key;
    }
}
