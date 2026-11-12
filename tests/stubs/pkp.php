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

namespace Illuminate\Database\Migrations {
    abstract class Migration
    {
        public function up(): void
        {
        }

        public function down(): void
        {
        }
    }
}

namespace Illuminate\Database\Schema {
    class Blueprint
    {
        public function unsignedBigInteger(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function unsignedInteger(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function string(string $column, int $length = 255): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function text(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function timestamp(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }
    }

    class ColumnDefinition
    {
        public function primary(): self
        {
            return $this;
        }

        public function nullable(): self
        {
            return $this;
        }

        public function default(mixed $value): self
        {
            return $this;
        }
    }
}

namespace Illuminate\Database\Query {
    class Builder
    {
        public function where(string $column, mixed $operator = null, mixed $value = null): self
        {
            return $this;
        }

        public function first(): ?object
        {
            return null;
        }

        /**
         * @param array<string, mixed> $values
         */
        public function update(array $values): int
        {
            return 0;
        }

        /**
         * @param array<string, mixed> $values
         */
        public function insertOrIgnore(array $values): int
        {
            return 0;
        }
    }
}

namespace Illuminate\Support\Facades {
    class DB
    {
        public static function table(string $table): \Illuminate\Database\Query\Builder
        {
            return new \Illuminate\Database\Query\Builder();
        }
    }

    class Schema
    {
        public static function hasTable(string $table): bool
        {
            return false;
        }

        public static function create(string $table, callable $callback): void
        {
        }

        public static function dropIfExists(string $table): void
        {
        }
    }
}

namespace {
    function __($key = null, $replace = [], $locale = null): string|array|null
    {
        return $key;
    }
}
