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
        /** @var array<string, mixed> */
        private array $data = [];

        public function getData($key, $locale = null): mixed
        {
            if (!is_string($key)) {
                return null;
            }

            $value = $this->data[$key] ?? null;

            if ($locale !== null && is_array($value) && is_string($locale)) {
                return $value[$locale] ?? null;
            }

            return $value;
        }

        public function setData($key, $value, $locale = null): void
        {
            if (!is_string($key)) {
                return;
            }

            if ($locale !== null && is_string($locale)) {
                if (!isset($this->data[$key]) || !is_array($this->data[$key])) {
                    $this->data[$key] = [];
                }

                $this->data[$key][$locale] = $value;

                return;
            }

            $this->data[$key] = $value;
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
